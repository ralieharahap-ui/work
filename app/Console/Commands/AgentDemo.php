<?php

namespace App\Console\Commands;

use App\Agent\Runtime\AgentRuntime;
use App\Agent\Runtime\AgentTaskService;
use App\Models\AgentEvent;
use App\Models\AgentExperience;
use App\Models\AgentLesson;
use App\Models\AgentProcedure;
use App\Models\AgentTask;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Demo ujung ke ujung yang membuktikan agent benar-benar belajar.
 *
 * Pekerjaan #1 sengaja menabrak masalah nyata (kolom "revenue" tidak ada pada
 * berkas sumber). Agent mendiagnosis, memetakan kolom penggantinya, dan
 * menyimpan pelajarannya. Pekerjaan #2 pada berkas bulan berikutnya memakai
 * pelajaran itu sejak awal sehingga tidak lagi tersandung.
 */
class AgentDemo extends Command
{
    protected $signature = 'agent:demo
        {--user= : Email pemilik pekerjaan (bawaan: super admin pertama)}
        {--fresh : Kosongkan memori agent lebih dulu agar demo bermula dari nol}';

    protected $description = 'Menjalankan demo: pekerjaan kedua memanfaatkan pengalaman dari pekerjaan pertama.';

    public function handle(AgentTaskService $service, AgentRuntime $runtime): int
    {
        $user = $this->option('user')
            ? User::where('email', $this->option('user'))->first()
            : User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        if (! $user) {
            $this->error('Pengguna tidak ditemukan. Jalankan "php artisan db:seed" terlebih dahulu.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            AgentLesson::where('organization_id', $user->organization_id)->delete();
            AgentExperience::where('organization_id', $user->organization_id)->delete();
            AgentProcedure::where('organization_id', $user->organization_id)->delete();
            $this->warn('Memori agent dikosongkan — demo bermula dari nol pengalaman.');
        }

        $first = $this->runTask($service, $runtime, $user,
            'Buat laporan penjualan bulan Juli 2026 dari berkas penjualan-2026-07.csv',
            [
                'dataset'      => 'penjualan-2026-07.csv',
                'metrics'      => ['revenue', 'units_sold'],
                'group_by'     => 'produk',
                'report_title' => 'Laporan Penjualan Juli 2026',
            ],
            'PEKERJAAN #1 — belum punya pengalaman',
        );

        $second = $this->runTask($service, $runtime, $user,
            'Buat laporan penjualan bulan Agustus 2026 dari berkas penjualan-2026-08.csv',
            [
                'dataset'      => 'penjualan-2026-08.csv',
                'metrics'      => ['revenue', 'units_sold'],
                'group_by'     => 'produk',
                'report_title' => 'Laporan Penjualan Agustus 2026',
            ],
            'PEKERJAAN #2 — memakai pengalaman pekerjaan #1',
        );

        $this->comparison($first, $second);

        return $first->status === AgentTask::COMPLETED && $second->status === AgentTask::COMPLETED
            ? self::SUCCESS
            : self::FAILURE;
    }

    /** @param array<string, mixed> $context */
    private function runTask(
        AgentTaskService $service,
        AgentRuntime $runtime,
        User $user,
        string $objective,
        array $context,
        string $heading,
    ): AgentTask {
        $this->newLine();
        $this->line('══════════════════════════════════════════════════════════════');
        $this->line("  {$heading}");
        $this->line('══════════════════════════════════════════════════════════════');
        $this->line("Instruksi: {$objective}");

        $task = $service->create($user, $objective, [
            'context'         => $context,
            'idempotency_key' => 'demo:' . Str::uuid(),
        ], 'cli');

        $guard = 0;
        do {
            $runtime->tick($task);
            $task->refresh();
        } while ($task->isRunnable() && ++$guard < 10);

        $this->newLine();
        $this->line('Jejak keputusan:');

        foreach (AgentEvent::where('task_id', $task->id)->orderBy('created_at')->get() as $event) {
            $this->line(sprintf('  %-22s %s', $event->type, Str::limit((string) $event->message, 110)));
        }

        $this->newLine();
        $this->line('Status akhir : <info>' . $task->status . '</info>');
        $this->line('Keyakinan    : ' . round(($task->confidence['overall'] ?? 0) * 100) . '%');
        $this->line('Berkas hasil : ' . implode(', ', array_column($task->deliverables ?? [], 'name')) ?: '-');

        return $task;
    }

    private function comparison(AgentTask $first, AgentTask $second): void
    {
        $failures = fn (AgentTask $task) => $task->steps()->where('status', 'failed')->count();
        $retries  = fn (AgentTask $task) => max(0, (int) $task->steps()->sum('attempts') - $task->steps()->count());

        $this->newLine();
        $this->line('══════════════════════════════════════════════════════════════');
        $this->line('  PERBANDINGAN');
        $this->line('══════════════════════════════════════════════════════════════');

        $this->table(
            ['', 'Pekerjaan #1', 'Pekerjaan #2'],
            [
                ['Status',                $first->status,                         $second->status],
                ['Langkah gagal',         (string) $failures($first),             (string) $failures($second)],
                ['Percobaan ulang',       (string) $retries($first),              (string) $retries($second)],
                ['Rencana disusun ulang', (string) $first->replans,               (string) $second->replans],
                ['Pengalaman dipakai',    (string) count($first->experience_ids ?? []),  (string) count($second->experience_ids ?? [])],
                ['Pelajaran dipakai',     (string) count($first->lesson_ids ?? []),      (string) count($second->lesson_ids ?? [])],
                ['Asal rencana',          (string) ($first->plan['origin'] ?? '-'),      (string) ($second->plan['origin'] ?? '-')],
                ['Keyakinan rencana',     round((float) ($first->plan['confidence'] ?? 0) * 100) . '%',
                                          round((float) ($second->plan['confidence'] ?? 0) * 100) . '%'],
            ],
        );

        $lessons = AgentLesson::where('organization_id', $first->organization_id)->get();

        if ($lessons->isNotEmpty()) {
            $this->newLine();
            $this->line('Pelajaran yang tersimpan di memori:');

            foreach ($lessons as $lesson) {
                $this->line('  • [' . $lesson->scope . '/' . $lesson->subject . '] ' . $lesson->lesson);
                $this->line('    → ' . $lesson->recommendation
                    . ' (keyakinan ' . round($lesson->confidence * 100) . '%, dipakai ' . $lesson->use_count . '×)');
            }
        }

        $procedures = AgentProcedure::where('organization_id', $first->organization_id)->get();

        if ($procedures->isNotEmpty()) {
            $this->newLine();
            $this->line('Prosedur yang terbentuk:');

            foreach ($procedures as $procedure) {
                $this->line('  • ' . $procedure->label() . ' — ' . implode(' → ', array_column($procedure->steps, 'tool')));
                $this->line('    keberhasilan ' . round($procedure->success_rate * 100) . '%, dipakai ' . $procedure->use_count . '×');
            }
        }
    }
}
