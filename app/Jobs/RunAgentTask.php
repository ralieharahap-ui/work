<?php

namespace App\Jobs;

use App\Agent\Runtime\AgentRuntime;
use App\Models\AgentTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Menjalankan satu giliran kerja agent.
 *
 * Pekerjaan dieksekusi di luar siklus request supaya task panjang tidak
 * menggantung antarmuka. Bila antrean disetel 'sync', job berjalan langsung —
 * tetap benar, hanya tidak asinkron.
 */
class RunAgentTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;      // Percobaan ulang diatur runtime, bukan antrean.
    public int $timeout = 600;

    /** Batas rantai giliran dalam satu proses, penjaga dari loop tak berujung. */
    private const MAX_PASSES = 12;

    public function __construct(
        public readonly string $taskId,
        public readonly int $pass = 1,
    ) {
    }

    public function handle(AgentRuntime $runtime): void
    {
        $task = AgentTask::with('agent', 'user')->find($this->taskId);

        if (! $task || $task->isTerminal() || $task->status === AgentTask::PAUSED) {
            return;
        }

        $runtime->tick($task);

        // Bila masih ada pekerjaan tersisa (anggaran giliran habis), lanjutkan
        // pada giliran berikutnya alih-alih memaksakan satu proses panjang.
        $task->refresh();

        if ($task->isRunnable() && $this->pass < self::MAX_PASSES) {
            self::dispatch($this->taskId, $this->pass + 1)->delay(now()->addSeconds(2));
        }
    }
}
