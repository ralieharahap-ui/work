<?php

namespace Tests\Feature\Agent;

use App\Agent\Runtime\AgentTaskService;
use App\Models\AgentEvent;
use App\Models\AgentTask;

/**
 * Pemahaman instruksi bahasa bebas — terutama dari chat, tempat pengguna
 * menuliskan nama berkas dan alamat email di dalam kalimat, bukan di formulir.
 */
class AgentUnderstandingTest extends AgentTestCase
{
    public function test_nama_berkas_pada_kalimat_dipakai_sebagai_sumber_data(): void
    {
        $task = $this->runToCompletion(app(AgentTaskService::class)->create(
            $this->user,
            'Buat laporan penjualan bulan Agustus 2026 dari berkas penjualan-2026-08.csv',
            [],
            'telegram',
        ));

        $this->assertSame(AgentTask::COMPLETED, $task->status);
        $this->assertSame('penjualan-2026-08.csv', $task->context['dataset']);

        $read = $task->steps()->where('tool', 'spreadsheet.read')->firstOrFail();
        $this->assertSame('penjualan-2026-08.csv', $read->inputs['dataset']);

        // Angkanya benar-benar berasal dari berkas Agustus, bukan bulan lain.
        $analyze = $task->steps()->where('tool', 'data.analyze')->firstOrFail();
        $this->assertEquals(200000000, $analyze->output['totals']['revenue']);
    }

    public function test_alamat_email_pada_kalimat_dipakai_sebagai_penerima(): void
    {
        $task = app(AgentTaskService::class)->create(
            $this->user,
            'Susun draf email ke pengadaan@pelanggan.test berisi ringkasan pengiriman minggu ini',
            [],
            'telegram',
        );

        $task = $this->runToCompletion($task);

        $this->assertSame(['pengadaan@pelanggan.test'], $task->context['email_to']);

        $draft = $task->steps()->where('tool', 'email.draft')->firstOrFail();
        $this->assertSame(['pengadaan@pelanggan.test'], $draft->output['to']);
    }

    public function test_konteks_yang_diisi_pengguna_tidak_ditimpa_hasil_pembacaan_kalimat(): void
    {
        $task = $this->runToCompletion(app(AgentTaskService::class)->create(
            $this->user,
            'Buat laporan penjualan dari berkas penjualan-2026-08.csv',
            ['context' => ['dataset' => 'penjualan-2026-07.csv']],
        ));

        $this->assertSame('penjualan-2026-07.csv', $task->context['dataset']);
    }

    public function test_tanpa_berkas_sumber_agent_berhenti_dan_bertanya_alih_alih_menebak(): void
    {
        $task = $this->runToCompletion(app(AgentTaskService::class)->create(
            $this->user, 'Buat laporan penjualan bulan ini', [], 'telegram',
        ));

        // Menebak berkas berarti melaporkan periode yang keliru — lebih baik berhenti.
        $this->assertSame(AgentTask::PAUSED, $task->status);
        $this->assertStringContainsString('Berkas sumber belum ditentukan', (string) $task->failure_reason);

        $event = AgentEvent::where('task_id', $task->id)
            ->where('type', 'HUMAN_REVIEW_REQUESTED')->firstOrFail();

        $this->assertStringContainsString('penjualan-2026-07.csv', (string) $event->message);
    }
}
