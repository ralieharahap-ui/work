<?php

namespace App\Agent\Runtime;

use App\Agent\Channels\Telegram\TelegramClient;
use App\Agent\Events\EventRecorder;
use App\Agent\Events\EventType;
use App\Models\AgentConversation;
use App\Models\AgentTask;
use App\Models\AgentTaskStep;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Menyampaikan kabar kepada pemilik pekerjaan lewat kanal chat yang tertaut.
 * Bila belum ada kanal, kabar tetap tercatat sebagai peristiwa sehingga
 * terlihat di dasbor.
 */
class AgentNotifier
{
    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly EventRecorder $events,
    ) {
    }

    public function taskFinished(AgentTask $task): void
    {
        $status = match ($task->status) {
            AgentTask::COMPLETED => '✅ Selesai',
            AgentTask::FAILED    => '⚠️ Belum tuntas',
            AgentTask::CANCELLED => '🚫 Dibatalkan',
            default              => 'ℹ️ Diperbarui',
        };

        $lines = [
            "{$status}: {$task->title}",
            '',
            Str::limit((string) $task->final_output, 1500),
        ];

        if ($task->deliverables) {
            $lines[] = '';
            $lines[] = '📎 Berkas: ' . implode(', ', array_column($task->deliverables, 'name'));
        }

        $lines[] = '';
        $lines[] = 'Keyakinan hasil: ' . round(($task->confidence['overall'] ?? 0) * 100) . '%';
        $lines[] = 'Rincian: /status ' . $this->shortId($task);

        $this->send($task, implode("\n", $lines));
    }

    public function approvalNeeded(AgentTask $task, AgentTaskStep $step, string $reason): void
    {
        $this->send($task, implode("\n", [
            '🔐 Butuh persetujuan Anda',
            '',
            'Pekerjaan: ' . $task->title,
            'Tindakan: ' . $step->objective,
            'Tool: ' . $step->tool,
            'Alasan: ' . $reason,
            '',
            'Balas /setujui ' . $this->shortId($task) . ' untuk melanjutkan,',
            'atau /tolak ' . $this->shortId($task) . ' untuk membatalkan tindakan ini.',
        ]));
    }

    /** @param array<string, mixed>|null $definition */
    public function accessNeeded(AgentTask $task, string $integration, ?array $definition): void
    {
        $lines = [
            '🔑 Saya butuh akses tambahan',
            '',
            'Pekerjaan: ' . $task->title,
            'Akses: ' . ($definition['label'] ?? $integration),
            'Kegunaan: ' . ($definition['purpose'] ?? 'melanjutkan langkah yang tertahan'),
            '',
            'Cara memberikannya:',
        ];

        foreach ((array) ($definition['guidance'] ?? []) as $i => $step) {
            $lines[] = ($i + 1) . '. ' . $step;
        }

        $lines[] = '';
        $lines[] = 'Buka menu Asisten AI → Akses Tools pada aplikasi, lalu kirim /lanjut ' . $this->shortId($task) . '.';

        $this->send($task, implode("\n", $lines));
    }

    public function humanReviewNeeded(AgentTask $task, string $reason): void
    {
        $this->send($task, implode("\n", [
            '🙋 Saya berhenti dan butuh pemeriksaan Anda',
            '',
            'Pekerjaan: ' . $task->title,
            'Alasan: ' . $reason,
            '',
            'Setelah diperiksa, kirim /lanjut ' . $this->shortId($task) . ' untuk melanjutkan,',
            'atau /batal ' . $this->shortId($task) . ' untuk menghentikannya.',
        ]));
    }

    public function message(User $user, string $text): bool
    {
        $conversation = $this->conversationFor($user);

        if (! $conversation) {
            return false;
        }

        return $this->telegram->sendMessage($user->organization_id, $conversation->chat_id, $text);
    }

    private function send(AgentTask $task, string $text): void
    {
        $conversation = $task->user ? $this->conversationFor($task->user) : null;

        if (! $conversation) {
            return;
        }

        $delivered = $this->telegram->sendMessage($task->organization_id, $conversation->chat_id, $text);

        if ($delivered) {
            $this->events->record($task, EventType::MESSAGE_SENT, 'Kabar dikirim ke Telegram pemilik pekerjaan.', [
                'channel' => 'telegram',
            ]);
        }
    }

    private function conversationFor(User $user): ?AgentConversation
    {
        return AgentConversation::where('user_id', $user->id)
            ->where('channel', 'telegram')
            ->where('is_linked', true)
            ->latest('last_message_at')
            ->first();
    }

    /** Delapan huruf pertama UUID sudah cukup unik untuk dipakai di chat. */
    private function shortId(AgentTask $task): string
    {
        return substr((string) $task->id, 0, 8);
    }
}
