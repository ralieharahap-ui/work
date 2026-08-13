<?php

namespace Tests\Feature\Agent;

use App\Agent\Channels\Telegram\TelegramBot;
use App\Agent\Integrations\IntegrationManager;
use App\Models\AgentConversation;
use App\Models\AgentIntegration;
use App\Models\AgentTask;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Chatbot Telegram: penautan akun, perintah, dan penjagaan webhook. */
class AgentTelegramTest extends AgentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.telegram.org/*getMe*'      => Http::response(['ok' => true, 'result' => ['username' => 'uji_bot', 'first_name' => 'Uji', 'id' => 42]]),
            'api.telegram.org/*sendMessage*'=> Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
            'api.telegram.org/*'            => Http::response(['ok' => true, 'result' => []]),
        ]);

        app(IntegrationManager::class)->connect(
            $this->organization->id, 'telegram', ['bot_token' => '123456:token-uji'], $this->user,
        );
    }

    public function test_chat_yang_belum_tertaut_hanya_menerima_petunjuk_penautan(): void
    {
        app(TelegramBot::class)->handle($this->message('Buat laporan penjualan Juli'), $this->organization->id);

        $this->assertSame(0, AgentTask::count(), 'Chat asing tidak boleh bisa menyuruh agent bekerja.');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) $request['text'], '/tautkan'));
    }

    public function test_kode_penautan_mengikat_chat_ke_akun_lalu_perintah_dilayani(): void
    {
        $bot  = app(TelegramBot::class);
        $code = $bot->issueLinkCode($this->user);

        $bot->handle($this->message('/tautkan ' . $code), $this->organization->id);

        $conversation = AgentConversation::where('chat_id', '900001')->firstOrFail();
        $this->assertTrue($conversation->is_linked);
        $this->assertSame($this->user->id, $conversation->user_id);

        // Kode sekali pakai: percobaan kedua ditolak.
        $bot->handle($this->message('/tautkan ' . $code, chatId: '900002'), $this->organization->id);
        $this->assertFalse(AgentConversation::where('chat_id', '900002')->firstOrFail()->is_linked);

        // Setelah tertaut, teks bebas menjadi pekerjaan yang benar-benar dikerjakan.
        $bot->handle($this->message('Buat laporan penjualan Juli 2026 dari penjualan-2026-07.csv'), $this->organization->id);

        $task = AgentTask::firstOrFail();
        $this->assertSame($this->user->id, $task->user_id);
        $this->assertSame('telegram', $task->source);
        $this->assertSame('report_generation', $task->task_type);
    }

    public function test_pesan_yang_sama_tidak_membuat_pekerjaan_ganda(): void
    {
        $bot = app(TelegramBot::class);
        $bot->handle($this->message('/tautkan ' . $bot->issueLinkCode($this->user)), $this->organization->id);

        $bot->handle($this->message('Rangkum tugas yang belum selesai', messageId: '555'), $this->organization->id);
        $bot->handle($this->message('Rangkum tugas yang belum selesai', messageId: '555'), $this->organization->id);

        $this->assertSame(1, AgentTask::count());
    }

    public function test_webhook_menolak_rahasia_yang_salah(): void
    {
        $record = AgentIntegration::where('key', 'telegram')->firstOrFail();
        $secrets = $record->secrets();
        $secrets['webhook_secret'] = 'rahasia-benar';
        $record->setSecrets($secrets);
        $record->save();

        $payload = $this->message('/bantuan');

        $this->postJson("/agent/telegram/webhook/{$this->organization->id}/rahasia-salah", $payload)
            ->assertNotFound();

        $this->postJson("/agent/telegram/webhook/{$this->organization->id}/rahasia-benar", $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);

        // Header rahasia yang tidak cocok juga ditolak.
        $this->postJson(
            "/agent/telegram/webhook/{$this->organization->id}/rahasia-benar",
            $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => 'palsu'],
        )->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function message(string $text, string $chatId = '900001', string $messageId = '1'): array
    {
        return [
            'update_id' => (int) $messageId,
            'message'   => [
                'message_id' => (int) $messageId,
                'chat'       => ['id' => $chatId, 'type' => 'private'],
                'from'       => ['id' => 777, 'first_name' => 'Rina', 'username' => 'rina'],
                'text'       => $text,
            ],
        ];
    }
}
