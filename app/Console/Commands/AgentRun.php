<?php

namespace App\Console\Commands;

use App\Agent\Runtime\AgentRuntime;
use App\Agent\Runtime\AgentTaskService;
use App\Models\AgentTask;
use App\Models\User;
use Illuminate\Console\Command;

/** Menjalankan satu pekerjaan agent dari baris perintah (uji coba & operasional). */
class AgentRun extends Command
{
    protected $signature = 'agent:run
        {objective? : Instruksi pekerjaan dalam bahasa alami}
        {--task= : Lanjutkan pekerjaan yang sudah ada (UUID)}
        {--user= : Email pemilik pekerjaan}
        {--context= : Konteks tambahan dalam format JSON}
        {--watch : Tampilkan jejak langkah setelah selesai}';

    protected $description = 'Menjalankan pekerjaan asisten AI dan menampilkan hasilnya.';

    public function handle(AgentTaskService $service, AgentRuntime $runtime): int
    {
        if ($taskId = $this->option('task')) {
            $task = AgentTask::with('agent', 'user')->find($taskId);

            if (! $task) {
                $this->error('Pekerjaan tidak ditemukan.');

                return self::FAILURE;
            }
        } else {
            $objective = (string) ($this->argument('objective') ?: $this->ask('Apa yang perlu saya kerjakan?'));

            $user = $this->option('user')
                ? User::where('email', $this->option('user'))->first()
                : User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

            if (! $user) {
                $this->error('Pengguna pemilik pekerjaan tidak ditemukan (gunakan --user=email).');

                return self::FAILURE;
            }

            $context = json_decode((string) $this->option('context'), true);

            $task = $service->create($user, $objective, [
                'context' => is_array($context) ? $context : [],
            ], 'cli');
        }

        $this->line("Pekerjaan: <info>{$task->title}</info> ({$task->id})");

        $guard = 0;

        do {
            $runtime->tick($task);
            $task->refresh();
        } while ($task->isRunnable() && ++$guard < 10);

        $this->newLine();
        $this->line('Status  : <info>' . $task->status . '</info>');
        $this->line('Jenis   : ' . $task->task_type);
        $this->line('Keyakinan: ' . round(($task->confidence['overall'] ?? $task->confidence['planning'] ?? 0) * 100) . '%');

        if ($task->final_output) {
            $this->newLine();
            $this->line($task->final_output);
        }

        if ($task->failure_reason) {
            $this->warn($task->failure_reason);
        }

        if ($this->option('watch')) {
            $this->newLine();
            $this->table(
                ['Langkah', 'Tool', 'Status', 'Catatan'],
                $task->steps()->get()->map(fn ($step) => [
                    $step->step_key, $step->tool, $step->status,
                    \Illuminate\Support\Str::limit((string) ($step->observation ?: $step->error), 60),
                ])->all(),
            );
        }

        return in_array($task->status, [AgentTask::FAILED, AgentTask::CANCELLED], true) ? self::FAILURE : self::SUCCESS;
    }
}
