<?php

namespace App\Http\Controllers\Agent;

use App\Agent\Channels\Telegram\TelegramBot;
use App\Agent\Channels\Telegram\TelegramClient;
use App\Agent\Integrations\IntegrationCatalog;
use App\Agent\Integrations\IntegrationManager;
use App\Http\Controllers\Controller;
use App\Models\AgentIntegration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pemberian akses tool oleh manusia. Nilai kredensial hanya bergerak satu arah
 * (masuk); tidak pernah dikirim kembali ke antarmuka.
 */
class AgentIntegrationController extends Controller
{
    public function update(Request $request, string $key, IntegrationManager $integrations): RedirectResponse
    {
        $this->authorizeAccess($request);

        $definition = IntegrationCatalog::get($key) ?? abort(404);

        $rules = [];
        foreach ($definition['fields'] as $field => $meta) {
            $rules['credentials.' . $field] = ['nullable', 'string', 'max:2000'];
        }

        $validated = $request->validate($rules);

        $record = $integrations->connect(
            (string) $request->user()->organization_id,
            $key,
            (array) ($validated['credentials'] ?? []),
            $request->user(),
        );

        return $record->isConnected()
            ? back()->with('success', $definition['label'] . ' berhasil terhubung.')
            : back()->with('error', $definition['label'] . ' gagal terhubung: ' . $record->last_error);
    }

    public function verify(Request $request, string $key, IntegrationManager $integrations): RedirectResponse
    {
        $this->authorizeAccess($request);

        $record = $integrations->verify((string) $request->user()->organization_id, $key);

        return $record->isConnected()
            ? back()->with('success', 'Koneksi masih sehat.')
            : back()->with('error', 'Uji koneksi gagal: ' . $record->last_error);
    }

    public function deny(Request $request, string $key, IntegrationManager $integrations): RedirectResponse
    {
        $this->authorizeAccess($request);

        $integrations->deny((string) $request->user()->organization_id, $key, $request->user());

        return back()->with('success', 'Akses ditolak. Asisten tidak akan memintanya lagi.');
    }

    public function revoke(Request $request, string $key, IntegrationManager $integrations): RedirectResponse
    {
        $this->authorizeAccess($request);

        $integrations->revoke((string) $request->user()->organization_id, $key);

        return back()->with('success', 'Akses dicabut dan kredensialnya dihapus.');
    }

    /** Kode sekali pakai untuk menautkan chat Telegram ke akun pengguna. */
    public function telegramLinkCode(Request $request, TelegramBot $bot): RedirectResponse
    {
        $code = $bot->issueLinkCode($request->user());

        return back()->with('success', 'Kode penautan: ' . $code
            . ' — kirim "/tautkan ' . $code . '" ke bot Telegram dalam '
            . config('agent.telegram.link_ttl', 30) . ' menit.');
    }

    /** Mendaftarkan URL webhook Telegram beserta rahasianya. */
    public function telegramWebhook(Request $request, TelegramClient $telegram, IntegrationManager $integrations): RedirectResponse
    {
        $this->authorizeAccess($request);

        $orgId  = (string) $request->user()->organization_id;
        $record = AgentIntegration::where('organization_id', $orgId)->where('key', 'telegram')->first();

        if (! $record || ! $record->isConnected()) {
            return back()->with('error', 'Hubungkan bot Telegram terlebih dahulu.');
        }

        $credentials = $record->secrets();
        $secret      = $credentials['webhook_secret'] ?? Str::random(40);

        if (empty($credentials['webhook_secret'])) {
            $credentials['webhook_secret'] = $secret;
            $record->setSecrets($credentials);
            $record->save();
        }

        $url = route('agent.telegram.webhook', ['organization' => $orgId, 'secret' => $secret]);

        return $telegram->setWebhook($orgId, $url, $secret)
            ? back()->with('success', 'Webhook Telegram aktif. Bot siap menerima pesan.')
            : back()->with('error', 'Telegram menolak pendaftaran webhook. Pastikan URL aplikasi dapat diakses publik.');
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()->hasRole('super_admin'), 403,
            'Hanya administrator yang boleh mengatur akses tool asisten.');
    }
}
