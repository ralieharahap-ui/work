<?php

namespace App\Console\Commands;

use App\Agent\Channels\Telegram\TelegramBot;
use App\Agent\Channels\Telegram\TelegramClient;
use App\Models\AgentIntegration;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Operasional kanal Telegram: menjalankan long-polling (praktis saat
 * pengembangan) atau mendaftarkan/menghapus webhook untuk produksi.
 */
class AgentTelegram extends Command
{
    protected $signature = 'agent:telegram
        {--org= : UUID organisasi (bawaan: organisasi pertama)}
        {--poll : Ambil pesan lewat long polling}
        {--set-webhook= : Daftarkan URL webhook (kosongkan untuk memakai URL aplikasi)}
        {--delete-webhook : Hapus pendaftaran webhook}
        {--once : Pada mode polling, berhenti setelah satu putaran}';

    protected $description = 'Menjalankan chatbot Telegram (polling) atau mengatur webhook-nya.';

    public function handle(TelegramClient $telegram, TelegramBot $bot): int
    {
        $organization = $this->option('org')
            ? Organization::find($this->option('org'))
            : Organization::first();

        if (! $organization) {
            $this->error('Organisasi tidak ditemukan.');

            return self::FAILURE;
        }

        if (! $telegram->isReady($organization->id)) {
            $this->error('Bot Telegram belum berkredensial. Jalankan: php artisan agent:setup --interactive');

            return self::FAILURE;
        }

        if ($this->option('delete-webhook')) {
            return $telegram->deleteWebhook($organization->id) ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('set-webhook') !== null) {
            return $this->registerWebhook($telegram, $organization->id, (string) $this->option('set-webhook'));
        }

        if (! $this->option('poll')) {
            $this->info('Tidak ada tindakan. Gunakan --poll, --set-webhook, atau --delete-webhook.');

            return self::SUCCESS;
        }

        // Long polling menutup webhook; keduanya tidak bisa aktif bersamaan.
        $telegram->deleteWebhook($organization->id);
        $this->info('Mendengarkan pesan Telegram… (Ctrl+C untuk berhenti)');

        $offset = 0;

        do {
            $updates = $telegram->getUpdates($organization->id, $offset, 25);

            foreach ($updates as $update) {
                $offset = max($offset, (int) ($update['update_id'] ?? 0) + 1);

                try {
                    $bot->handle($update, $organization->id);
                } catch (Throwable $e) {
                    report($e);
                    $this->warn('Gagal memproses satu pesan: ' . $e->getMessage());
                }
            }

            if ($updates !== []) {
                $this->line(count($updates) . ' pesan diproses.');
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    private function registerWebhook(TelegramClient $telegram, string $organizationId, string $url): int
    {
        $record = AgentIntegration::where('organization_id', $organizationId)->where('key', 'telegram')->first();

        if (! $record) {
            $this->error('Akses Telegram belum tersimpan.');

            return self::FAILURE;
        }

        $credentials = $record->secrets();
        $secret      = $credentials['webhook_secret'] ?? Str::random(40);

        if (empty($credentials['webhook_secret'])) {
            $credentials['webhook_secret'] = $secret;
            $record->setSecrets($credentials);
            $record->save();
        }

        $url = $url !== ''
            ? rtrim($url, '/')
            : route('agent.telegram.webhook', ['organization' => $organizationId, 'secret' => $secret]);

        if (! $telegram->setWebhook($organizationId, $url, $secret)) {
            $this->error('Telegram menolak pendaftaran webhook.');

            return self::FAILURE;
        }

        $this->info('Webhook aktif: ' . $url);

        return self::SUCCESS;
    }
}
