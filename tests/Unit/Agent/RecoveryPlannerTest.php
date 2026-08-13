<?php

namespace Tests\Unit\Agent;

use App\Agent\Data\ToolResult;
use App\Agent\Execution\ErrorClassifier;
use App\Agent\Execution\RecoveryPlanner;
use App\Models\AgentTaskStep;
use Tests\TestCase;

/** Kegagalan diubah menjadi perbaikan yang spesifik, bukan pengulangan buta. */
class RecoveryPlannerTest extends TestCase
{
    private RecoveryPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = new RecoveryPlanner(new ErrorClassifier());
    }

    public function test_kolom_yang_hilang_dipetakan_ke_padanan_terdekat(): void
    {
        $step = new AgentTaskStep([
            'objective' => 'baca data',
            'tool'      => 'spreadsheet.read',
            'inputs'    => ['dataset' => 'penjualan-2026-07.csv', 'required_columns' => ['revenue']],
        ]);

        $result = ToolResult::failure('Kolom berikut tidak ada: revenue.', 'missing_column', [
            'dataset'           => 'penjualan-2026-07.csv',
            'missing_columns'   => ['revenue'],
            'available_columns' => ['tanggal', 'produk', 'total_revenue', 'units_sold'],
        ]);

        $recovery = $this->planner->plan($step, $result, 'missing_column');

        $this->assertSame('adjust_inputs', $recovery->strategy);
        $this->assertSame('total_revenue', $recovery->inputs['column_map']['revenue']);

        // Pelajarannya berlaku untuk keluarga berkas bulanan, bukan satu berkas.
        $this->assertSame('penjualan-*-*.csv', $recovery->lesson['payload']['dataset_pattern']);
        $this->assertSame('data', $recovery->lesson['scope']);
    }

    public function test_tanpa_padanan_kolom_agent_menyerahkan_ke_manusia(): void
    {
        $step = new AgentTaskStep(['objective' => 'baca data', 'tool' => 'spreadsheet.read', 'inputs' => []]);

        $recovery = $this->planner->plan($step, ToolResult::failure('Kolom tidak ada.', 'missing_column', [
            'missing_columns'   => ['revenue'],
            'available_columns' => ['nama_kapal', 'pelabuhan'],
        ]), 'missing_column');

        $this->assertSame('escalate', $recovery->strategy);
        $this->assertNull($recovery->lesson);
    }

    public function test_nama_berkas_keliru_diperbaiki_ke_berkas_yang_tersedia(): void
    {
        $step = new AgentTaskStep([
            'objective' => 'baca data', 'tool' => 'spreadsheet.read',
            'inputs'    => ['dataset' => 'penjualan-juli.csv'],
        ]);

        $recovery = $this->planner->plan($step, ToolResult::failure("Berkas tidak ditemukan.", 'not_found', [
            'available_datasets' => ['penjualan-2026-07.csv', 'piutang-bank.csv'],
        ]), 'not_found');

        $this->assertSame('adjust_inputs', $recovery->strategy);
        $this->assertSame('penjualan-2026-07.csv', $recovery->inputs['dataset']);
    }

    public function test_akses_yang_kurang_menghasilkan_permintaan_akses_beserta_alternatifnya(): void
    {
        $step = new AgentTaskStep(['objective' => 'kirim email', 'tool' => 'email.send', 'inputs' => []]);

        $recovery = $this->planner->plan($step, ToolResult::failure('Belum ada akses.', 'missing_integration', [
            'integration'  => 'microsoft365',
            'alternatives' => ['smtp_email'],
        ]), 'missing_integration');

        $this->assertSame('request_access', $recovery->strategy);
        $this->assertSame('microsoft365', $recovery->blockedOn);
        $this->assertSame(['smtp_email'], $recovery->alternatives);
    }

    public function test_tindakan_tak_idempoten_yang_nasibnya_tidak_diketahui_tidak_diulang(): void
    {
        $step = new AgentTaskStep(['objective' => 'kirim email', 'tool' => 'email.send', 'inputs' => []]);

        $recovery = $this->planner->plan(
            $step,
            ToolResult::failure('Hasil percobaan sebelumnya tidak diketahui.', 'uncertain_execution'),
            'uncertain_execution',
        );

        $this->assertSame('human_review', $recovery->strategy);
    }
}
