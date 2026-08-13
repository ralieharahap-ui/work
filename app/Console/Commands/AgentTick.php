<?php

namespace App\Console\Commands;

use App\Agent\Integrations\IntegrationManager;
use App\Agent\Runtime\AgentRuntime;
use App\Models\AgentApproval;
use App\Models\AgentTask;
use Illuminate\Console\Command;

/**
 * Denyut kerja agent. Dijalankan penjadwal setiap menit agar pekerjaan panjang
 * tetap maju, persetujuan yang basi ditutup, dan pekerjaan yang tertahan karena
 * akses otomatis lanjut begitu aksesnya diberikan.
 */
class AgentTick extends Command
{
    protected $signature = 'agent:tick {--limit=10 : Jumlah pekerjaan yang diproses per denyut}';

    protected $description = 'Melanjutkan pekerjaan agent yang masih berjalan, tertahan, atau menunggu persetujuan.';

    public function handle(AgentRuntime $runtime, IntegrationManager $integrations): int
    {
        $expired = AgentApproval::where('status', 'pending')
            ->whereNotNull('expires_at')->where('expires_at', '<', now())
            ->update(['status' => 'expired', 'decided_at' => now()]);

        if ($expired > 0) {
            $this->line("{$expired} permintaan persetujuan kedaluwarsa ditutup.");
        }

        // Pekerjaan yang tertahan menunggu akses: dilanjutkan otomatis begitu
        // aksesnya tersedia, tanpa perlu diminta ulang oleh pengguna.
        foreach (AgentTask::where('status', AgentTask::PAUSED)->get() as $task) {
            $blockedOn = $task->working_memory['blocked_on'] ?? null;

            if (! $blockedOn) {
                continue;
            }

            // Akses pengganti juga membuka jalan (mis. SMTP menggantikan
            // Microsoft 365 untuk mengirim email).
            $candidates = array_merge([(string) $blockedOn],
                array_map('strval', (array) ($task->working_memory['blocked_alternatives'] ?? [])));

            foreach ($candidates as $candidate) {
                if ($integrations->isConnected((string) $task->organization_id, $candidate)) {
                    $runtime->resume($task);
                    $this->info("Melanjutkan '{$task->title}' — akses {$candidate} sudah tersedia.");
                    break;
                }
            }
        }

        $tasks = AgentTask::with('agent', 'user')
            ->whereIn('status', AgentTask::RUNNABLE)
            ->orderBy('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($tasks as $task) {
            $runtime->tick($task);
            $this->line("· {$task->title} → " . $task->refresh()->status);
        }

        if ($tasks->isEmpty()) {
            $this->line('Tidak ada pekerjaan yang perlu dilanjutkan.');
        }

        return self::SUCCESS;
    }
}
