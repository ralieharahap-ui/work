<?php

namespace App\Console\Commands;

use App\Agent\Channels\Telegram\TelegramClient;
use App\Agent\Integrations\IntegrationCatalog;
use App\Agent\Integrations\IntegrationManager;
use App\Agent\Llm\LlmManager;
use App\Agent\Runtime\AgentTaskService;
use App\Agent\Tools\ToolRegistry;
use App\Models\Organization;
use Illuminate\Console\Command;

/**
 * Sapaan pembuka agent: menjelaskan apa yang bisa dikerjakannya, akses apa
 * yang dibutuhkan, untuk apa akses itu dipakai, dan bagaimana memberikannya —
 * lalu (dengan --interactive) menerima kredensialnya dan langsung mengujinya.
 */
class AgentSetup extends Command
{
    protected $signature = 'agent:setup
        {--org= : Batasi ke satu organisasi (UUID)}
        {--interactive : Minta kredensial akses satu per satu}
        {--connect= : Hubungkan satu akses tanpa tanya-jawab, mis. --connect=telegram}
        {--field=* : Nilai kredensial untuk --connect, format nama=nilai (boleh diulang)}
        {--verify : Uji ulang seluruh akses yang sudah tersambung}';

    protected $description = 'Panduan pembuka asisten AI: menjelaskan kebutuhan akses dan menerima pemberian akses.';

    public function handle(
        IntegrationManager $integrations,
        AgentTaskService $tasks,
        ToolRegistry $registry,
        LlmManager $llm,
        TelegramClient $telegram,
    ): int {
        $organizations = Organization::query()
            ->when($this->option('org'), fn ($q) => $q->whereKey($this->option('org')))
            ->get();

        if ($organizations->isEmpty()) {
            $this->error('Tidak ada organisasi yang cocok.');

            return self::FAILURE;
        }

        foreach ($organizations as $organization) {
            $this->newLine();
            $this->line("<comment>{$organization->name}</comment>");

            $agent = $tasks->agentFor($organization->id);

            if ($this->option('connect')) {
                $this->connectDirectly($organization->id, $integrations);

                continue;
            }

            $this->line('  Agent      : ' . $agent->name . ' (' . $agent->role . ', otonomi ' . $agent->autonomy . ')');
            $this->line('  Perencana  : ' . $llm->activeName());
            $this->line('  Tool aktif : ' . count($registry->names()) . ' — ' . implode(', ', $registry->names()));
            $this->newLine();

            $this->line($integrations->onboardingScript($organization->id));
            $this->newLine();

            if ($this->option('interactive')) {
                $this->collectCredentials($organization->id, $integrations);
            }

            if ($this->option('verify')) {
                $this->verifyAll($organization->id, $integrations);
            }

            $this->table(
                ['Akses', 'Status', 'Catatan'],
                collect($integrations->overview($organization->id))->map(fn (array $row) => [
                    $row['label'],
                    match ($row['status']) {
                        'connected'  => 'tersambung',
                        'denied'     => 'ditolak',
                        'error'      => 'gagal',
                        default      => 'belum diisi',
                    },
                    $row['last_error'] ?: (is_array($row['meta']) ? implode(', ', array_map(
                        static fn ($k, $v) => "{$k}: {$v}",
                        array_keys($row['meta']), array_values($row['meta']),
                    )) : '—'),
                ])->all(),
            );

            if ($telegram->isReady($organization->id)) {
                $this->info('  Bot Telegram siap. Jalankan "php artisan agent:telegram --poll" untuk pengembangan,'
                    . ' atau daftarkan webhook dari dasbor untuk produksi.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Pemberian akses lewat argumen — dipakai pada server tanpa TTY (Docker,
     * skrip deploy). Nilai kredensial tidak pernah ditampilkan kembali.
     */
    private function connectDirectly(string $organizationId, IntegrationManager $integrations): void
    {
        $key         = (string) $this->option('connect');
        $definition  = $integrations->definition($key);

        if (! $definition) {
            $this->error("Akses '{$key}' tidak dikenal. Pilihan: " . implode(', ', IntegrationCatalog::keys()));

            return;
        }

        $credentials = [];

        foreach ((array) $this->option('field') as $pair) {
            [$name, $value] = array_pad(explode('=', (string) $pair, 2), 2, null);

            if ($name !== null && $value !== null) {
                $credentials[trim($name)] = $value;
            }
        }

        if ($credentials === []) {
            $this->error('Sertakan minimal satu --field=nama=nilai. Kolom yang dibutuhkan: '
                . implode(', ', array_keys($definition['fields'] ?? [])) . '.');

            return;
        }

        $record = $integrations->connect($organizationId, $key, $credentials);

        if ($record->isConnected()) {
            $this->info("✔ {$definition['label']} tersambung."
                . ($record->meta ? ' (' . json_encode($record->meta, JSON_UNESCAPED_SLASHES) . ')' : ''));

            return;
        }

        // Pesan galat sudah diredaksi dari kredensial oleh IntegrationManager.
        $this->error("✖ {$definition['label']} gagal: {$record->last_error}");
    }

    private function collectCredentials(string $organizationId, IntegrationManager $integrations): void
    {
        foreach ($integrations->pending($organizationId) as $row) {
            $this->newLine();
            $this->line("<comment>{$row['label']}</comment> — {$row['purpose']}");

            foreach ($row['guidance'] as $i => $step) {
                $this->line('  ' . ($i + 1) . '. ' . $step);
            }

            if (! $this->confirm("Berikan akses {$row['label']} sekarang?", false)) {
                if ($this->confirm('Tandai sebagai ditolak supaya tidak ditanyakan lagi?', false)) {
                    $integrations->deny($organizationId, $row['key']);
                }

                continue;
            }

            $credentials = [];

            foreach (IntegrationCatalog::get($row['key'])['fields'] ?? [] as $field => $meta) {
                $label = $meta['label'] . ($meta['hint'] ?? '' ? " ({$meta['hint']})" : '');

                $credentials[$field] = ($meta['secret'] ?? false)
                    ? (string) $this->secret($label)
                    : (string) $this->ask($label);
            }

            $record = $integrations->connect($organizationId, $row['key'], $credentials);

            $record->isConnected()
                ? $this->info("  ✔ {$row['label']} tersambung.")
                : $this->error("  ✖ {$row['label']} gagal: {$record->last_error}");
        }
    }

    private function verifyAll(string $organizationId, IntegrationManager $integrations): void
    {
        foreach ($integrations->overview($organizationId) as $row) {
            if ($row['status'] !== 'connected') {
                continue;
            }

            $record = $integrations->verify($organizationId, $row['key']);

            $record->isConnected()
                ? $this->info("  ✔ {$row['label']} masih sehat.")
                : $this->warn("  ✖ {$row['label']}: {$record->last_error}");
        }
    }
}
