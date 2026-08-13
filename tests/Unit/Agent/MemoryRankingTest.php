<?php

namespace Tests\Unit\Agent;

use App\Agent\Memory\ExperienceScorer;
use App\Agent\Memory\HashingEmbedder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Pemeringkatan memori: mirip, terbukti, yakin, dan masih baru. */
class MemoryRankingTest extends TestCase
{
    public function test_vektor_bersifat_deterministik_dan_menangkap_kemiripan(): void
    {
        $embedder = new HashingEmbedder(128);

        $a = $embedder->embed('buat laporan penjualan bulanan');
        $b = $embedder->embed('buat laporan penjualan bulanan');
        $c = $embedder->embed('susun laporan penjualan bulan ini');
        $d = $embedder->embed('jadwalkan rapat dengan pemasok kapal tongkang');

        $this->assertSame($a, $b, 'Vektor untuk teks yang sama harus identik.');
        $this->assertGreaterThan(
            HashingEmbedder::cosine($a, $d),
            HashingEmbedder::cosine($a, $c),
            'Kalimat yang bertopik sama harus lebih mirip daripada yang berbeda topik.',
        );
    }

    public function test_pengalaman_yang_lebih_terbukti_menang(): void
    {
        $embedder = new HashingEmbedder(128);
        $scorer   = new ExperienceScorer();

        $query    = 'buat laporan penjualan bulanan';
        $vector   = $embedder->embed($query);
        $keywords = HashingEmbedder::tokenize($query);

        $andal = (object) [
            'embedding' => $embedder->embed('buat laporan penjualan bulanan'),
            'keywords'  => HashingEmbedder::tokenize('buat laporan penjualan bulanan'),
            'confidence' => 0.9, 'success_count' => 8, 'failure_count' => 0,
            'last_used_at' => Carbon::now(),
        ];

        $sering_gagal = (object) [
            'embedding' => $embedder->embed('buat laporan penjualan bulanan'),
            'keywords'  => HashingEmbedder::tokenize('buat laporan penjualan bulanan'),
            'confidence' => 0.2, 'success_count' => 1, 'failure_count' => 7,
            'last_used_at' => Carbon::now(),
        ];

        $usang = (object) [
            'embedding' => $embedder->embed('buat laporan penjualan bulanan'),
            'keywords'  => HashingEmbedder::tokenize('buat laporan penjualan bulanan'),
            'confidence' => 0.9, 'success_count' => 8, 'failure_count' => 0,
            'last_used_at' => Carbon::now()->subDays(400),
        ];

        $this->assertGreaterThan(
            $scorer->score($sering_gagal, $vector, $keywords)['score'],
            $scorer->score($andal, $vector, $keywords)['score'],
        );

        $this->assertGreaterThan(
            $scorer->score($usang, $vector, $keywords)['score'],
            $scorer->score($andal, $vector, $keywords)['score'],
            'Pengalaman yang sudah lama tidak dipakai harus kalah dari yang segar.',
        );
    }

    public function test_memori_usang_ditekan_prioritasnya_tanpa_dihapus(): void
    {
        $embedder = new HashingEmbedder(128);
        $scorer   = new ExperienceScorer();
        $query    = 'laporan penjualan';

        $dasar = [
            'embedding'    => $embedder->embed($query),
            'keywords'     => HashingEmbedder::tokenize($query),
            'confidence'   => 0.8,
            'success_count'=> 5,
            'failure_count'=> 0,
            'last_used_at' => Carbon::now(),
        ];

        $aktif = (object) ($dasar + ['is_obsolete' => false]);
        $usang = (object) ($dasar + ['is_obsolete' => true]);

        $skorAktif = $scorer->score($aktif, $embedder->embed($query), HashingEmbedder::tokenize($query))['score'];
        $skorUsang = $scorer->score($usang, $embedder->embed($query), HashingEmbedder::tokenize($query))['score'];

        $this->assertGreaterThan(0, $skorUsang);
        $this->assertLessThan($skorAktif * 0.5, $skorUsang);
    }
}
