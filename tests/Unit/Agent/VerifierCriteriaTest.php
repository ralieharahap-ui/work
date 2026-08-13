<?php

namespace Tests\Unit\Agent;

use App\Agent\Verification\Verifier;
use App\Models\AgentTaskStep;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Kriteria keberhasilan diuji terhadap bukti, bukan klaim. */
class VerifierCriteriaTest extends TestCase
{
    public function test_kriteria_isi_keluaran_diperiksa_satu_per_satu(): void
    {
        $verifier = new Verifier();

        $step = new AgentTaskStep([
            'step_key'         => 'step_1',
            'objective'        => 'uji',
            'output'           => ['rows' => [['a' => 1], ['a' => 2]], 'row_count' => 2, 'totals' => ['revenue' => 100]],
            'success_criteria' => ['output_not_empty', 'has:rows', 'min_rows:2', 'numeric:row_count', 'contains:revenue'],
        ]);

        $verification = $verifier->verifyStep($step);

        $this->assertTrue($verification->passed);
        $this->assertSame(1.0, $verification->score);
        $this->assertCount(5, $verification->checks);
    }

    public function test_kriteria_yang_tidak_terpenuhi_menggagalkan_langkah(): void
    {
        $verifier = new Verifier();

        $step = new AgentTaskStep([
            'step_key'         => 'step_1',
            'objective'        => 'uji',
            'output'           => ['rows' => [], 'row_count' => 0],
            'success_criteria' => ['has:rows', 'min_rows:1'],
        ]);

        $verification = $verifier->verifyStep($step);

        $this->assertFalse($verification->passed);
        $this->assertSame(0.0, $verification->score);
        $this->assertCount(2, $verification->issues);
    }

    public function test_kriteria_berkas_menuntut_berkas_yang_benar_benar_ada(): void
    {
        Storage::fake('local');

        $verifier = new Verifier();

        $step = new AgentTaskStep([
            'step_key' => 'step_1', 'objective' => 'uji',
            'output'   => ['path' => 'agent/tasks/uji/dokumen.md'],
            'success_criteria' => ['has_file'],
        ]);

        $this->assertFalse($verifier->verifyStep($step)->passed);

        Storage::disk('local')->put('agent/tasks/uji/dokumen.md', '# Laporan');

        $this->assertTrue($verifier->verifyStep($step)->passed);
    }
}
