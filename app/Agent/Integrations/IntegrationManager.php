<?php

namespace App\Agent\Integrations;

use App\Agent\Integrations\Connectors\AnthropicConnector;
use App\Agent\Integrations\Connectors\GoogleCalendarConnector;
use App\Agent\Integrations\Connectors\IntegrationConnector;
use App\Agent\Integrations\Connectors\MicrosoftGraphConnector;
use App\Agent\Integrations\Connectors\SmtpConnector;
use App\Agent\Integrations\Connectors\TelegramConnector;
use App\Agent\Integrations\Connectors\WhatsAppConnector;
use App\Models\AgentIntegration;
use App\Models\User;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Pengelola akses tool luar: menyimpan kredensial (terenkripsi), mengujinya,
 * dan menyusun panduan yang dibacakan agent ketika akses belum diberikan.
 *
 * Nilai kredensial tidak pernah keluar dari lapisan ini — API dan UI hanya
 * menerima status serta metadata non-rahasia.
 */
class IntegrationManager
{
    public function __construct(private readonly Container $container)
    {
    }

    /** @return array<string, mixed>|null */
    public function definition(string $key): ?array
    {
        return IntegrationCatalog::get($key);
    }

    public function connector(string $key): IntegrationConnector
    {
        return $this->container->make(match ($key) {
            'telegram'        => TelegramConnector::class,
            'anthropic'       => AnthropicConnector::class,
            'microsoft365'    => MicrosoftGraphConnector::class,
            'google_calendar' => GoogleCalendarConnector::class,
            'smtp_email'      => SmtpConnector::class,
            'whatsapp'        => WhatsAppConnector::class,
            default           => throw new InvalidArgumentException("Integrasi '{$key}' tidak dikenal."),
        });
    }

    public function record(string $organizationId, string $key): AgentIntegration
    {
        if (! IntegrationCatalog::get($key)) {
            throw new InvalidArgumentException("Integrasi '{$key}' tidak ada dalam katalog.");
        }

        return AgentIntegration::firstOrCreate(
            ['organization_id' => $organizationId, 'key' => $key],
            ['status' => 'not_configured', 'scopes' => IntegrationCatalog::get($key)['scopes'] ?? []],
        );
    }

    public function isConnected(string $organizationId, string $key): bool
    {
        return AgentIntegration::where('organization_id', $organizationId)
            ->where('key', $key)->where('status', 'connected')->exists();
    }

    /** @return array<string, string> */
    public function credentials(string $organizationId, string $key): array
    {
        $record = AgentIntegration::where('organization_id', $organizationId)->where('key', $key)->first();

        return $record && $record->isConnected() ? $record->secrets() : [];
    }

    /**
     * Menyimpan akses yang diberikan user lalu langsung mengujinya.
     *
     * @param  array<string, string>  $credentials
     */
    public function connect(string $organizationId, string $key, array $credentials, ?User $grantedBy = null): AgentIntegration
    {
        $definition = IntegrationCatalog::get($key) ?? throw new InvalidArgumentException("Integrasi '{$key}' tidak dikenal.");
        $record     = $this->record($organizationId, $key);

        // Kolom yang dikosongkan pada formulir tidak menghapus nilai lama.
        $merged = array_merge($record->secrets(), array_filter(
            $credentials,
            static fn ($value) => $value !== null && $value !== '',
        ));

        foreach ($definition['fields'] ?? [] as $field => $rules) {
            if (($rules['required'] ?? false) && empty($merged[$field])) {
                $record->fill(['status' => 'error', 'last_error' => "Kolom '{$rules['label']}' wajib diisi."])->save();

                return $record;
            }
        }

        $record->setSecrets($merged);

        $result = $this->connector($key)->verify($merged);

        $record->fill([
            'status'           => $result->ok ? 'connected' : 'error',
            'meta'             => $result->ok ? $result->meta : $record->meta,
            'scopes'           => $definition['scopes'] ?? [],
            'granted_by'       => $grantedBy?->id ?? $record->granted_by,
            'granted_at'       => $result->ok ? now() : $record->granted_at,
            'last_verified_at' => now(),
            'last_error'       => $result->ok ? null : $result->error,
        ])->save();

        return $record;
    }

    public function verify(string $organizationId, string $key): AgentIntegration
    {
        $record      = $this->record($organizationId, $key);
        $credentials = $record->secrets();
        $result      = $this->connector($key)->verify($credentials);

        $record->fill([
            'status'           => $result->ok ? 'connected' : ($record->hasSecrets() ? 'error' : 'not_configured'),
            'meta'             => $result->ok ? $result->meta : $record->meta,
            'last_verified_at' => now(),
            'last_error'       => $result->ok ? null : $result->error,
        ])->save();

        return $record;
    }

