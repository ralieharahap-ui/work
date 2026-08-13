<?php

namespace App\Agent\Execution;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolResult;
use App\Agent\Events\EventRecorder;
use App\Agent\Events\EventType;
use App\Agent\Memory\MemoryManager;
use App\Agent\Memory\Redactor;
use App\Agent\Tools\ToolRegistry;
use App\Models\AgentTask;
use App\Models\AgentTaskStep;
use App\Models\AgentToolExecution;
use InvalidArgumentException;
use Throwable;

/**
 * Menjalankan satu langkah rencana: validasi → eksekusi → observasi → catat.
 *
 * Idempotensi ditegakkan di sini. Pemanggilan dengan kunci yang sama tidak
 * pernah dieksekusi dua kali, sehingga percobaan ulang atau penyusunan rencana
 * ulang tidak menyebabkan email terkirim dua kali atau agenda ganda.
 */
class Executor
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly EventRecorder $events,
        private readonly MemoryManager $memory,
        private readonly Redactor $redactor,
    ) {
    }

    public function execute(AgentTask $task, AgentTaskStep $step, ToolContext $context): ToolResult
    {
        $toolName = (string) $step->tool;

        if (! $this->registry->has($toolName)) {
            return ToolResult::failure("Tool '{$toolName}' tidak tersedia.", 'not_found');
        }

        $tool   = $this->registry->get($toolName);
        $inputs = $this->resolveReferences($task, $step->inputs ?? []);

        try {
            $validated = $tool->validate($inputs);
        } catch (InvalidArgumentException $e) {
            return ToolResult::failure($e->getMessage(), 'validation');
        }

        $key      = $this->idempotencyKey($task, $step, $toolName, $validated);
        $previous = $this->previousExecution($key);

        if ($previous?->status === 'succeeded') {
            $this->events->record($task, EventType::TOOL_REPLAYED,
                "Hasil '{$toolName}' dipakai ulang tanpa memanggil ulang tool (idempotensi).",
                ['idempotency_key' => $key], $step->id);

            return ToolResult::success(
                (array) $previous->output,
                'Hasil sebelumnya dipakai ulang (tindakan tidak diulang).',
            );
        }

        $definition = $tool->describe();

        // Percobaan sebelumnya berhenti tanpa kabar (proses mati saat tool
        // sedang berjalan). Untuk tindakan yang tidak dapat ditarik kembali,
        // mengulang lebih berbahaya daripada berhenti — serahkan ke manusia.
        if ($previous?->status === 'running' && ! $definition->idempotent) {
            return ToolResult::failure(
                "Percobaan '{$toolName}' sebelumnya tidak diketahui hasilnya. "
                . 'Tindakan ini tidak dapat ditarik kembali, jadi tidak saya ulang tanpa pemeriksaan manusia.',
                'uncertain_execution',
                ['execution_id' => $previous->id],
            );
        }

        $this->events->record($task, EventType::TOOL_CALLED, "Menjalankan {$toolName}.", [
            'tool'   => $toolName,
            'inputs' => $this->redactor->forLogs($this->preview($validated)),
        ], $step->id);

        // Baris eksekusi dipesan lebih dulu supaya tindakan yang benar-benar
        // terjadi tetap tercatat walau proses berhenti di tengah jalan.
        $execution = AgentToolExecution::create([
            'organization_id' => $task->organization_id,
            'task_id'         => $task->id,
            'step_id'         => $step->id,
            'tool'            => $toolName,
            'task_type'       => $task->task_type,
            'input'           => $this->redactor->forLogs($this->preview($validated)),
            'status'          => 'running',
            'attempt'         => $step->attempts,
            'idempotency_key' => $key,
        ]);

        $startedAt = microtime(true);

        try {
            $result = $tool->execute($validated, $context);
        } catch (Throwable $e) {
            report($e);
            $result = ToolResult::failure('Tool gagal dijalankan: ' . $e->getMessage(), 'unknown');
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $execution->fill([
            'output'      => $this->redactor->forLogs($this->preview($result->ok ? $result->output : $result->data)),
            'status'      => $result->ok ? 'succeeded' : 'failed',
            'error'       => $result->error,
            'error_class' => $result->errorClass,
            'duration_ms' => $durationMs,
        ])->save();

        $this->memory->recordToolOutcome(
            $task->organization_id, $toolName, $task->task_type,
            $result->ok, $durationMs, $result->errorClass,
        );

        $this->events->record(
            $task,
            $result->ok ? EventType::TOOL_COMPLETED : EventType::TOOL_FAILED,
            $result->ok
                ? ($result->observation ?: "{$toolName} selesai.")
                : "{$toolName} gagal: " . $result->error,
            ['tool' => $toolName, 'duration_ms' => $durationMs, 'error_class' => $result->errorClass],
            $step->id,
        );

        return $result;
    }

    /**
     * Menukar rujukan antar-langkah (source_step, left_step, right_step)
     * dengan keluaran nyata langkah tersebut.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function resolveReferences(AgentTask $task, array $inputs): array
    {
        $map = ['source_step' => 'source', 'left_step' => 'left', 'right_step' => 'right'];

        foreach ($map as $reference => $target) {
            if (empty($inputs[$reference])) {
                continue;
            }

            $source = $task->steps()->where('step_key', $inputs[$reference])->first();
            unset($inputs[$reference]);

            if ($source && $source->output) {
                $inputs[$target] = $source->output;
            }
        }

        return $inputs;
    }

    /**
     * Kunci idempotensi sengaja TIDAK memuat nomor langkah: tindakan yang
     * identik (tool + input yang sama) di dalam satu pekerjaan hanya boleh
     * terjadi sekali, walaupun rencana disusun ulang dan langkahnya berganti
     * nomor. Percobaan ulang setelah gagal tetap berjalan karena hanya
     * eksekusi yang berstatus 'succeeded' yang dipakai ulang.
     *
     * @param  array<string, mixed>  $inputs
     */
    public function idempotencyKey(AgentTask $task, AgentTaskStep $step, string $tool, array $inputs): string
    {
        $canonical = $this->canonicalize($inputs);

        return hash('sha256', implode('|', [
            $task->id, $tool, json_encode($canonical, JSON_UNESCAPED_UNICODE) ?: '',
        ]));
    }

    /**
     * Catatan percobaan terakhir untuk kunci ini. Keberhasilan diutamakan:
     * bila tindakan pernah berhasil, ia tidak boleh terjadi dua kali.
     */
    private function previousExecution(string $key): ?AgentToolExecution
    {
        return AgentToolExecution::where('idempotency_key', $key)
            ->orderByRaw("CASE status WHEN 'succeeded' THEN 0 WHEN 'running' THEN 1 ELSE 2 END")
            ->latest()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function canonicalize(array $input): array
    {
        ksort($input);

        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = $this->canonicalize($value);
            }
        }

        return $input;
    }

    /**
     * Potongan ringkas untuk catatan audit — payload besar (ribuan baris data)
     * tidak perlu digandakan ke dalam log.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preview(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && count($value) > 5) {
                $data[$key] = [
                    '_ringkasan' => count($value) . ' entri',
                    'contoh'     => array_slice($value, 0, 3, true),
                ];
            } elseif (is_string($value) && mb_strlen($value) > 800) {
                $data[$key] = mb_substr($value, 0, 800) . '…';
            }
        }

        return $data;
    }
}
