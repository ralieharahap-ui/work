<?php

namespace Tests\Feature\Agent;

use App\Agent\Runtime\AgentTaskService;
use App\Models\AgentEvent;
use App\Models\AgentExperience;
use App\Models\AgentTask;
use App\Models\AgentToolExecution;
use Illuminate\Support\Facades\Storage;

/** Alur inti: TASK → PLAN → EXECUTE → VERIFY → REFLECT → LEARN. */
class AgentLoopTest extends AgentTestCase
{
    public function test_pekerjaan_direncanakan_dikerjakan_diperiksa_lalu_menghasilkan_pengalaman(): void
    {
        $service = app(AgentTaskService::class);

        $task = $service->create($this->user, 'Buat laporan penjualan bulan Juli 2026', [
            'context' => $this->reportContext(),
        ]);

        $this->assertSame(AgentTask::PENDING, $task->status);

        $task = $this->runToCompletion($task);

        $this->assertSame(AgentTask::COMPLETED, $task->status);
        $this->assertSame('report_generation', $task->task_type);

        // Rencana tersimpan sebagai data terstruktur, bukan teks bebas.
        $this->assertIsArray($task->plan['steps']);
        $this->assertGreaterThanOrEqual(3, count($task->plan['steps']));
        $this->assertSame(
            ['spreadsheet.read', 'data.analyze', 'document.create'],
            $task->steps()->pluck('tool')->all(),
        );

        // Berkas hasil benar-benar ada.
        $this->assertNotEmpty($task->deliverables);
        $this->assertTrue(Storage::disk('local')->exists($task->deliverables[0]['path']));

        // Pemeriksaan hasil dijalankan dan lulus.
        $this->assertTrue($task->verification['passed']);
        $this->assertGreaterThan(0.5, $task->confidence['overall']);

        // Refleksi menghasilkan pengalaman yang tersimpan.
        $experience = AgentExperience::where('task_id', $task->id)->first();
        $this->assertNotNull($experience);
        $this->assertSame('SUCCESS', $experience->outcome);
        $this->assertNotEmpty($experience->embedding);
        $this->assertContains('spreadsheet.read', $experience->tools);
    }

    public function test_setiap_tindakan_meninggalkan_jejak_audit_yang_dapat_ditelusuri(): void
    {
        $task = app(AgentTaskService::class)->create($this->user, 'Buat laporan penjualan Juli', [
            'context' => $this->reportContext(),
        ]);

        $task = $this->runToCompletion($task);

        $types = AgentEvent::where('task_id', $task->id)->pluck('type')->all();

        foreach ([
            'TASK_CREATED', 'TASK_UNDERSTOOD', 'MEMORY_RETRIEVED', 'TASK_PLANNED',
            'STEP_STARTED', 'TOOL_CALLED', 'TOOL_COMPLETED', 'VERIFICATION_PASSED',
            'VERIFICATION_STARTED', 'REFLECTION_CREATED', 'EXPERIENCE_CREATED', 'TASK_COMPLETED',
        ] as $expected) {
            $this->assertContains($expected, $types, "Peristiwa {$expected} tidak tercatat.");
        }

        // Tiap pemanggilan tool tercatat — termasuk percobaan yang gagal,
        // sehingga urutan kejadiannya dapat direkonstruksi utuh.
        $executions = AgentToolExecution::where('task_id', $task->id)->get();
        $this->assertGreaterThanOrEqual(3, $executions->count());

        $succeeded = $executions->where('status', 'succeeded')->pluck('tool')->unique()->values()->all();
        $this->assertEqualsCanonicalizing(
            ['spreadsheet.read', 'data.analyze', 'document.create'],
            $succeeded,
        );
        $this->assertTrue($executions->every(fn ($e) => $e->duration_ms >= 0 && $e->idempotency_key !== null));
    }

    public function test_verifikasi_menolak_hasil_yang_tidak_memenuhi_kriteria(): void
    {
        // Berkas kosong: langkah pertama gagal walau tool "berhasil dipanggil".
        $this->dataset('kosong.csv', ['tanggal,produk,total_revenue,units_sold']);

        $task = app(AgentTaskService::class)->create($this->user, 'Buat laporan penjualan dari berkas kosong', [
            'context' => $this->reportContext('kosong.csv'),
        ]);

        $task = $this->runToCompletion($task);

        $this->assertContains($task->status, [AgentTask::FAILED, AgentTask::PAUSED]);
        $this->assertNotEmpty($task->failure_reason);
    }

    public function test_pekerjaan_dapat_dijeda_dilanjutkan_dan_dibatalkan(): void
    {
        $runtime = app(\App\Agent\Runtime\AgentRuntime::class);
        $service = app(AgentTaskService::class);

        $task = $service->create($this->user, 'Buat laporan penjualan Juli', [
            'context' => $this->reportContext(),
        ]);

        $runtime->pause($task, 'diuji');
        $this->assertSame(AgentTask::PAUSED, $task->refresh()->status);

        // Selama dijeda, satu giliran kerja tidak mengubah apa pun.
        $runtime->tick($task);
        $this->assertSame(AgentTask::PAUSED, $task->refresh()->status);

        $runtime->resume($task);
        $task = $this->runToCompletion($task->refresh());
        $this->assertSame(AgentTask::COMPLETED, $task->status);

        $other = $service->create($this->user, 'Buat laporan penjualan Agustus', [
            'context' => $this->reportContext('penjualan-2026-08.csv'),
            'idempotency_key' => 'uji-batal',
        ]);
        $runtime->cancel($other, 'tidak jadi');

        $this->assertSame(AgentTask::CANCELLED, $other->refresh()->status);
        $this->assertSame('tidak jadi', $other->failure_reason);
    }
}