    /** User menolak memberi akses — agent berhenti menawarkannya. */
    public function deny(string $organizationId, string $key, ?User $user = null): AgentIntegration
    {
        $record = $this->record($organizationId, $key);
        $record->setSecrets([]);
        $record->fill(['status' => 'denied', 'granted_by' => $user?->id, 'last_error' => null])->save();

        return $record;
    }

    public function revoke(string $organizationId, string $key): AgentIntegration
    {
        $record = $this->record($organizationId, $key);
        $record->setSecrets([]);
        $record->fill([
            'status' => 'not_configured', 'meta' => null, 'granted_at' => null, 'last_error' => null,
        ])->save();

        return $record;
    }

    /**
     * Status seluruh akses untuk ditampilkan (tanpa nilai rahasia sedikit pun).
     *
     * @return array<int, array<string, mixed>>
     */
    public function overview(string $organizationId): array
    {
        $records = AgentIntegration::where('organization_id', $organizationId)->get()->keyBy('key');

        return array_values(array_map(function (array $definition, string $key) use ($records) {
            $record = $records->get($key);
            $stored = $record?->secrets() ?? [];

            $fields = [];
            foreach ($definition['fields'] ?? [] as $field => $rules) {
                $fields[] = [
                    'key'      => $field,
                    'label'    => $rules['label'],
                    'secret'   => (bool) ($rules['secret'] ?? false),
                    'required' => (bool) ($rules['required'] ?? false),
                    'hint'     => $rules['hint'] ?? null,
                    'filled'   => ! empty($stored[$field]),
                ];
            }

            return [
                'key'              => $key,
                'label'            => $definition['label'],
                'purpose'          => $definition['purpose'],
                'essential'        => (bool) ($definition['essential'] ?? false),
                'scopes'           => $definition['scopes'] ?? [],
                'guidance'         => $definition['guidance'] ?? [],
                'revoke'           => $definition['revoke'] ?? null,
                'fields'           => $fields,
                'status'           => $record?->status ?? 'not_configured',
                'meta'             => $record?->meta,
                'last_error'       => $record?->last_error,
                'last_verified_at' => $record?->last_verified_at?->toIso8601String(),
                'granted_at'       => $record?->granted_at?->toIso8601String(),
            ];
        }, IntegrationCatalog::all(), IntegrationCatalog::keys()));
    }

    /**
     * Akses yang masih perlu diminta agent (belum tersambung & belum ditolak).
     *
     * @return array<int, array<string, mixed>>
     */
    public function pending(string $organizationId, bool $essentialOnly = false): array
    {
        return array_values(array_filter(
            $this->overview($organizationId),
            static fn (array $row) => ! in_array($row['status'], ['connected', 'denied'], true)
                && (! $essentialOnly || $row['essential']),
        ));
    }

    /** Panduan pembuka yang dibacakan agent saat aplikasi mulai dijalankan. */
    public function onboardingScript(string $organizationId): string
    {
        $pending = $this->pending($organizationId);

        if ($pending === []) {
            return "Semua akses yang saya butuhkan sudah tersedia. Saya siap menerima pekerjaan.";
        }

        $lines = [
            'Halo! Saya ' . config('agent.name', 'Asisten Kantor') . ', asisten kantor Anda.',
            'Supaya bisa bekerja, saya perlu izin masuk ke beberapa alat. Berikut yang belum tersedia:',
            '',
        ];

        foreach ($pending as $row) {
            $lines[] = ($row['essential'] ? '• [WAJIB] ' : '• ') . $row['label'];
            $lines[] = '  Kegunaan: ' . $row['purpose'];

            if ($row['scopes'] !== []) {
                $lines[] = '  Yang saya minta: ' . implode('; ', $row['scopes']);
            }

            foreach ($row['guidance'] as $i => $step) {
                $lines[] = '  ' . ($i + 1) . '. ' . $step;
            }

            $lines[] = '';
        }

        $lines[] = 'Berikan akses lewat menu Asisten AI → Akses Tools. Anda boleh menolak salah satunya —';
        $lines[] = 'saya akan bekerja dengan kemampuan yang tersisa dan memberi tahu bila ada yang tidak bisa dilakukan.';

        return implode("\n", $lines);
    }
}
