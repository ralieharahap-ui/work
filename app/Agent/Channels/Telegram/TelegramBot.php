<?php

namespace App\Agent\Channels\Telegram;

use App\Agent\Events\EventRecorder;
use App\Agent\Events\EventType;
use App\Agent\Integrations\IntegrationManager;
use App\Agent\Runtime\AgentRuntime;
use App\Agent\Runtime\AgentTaskService;
use App\Agent\Runtime\ApprovalService;
use App\Models\AgentApproval;
use App\Models\AgentConversation;
use App\Models\AgentTask;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Chatbot Telegram: pintu percakapan menuju agent.
 *
 * Perintah hanya dilayani untuk chat yang sudah ditautkan ke akun aplikasi.
 * Chat asing hanya menerima petunjuk cara menautkan diri — sehingga tidak ada
 * orang luar yang bisa menyuruh agent bekerja atas nama karyawan.
 */
class TelegramBot
{
    public const LINK_CACHE_PREFIX = 'agent:telegram:link:';

    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly AgentTaskService $tasks,
        private readonly AgentRuntime $runtime,
        private readonly ApprovalService $approvals,
        private readonly IntegrationManager $integrations,
        private readonly EventRecorder $events,
    ) {
    }

    /** Membuat kode penautan sekali pakai untuk seorang pengguna. */
    public function issueLinkCode(User $user): string
    {
        $code = Str::upper(Str::random(4) . '-' . Str::random(4));

        Cache::put(
            self::LINK_CACHE_PREFIX . $code,
            $user->id,
            now()->addMinutes((int) config('agent.telegram.link_ttl', 30)),
        );

        return $code;
    }

    /** @param array<string, mixed> $update */
    public function handle(array $update, string $organizationId): void
    {
        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if (! is_array($message) || ! isset($message['chat']['id'])) {
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));

        if ($text === '') {
            return;
        }

        $conversation = $this->conversation($message, $organizationId);

        $conversation->fill([
            'last_message_at' => now(),
            'display_name'    => trim(($message['from']['first_name'] ?? '') . ' ' . ($message['from']['last_name'] ?? '')) ?: null,
            'username'        => $message['from']['username'] ?? null,
        ])->save();

        [$command, $argument] = $this->parse($text);

        // Penautan akun & sapaan boleh dilakukan sebelum chat dikenali.
        if (in_array($command, ['/tautkan', '/link'], true)) {
            $this->reply($conversation, $this->linkAccount($conversation, $argument));

            return;
        }

        if (in_array($command, ['/start', '/mulai'], true)) {
            $this->reply($conversation, $this->welcome($conversation));

            return;
        }

        if (! $conversation->is_linked || ! $conversation->user) {
            $this->reply($conversation, $this->linkInstructions());

            return;
        }

        $this->events->record(null, EventType::MESSAGE_RECEIVED, 'Pesan Telegram diterima.', [
            'command' => $command ?: 'teks-bebas',
        ], null, $conversation->user_id, $conversation->organization_id);

        $reply = match ($command) {
            '/bantuan', '/help' => $this->help(),
            '/akses'            => $this->integrations->onboardingScript((string) $conversation->organization_id),
            '/tugas'            => $this->listTasks($conversation),
            '/status'           => $this->taskStatus($conversation, $argument),
            '/setujui'          => $this->decideApproval($conversation, $argument, true),
            '/tolak'            => $this->decideApproval($conversation, $argument, false),
            '/batal'            => $this->cancelTask($conversation, $argument),
            '/jeda'             => $this->pauseTask($conversation, $argument),
            '/lanjut'           => $this->resumeTask($conversation, $argument),
            default             => $this->createTask($conversation, $text, (string) ($message['message_id'] ?? '')),
        };

        $this->reply($conversation, $reply);
    }

    // ── Penautan akun ─────────────────────────────────────────────────────

    private function linkAccount(AgentConversation $conversation, string $code): string
    {
        $code = Str::upper(trim($code));

        if ($code === '') {
            return "Kirim kode penautan Anda, contoh:\n/tautkan ABCD-1234\n\n" . $this->linkInstructions();
        }

        $userId = Cache::get(self::LINK_CACHE_PREFIX . $code);

        if (! $userId) {
            return 'Kode tidak dikenali atau sudah kedaluwarsa. Ambil kode baru dari menu Asisten AI di aplikasi.';
        }

        $user = User::find($userId);

        if (! $user || ! $user->is_active) {
            return 'Akun tidak aktif. Hubungi administrator aplikasi.';
        }

        Cache::forget(self::LINK_CACHE_PREFIX . $code);

        $conversation->fill([
            'user_id'         => $user->id,
            'organization_id' => $user->organization_id,
            'is_linked'       => true,
            'link_code'       => $code,
            'link_expires_at' => null,
        ])->save();

        $this->events->record(null, EventType::MESSAGE_RECEIVED,
            'Chat Telegram ditautkan ke akun ' . $user->name . '.',
            ['chat_id' => $conversation->chat_id], null, $user->id, $user->organization_id);

        return "Berhasil ditautkan dengan akun *{$user->name}*.\n\n"
            . "Sekarang Anda bisa langsung menuliskan pekerjaan, misalnya:\n"
            . "\"Buat laporan penjualan bulan ini dari berkas penjualan-2026-07.csv\"\n\n"
            . 'Ketik /bantuan untuk daftar perintah.';
    }

    private function linkInstructions(): string
    {
        return "Halo! Saya " . config('agent.name', 'Asisten Kantor') . ", asisten kantor digital.\n\n"
            . "Chat ini belum tertaut ke akun aplikasi, jadi saya belum boleh menerima pekerjaan.\n\n"
            . "Cara menautkan:\n"
            . "1. Buka aplikasi → menu Asisten AI.\n"
            . "2. Klik \"Hubungkan Telegram\" untuk memperoleh kode.\n"
            . "3. Kirim ke saya: /tautkan KODE-ANDA";
    }

    private function welcome(AgentConversation $conversation): string
    {
        if (! $conversation->is_linked) {
            return $this->linkInstructions();
        }

        $pending = $this->integrations->pending((string) $conversation->organization_id, essentialOnly: false);

        $lines = [
            'Halo ' . ($conversation->user?->name ?? 'rekan') . '! Saya siap membantu pekerjaan kantor Anda.',
            '',
            'Yang bisa saya kerjakan: menyusun laporan dari berkas data, merekonsiliasi dua sumber data,',
            'menyiapkan draf email, membuat agenda, merangkum tunggakan pekerjaan, dan menyusun dokumen.',
            '',
            'Tulis saja pekerjaannya dengan bahasa biasa. Saya akan merencanakan, mengerjakan, memeriksa hasilnya,',
            'dan meminta persetujuan Anda sebelum melakukan hal yang tidak bisa ditarik kembali.',
        ];

        if ($pending !== []) {
            $lines[] = '';
            $lines[] = 'Catatan: ' . count($pending) . ' akses masih perlu diberikan agar kemampuan saya lengkap.';
            $lines[] = 'Ketik /akses untuk rinciannya.';
        }

        $lines[] = '';
        $lines[] = 'Ketik /bantuan untuk daftar perintah.';

        return implode("\n", $lines);
    }

    private function help(): string
    {
        return implode("\n", [
            'Perintah yang tersedia:',
            '',
            '/tugas — daftar pekerjaan yang sedang berjalan',
            '/status <id> — rincian satu pekerjaan',
            '/setujui <id> — setujui tindakan yang menunggu persetujuan',
            '/tolak <id> — tolak tindakan tersebut',
            '/jeda <id> — hentikan sementara',
            '/lanjut <id> — lanjutkan kembali',
            '/batal <id> — batalkan pekerjaan',
            '/akses — akses tool yang saya butuhkan & cara memberikannya',
            '',
            'Selain itu, tuliskan saja pekerjaannya dengan bahasa biasa.',
        ]);
    }

    // ── Perintah pekerjaan ────────────────────────────────────────────────

    private function createTask(AgentConversation $conversation, string $text, string $messageId): string
    {
        $user = $conversation->user;

        if (! $user->can('agent.create')) {
            return 'Akun Anda belum diberi izin membuat pekerjaan untuk asisten. Hubungi administrator.';
        }

        $task = $this->tasks->create($user, $text, [
            'external_ref'    => 'telegram:' . $conversation->chat_id . ':' . $messageId,
            'idempotency_key' => 'telegram:' . $conversation->chat_id . ':' . $messageId,
        ], 'telegram');

        $this->tasks->run($task);
        $task->refresh();

        return implode("\n", array_filter([
            '📝 Diterima: ' . $task->title,
            'ID: ' . substr($task->id, 0, 8) . ' · jenis: ' . $task->task_type,
            '',
            $this->statusLine($task),
            'Ketik /status ' . substr($task->id, 0, 8) . ' untuk rinciannya.',
        ]));
    }

    private function listTasks(AgentConversation $conversation): string
    {
        $tasks = AgentTask::where('user_id', $conversation->user_id)
            ->latest()->limit(8)->get();

        if ($tasks->isEmpty()) {
            return 'Belum ada pekerjaan. Tuliskan saja apa yang perlu saya kerjakan.';
        }

        return "Pekerjaan terakhir:\n\n" . $tasks->map(fn (AgentTask $task) => sprintf(
            "%s %s\n   ID %s · %s",
            $this->statusIcon($task->status), Str::limit($task->title, 60),
            substr($task->id, 0, 8), $task->status,
        ))->implode("\n\n");
    }

    private function taskStatus(AgentConversation $conversation, string $argument): string
    {
        $task = $this->findTask($conversation, $argument);

        if (! $task) {
            return 'Pekerjaan dengan ID tersebut tidak ditemukan.';
        }

        $lines = [
            $this->statusIcon($task->status) . ' ' . $task->title,
            'Status: ' . $task->status . ' · jenis: ' . $task->task_type,
            '',
            $this->statusLine($task),
        ];

        foreach ($task->steps as $step) {
            $lines[] = sprintf('%s %s (%s)',
                match ($step->status) {
                    'succeeded' => '✔',
                    'failed'    => '✖',
                    'running'   => '⏳',
                    'waiting_approval' => '🔐',
                    default     => '·',
                },
                Str::limit($step->objective, 60),
                $step->tool ?? '-',
            );
        }

        if ($task->final_output) {
            $lines[] = '';
            $lines[] = Str::limit($task->final_output, 1200);
        }

        if ($task->status === AgentTask::WAITING_APPROVAL) {
            $lines[] = '';
            $lines[] = 'Menunggu keputusan Anda: /setujui ' . substr($task->id, 0, 8) . ' atau /tolak ' . substr($task->id, 0, 8);
        }

        return implode("\n", $lines);
    }

    private function decideApproval(AgentConversation $conversation, string $argument, bool $approved): string
    {
        $task = $this->findTask($conversation, $argument);

        if (! $task) {
            return 'Pekerjaan tidak ditemukan.';
        }

        $approval = AgentApproval::where('task_id', $task->id)->where('status', 'pending')->latest()->first();

        if (! $approval) {
            return 'Tidak ada tindakan yang menunggu persetujuan pada pekerjaan itu.';
        }

        if (! $conversation->user->can('agent.approve')) {
            return 'Akun Anda tidak memiliki izin menyetujui tindakan agent.';
        }

        $this->approvals->decide($approval, $approved, $conversation->user, 'telegram');

        if ($approved) {
            $this->tasks->run($task->refresh());

            return '✅ Disetujui. Saya lanjutkan pekerjaannya.';
        }

        $this->tasks->run($task->refresh());

        return '🚫 Ditolak. Tindakan itu saya lewati.';
    }

    private function cancelTask(AgentConversation $conversation, string $argument): string
    {
        $task = $this->findTask($conversation, $argument);

        if (! $task) {
            return 'Pekerjaan tidak ditemukan.';
        }

        $this->runtime->cancel($task, 'Dibatalkan lewat Telegram.');

        return '🚫 Pekerjaan "' . Str::limit($task->title, 50) . '" dibatalkan.';
    }

    private function pauseTask(AgentConversation $conversation, string $argument): string
    {
        $task = $this->findTask($conversation, $argument);

        if (! $task) {
            return 'Pekerjaan tidak ditemukan.';
        }

        $this->runtime->pause($task, 'Dijeda lewat Telegram.');

        return '⏸ Dijeda. Kirim /lanjut ' . substr($task->id, 0, 8) . ' bila ingin dilanjutkan.';
    }

    private function resumeTask(AgentConversation $conversation, string $argument): string
    {
        $task = $this->findTask($conversation, $argument);

        if (! $task) {
            return 'Pekerjaan tidak ditemukan.';
        }

        $this->runtime->resume($task);
        $this->tasks->run($task->refresh());

        return '▶️ Dilanjutkan.';
    }

    // ── Perkakas internal ─────────────────────────────────────────────────

    /** @param array<string, mixed> $message */
    private function conversation(array $message, string $organizationId): AgentConversation
    {
        return AgentConversation::firstOrCreate(
            ['channel' => 'telegram', 'chat_id' => (string) $message['chat']['id']],
            [
                'organization_id' => $organizationId,
                'chat_type'       => $message['chat']['type'] ?? null,
                'is_linked'       => false,
            ],
        );
    }

    /** @return array{0: string, 1: string} */
    private function parse(string $text): array
    {
        if (! str_starts_with($text, '/')) {
            return ['', $text];
        }

        $parts   = preg_split('/\s+/', $text, 2) ?: [$text];
        $command = Str::lower(explode('@', $parts[0])[0]); // dukung format /perintah@namabot

        return [$command, trim($parts[1] ?? '')];
    }

    private function findTask(AgentConversation $conversation, string $argument): ?AgentTask
    {
        $argument = trim($argument);

        $query = AgentTask::with('steps')
            ->where('organization_id', $conversation->organization_id)
            ->where('user_id', $conversation->user_id);

        if ($argument === '') {
            return $query->latest()->first();
        }

        return $query->where('id', 'like', $argument . '%')->latest()->first();
    }

    private function statusLine(AgentTask $task): string
    {
        return match ($task->status) {
            AgentTask::PENDING, AgentTask::PLANNING => '🧭 Sedang saya rencanakan…',
            AgentTask::EXECUTING => '⚙️ Sedang dikerjakan (' . $task->steps_executed . ' langkah berjalan).',
            AgentTask::WAITING_APPROVAL => '🔐 Menunggu persetujuan Anda.',
            AgentTask::VERIFYING => '🔍 Sedang saya periksa hasilnya.',
            AgentTask::PAUSED    => '⏸ Tertahan: ' . ($task->failure_reason ?: 'dijeda'),
            AgentTask::COMPLETED => '✅ Selesai (keyakinan ' . round(($task->confidence['overall'] ?? 0) * 100) . '%).',
            AgentTask::FAILED    => '⚠️ Belum tuntas: ' . Str::limit((string) $task->failure_reason, 200),
            AgentTask::CANCELLED => '🚫 Dibatalkan.',
            default              => '',
        };
    }

    private function statusIcon(string $status): string
    {
        return match ($status) {
            AgentTask::COMPLETED => '✅',
            AgentTask::FAILED    => '⚠️',
            AgentTask::CANCELLED => '🚫',
            AgentTask::WAITING_APPROVAL => '🔐',
            AgentTask::PAUSED    => '⏸',
            default              => '⚙️',
        };
    }

    private function reply(AgentConversation $conversation, string $text): void
    {
        $this->telegram->sendMessage(
            $conversation->organization_id,
            $conversation->chat_id,
            $text,
        );
    }
}
