<?php

namespace Database\Seeders;

use App\Agent\Integrations\IntegrationCatalog;
use App\Models\Agent;
use App\Models\AgentIntegration;
use App\Models\AgentMemory;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Menyiapkan agent bawaan tiap organisasi, catatan akses yang perlu diminta,
 * pengetahuan awal (memori semantik), dan berkas data contoh untuk demo.
 *
 * Sengaja tidak menanam pengalaman/prosedur palsu: seluruh memori episodik dan
 * prosedural harus benar-benar lahir dari pekerjaan yang pernah dijalankan.
 */
class AgentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Organization::all() as $organization) {
            Agent::firstOrCreate(
                ['organization_id' => $organization->id, 'slug' => 'asisten-kantor'],
                [
                    'name'     => (string) config('agent.name', 'Asisten Kantor'),
                    'role'     => 'personal_office_employee',
                    'persona'  => 'Asisten kantor yang teliti: merencanakan sebelum bertindak, memverifikasi hasil '
                        . 'sebelum menyatakan selesai, dan meminta persetujuan untuk tindakan yang tidak dapat ditarik kembali.',
                    'autonomy' => (string) config('agent.policy.autonomy', 'balanced'),
                ],
            );

            foreach (IntegrationCatalog::all() as $key => $definition) {
                AgentIntegration::firstOrCreate(
                    ['organization_id' => $organization->id, 'key' => $key],
                    ['status' => 'not_configured', 'scopes' => $definition['scopes'] ?? []],
                );
            }

            foreach ($this->knowledge() as $subject => $content) {
                AgentMemory::firstOrCreate(
                    ['organization_id' => $organization->id, 'subject' => $subject],
                    ['kind' => 'semantic', 'content' => $content, 'confidence' => 0.7, 'source' => 'seed'],
                );
            }
        }

        $this->sampleDatasets();
    }

    /** @return array<string, string> */
    private function knowledge(): array
    {
        return [
            'Laporan penjualan' => 'Laporan penjualan lazimnya memuat pendapatan (revenue), jumlah unit terjual, '
                . 'margin, dan pertumbuhan dibanding periode sebelumnya, dirinci per produk atau per pelanggan.',
            'Rekonsiliasi data' => 'Rekonsiliasi menyejajarkan dua sumber data pada kolom kunci, lalu mencatat selisih '
                . 'nilai serta baris yang hanya ada di salah satu sumber.',
            'Etika kerja asisten' => 'Tindakan yang keluar dari aplikasi (kirim email, buat agenda, kirim WhatsApp) '
                . 'selalu meminta persetujuan manusia lebih dulu, dan tidak pernah diulang dua kali untuk permintaan yang sama.',
            'Dokumen bukti tugas' => 'Penutupan tugas pada aplikasi ini membutuhkan dokumen bukti yang ditandatangani PIC '
                . 'dan tersimpan sebagai PDF.',
        ];
    }

    /**
     * Berkas contoh untuk demo & pengujian. Kolom pendapatan sengaja bernama
     * "total_revenue" — inilah yang memaksa agent belajar memetakan nama kolom.
     */
    private function sampleDatasets(): void
    {
        $base = trim((string) config('agent.workspace', 'agent'), '/') . '/datasets';

        $files = [
            'penjualan-2026-07.csv' => implode("\n", [
                'tanggal,produk,total_revenue,units_sold,margin',
                '2026-07-01,Cangkang Sawit,125000000,500,18500000',
                '2026-07-05,Cangkang Sawit,98000000,392,14200000',
                '2026-07-09,Wood Pellet,76000000,190,11400000',
                '2026-07-14,Cangkang Sawit,142000000,568,21300000',
                '2026-07-18,Wood Pellet,64000000,160,9600000',
                '2026-07-23,Serbuk Gergaji,38000000,220,4900000',
                '2026-07-28,Cangkang Sawit,131500000,526,19725000',
                '2026-07-30,Wood Pellet,71000000,178,10650000',
            ]),
            'penjualan-2026-08.csv' => implode("\n", [
                'tanggal,produk,total_revenue,units_sold,margin',
                '2026-08-02,Cangkang Sawit,118000000,472,17700000',
                '2026-08-07,Wood Pellet,82000000,205,12300000',
                '2026-08-11,Cangkang Sawit,136000000,544,20400000',
                '2026-08-16,Serbuk Gergaji,41000000,238,5330000',
                '2026-08-21,Cangkang Sawit,127500000,510,19125000',
                '2026-08-27,Wood Pellet,69000000,172,10350000',
            ]),
            'piutang-sistem.csv' => implode("\n", [
                'id,pelanggan,nilai',
                'INV-001,PLTU Tenayan,125000000',
                'INV-002,PLTU Bangka,98000000',
                'INV-003,PLTU Nagan Raya,76000000',
                'INV-004,PLTU Tembilahan,142000000',
            ]),
            'piutang-bank.csv' => implode("\n", [
                'id,pelanggan,nilai',
                'INV-001,PLTU Tenayan,125000000',
                'INV-002,PLTU Bangka,95000000',
                'INV-003,PLTU Nagan Raya,76000000',
                'INV-005,PLTU Pangkalan Susu,54000000',
            ]),
        ];

        foreach ($files as $name => $contents) {
            if (! Storage::disk('local')->exists($base . '/' . $name)) {
                Storage::disk('local')->put($base . '/' . $name, $contents);
            }
        }
    }
}
