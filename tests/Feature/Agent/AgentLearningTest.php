<?php

namespace Tests\Feature\Agent;

use App\Agent\Runtime\AgentTaskService;
use App\Models\AgentEvent;
use App\Models\AgentLesson;
use App\Models\AgentProcedure;
use App\Models\AgentTask;

/**
 * Inti permintaan: setiap pekerjaan yang berhasil harus menjadi pengalaman
 * yang membuat pekerjaan berikutnya lebih baik — bukan sekadar selesai.
 */
class AgentLearningTest extends AgentTestCase
{
    public function test_kegagalan_kolom_dipulihkan_dan_menjadi_pelajaran(): void
    {
        $task = app(AgentTaskService::class)->create($this->user, 'Buat laporan penjualan Juli 2026', [
            'context' => $this->reportContext(),
        ]);

        $task = $this->runToCompletion($task);

        $this->assertSame(AgentTask::COMPLETED, $task->status);

        // Langkah pertama sempat gagal karena kolom 'revenue' tidak ada,
        // lalu dipulihkan dengan memetakan 'total_revenue'.
        $recovery = AgentEvent::where('task_id', $task->id)->where('type', 'RECOVERY_APPLIED')->first();
        $this->assertNotNull($recovery, 'Pemulihan kegagalan tidak tercatat.');
        $this->assertSame('missing_column', $recovery->payload['error_class']);
        $this->assertSame('adjust_inputs', $recovery->payload['strategy']);

        $lesson = AgentLesson::where('organization_id', $this->organization->id)->first();
        $this->assertNotNull($lesson, 'Pelajaran tidak tersimpan.');
        $this->assertSame('column_map', $lesson->payload['type']);
        $this->assertSame('total_revenue', $lesson->payload['column_map']['revenue']);
        // Pelajaran berlaku untuk keluarga berkas, bukan satu berkas saja.
        $this->assertSame('penjualan-*-*.csv', $lesson->payload['dataset_pattern']);
    }

    public function test_pekerjaan_kedua_memakai_pelajaran_dan_tidak_mengulang_kesalahan(): void
    {
        $service = app(AgentTaskService::class);

        $first = $this->runToCompletion($service->create($this->user, 'Buat laporan penjualan Juli 2026', [
            'context'         => $this->reportContext(),
            'idempotency_key' => 'uji-1',
        ]));

        $second = $this->runToCompletion($service->create($this->user, 'Buat laporan penjualan Agustus 2026', [
            'context'         => $this->reportContext('penjualan-2026-08.csv'),
            'idempotency_key' => 'uji-2',
        ]));

        $this->assertSame(AgentTask::COMPLETED, $second->status);

        // Pekerjaan #1 tersandung sekali; pekerjaan #2 tidak sama sekali.
        $this->assertSame(1, $this->retries($first));
        $this->assertSame(0, $this->retries($second), 'Pekerjaan kedua masih mengulang kesalahan yang sama.');

        // Pengalaman & pelajaran benar-benar dipakai saat merencanakan.
        $this->assertNotEmpty($second->experience_ids);
        $this->assertNotEmpty($second->lesson_ids);
        $this->assertContains('LESSON_APPLIED', AgentEvent::where('task_id', $second->id)->pluck('type')->all());

        // Pemetaan kolom sudah dipasang sejak langkah pertama disusun.
        $firstStep = $second->steps()->first();
        $this->assertSame('total_revenue', $firstStep->inputs['column_map']['revenue']);

        // Rencananya kini berasal dari prosedur yang terbukti, dan keyakinannya naik.
        $this->assertSame('procedure', $second->plan['origin']);
        $this->assertGreaterThan($first->plan['confidence'], $second->plan['confidence']);
    }

    public function test_prosedur_terbentuk_dari_rangkaian_langkah_yang_berhasil(): void
    {
        $service = app(AgentTaskService::class);

        $this->runToCompletion($service->create($this->user, 'Buat laporan penjualan Juli', [
            'context' => $this->reportContext(), 'idempotency_key' => 'uji-a',
        ]));

        $procedure = AgentProcedure::where('organization_id', $this->organization->id)->first();

        $this->assertNotNull($procedure);
        $this->assertSame('REPORT_GENERATION_V1', $procedure->label());
        $this->assertSame(
            ['spreadsheet.read', 'data.analyze', 'document.create'],
            array_column($procedure->steps, 'tool'),
        );

        $this->runToCompletion($service->create($this->user, 'Buat laporan penjualan Agustus', [
            'context' => $this->reportContext('penjualan-2026-08.csv'), 'idempotency_key' => 'uji-b',
        ]));

        $procedure->refresh();

        // Dipakai dua kali, dihitung sekali per pekerjaan (tidak ganda).
        $this->assertSame(2, $procedure->use_count);
        $this->assertSame(2, $procedure->success_count);
        $this->assertGreaterThan(0.5, $procedure->success_rate);
    }

    public function test_pengalaman_yang_terbukti_berguna_naik_keyakinannya(): void
    {
        $service = app(AgentTaskService::class);

        $first = $this->runToCompletion($service->create($this->user, 'Buat laporan penjualan Juli', [
            'context' => $this->reportContext(), 'idempotency_key' => 'uji-c',
        ]));

        $experience = \App\Models\AgentExperience::where('task_id', $first->id)->firstOrFail();
        $before     = $experience->confidence;

        $this->runToCompletion($service->create($this->user, 'Buat laporan penjualan Agustus', [
            'context' => $this->reportContext('penjualan-2026-08.csv'), 'idempotency_key' => 'uji-d',
        ]));

        $experience->refresh();

        $this->assertSame(1, $experience->use_count);
        $this->assertSame(1, $experience->success_count);
        $this->assertGreaterThan($before, $experience->confidence);
        $this->assertFalse($experience->is_obsolete);
    }

    private function retries(AgentTask $task): int
    {
        return max(0, (int) $task->steps()->sum('attempts') - $task->steps()->count());
    }
}
