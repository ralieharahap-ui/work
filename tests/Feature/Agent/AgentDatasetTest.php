<?php

namespace Tests\Feature\Agent;

use App\Agent\Runtime\AgentTaskService;
use App\Models\AgentTask;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/** Berkas data: unggah, pemakaian oleh agent, dan batas keamanannya. */
class AgentDatasetTest extends AgentTestCase
{
    public function test_berkas_yang_diunggah_langsung_dapat_dipakai_agent(): void
    {
        $this->actingAs($this->user)
            ->post(route('agent.datasets.store'), [
                'file' => UploadedFile::fake()->createWithContent('stok-gudang.csv', implode("\n", [
                    'tanggal,produk,revenue,units_sold',
                    '2026-08-01,Cangkang Sawit,50000000,200',
                    '2026-08-02,Wood Pellet,25000000,60',
                ])),
            ])
            ->assertRedirect();

        Storage::disk('local')->assertExists('agent/datasets/stok-gudang.csv');

        $task = $this->runToCompletion(app(AgentTaskService::class)->create(
            $this->user, 'Buat laporan penjualan dari berkas stok-gudang.csv', [], 'telegram',
        ));

        $this->assertSame(AgentTask::COMPLETED, $task->status);
        $this->assertEquals(75000000, $task->steps()->where('tool', 'data.analyze')
            ->firstOrFail()->output['totals']['revenue']);
    }

    public function test_nama_berkas_dibersihkan_sehingga_tidak_bisa_menembus_folder_lain(): void
    {
        $this->actingAs($this->user)
            ->post(route('agent.datasets.store'), [
                'file' => UploadedFile::fake()->createWithContent('../../rahasia .csv', "a,b\n1,2"),
            ])
            ->assertRedirect();

        // Berkas mendarat di dalam folder data dengan nama yang sudah dijinakkan.
        $this->assertFalse(Storage::disk('local')->exists('rahasia.csv'));
        $this->assertNotEmpty(collect(Storage::disk('local')->files('agent/datasets'))
            ->filter(fn (string $path) => str_contains($path, 'rahasia'))->all());
    }

    public function test_berkas_selain_data_ditolak(): void
    {
        $this->actingAs($this->user)
            ->post(route('agent.datasets.store'), ['file' => UploadedFile::fake()->create('gambar.png', 10)])
            ->assertSessionHasErrors('file');
    }

    public function test_penghapusan_berkas_hanya_untuk_pengelola(): void
    {
        $staf = User::create([
            'name' => 'Staf', 'email' => 'staf-data@pt-uji.test',
            'password' => Hash::make('rahasia'), 'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $staf->assignRole('drafter');

        $this->actingAs($staf)
            ->delete(route('agent.datasets.destroy', 'penjualan-2026-07.csv'))
            ->assertForbidden();

        Storage::disk('local')->assertExists('agent/datasets/penjualan-2026-07.csv');

        $this->actingAs($this->user)
            ->delete(route('agent.datasets.destroy', 'penjualan-2026-07.csv'))
            ->assertRedirect();

        Storage::disk('local')->assertMissing('agent/datasets/penjualan-2026-07.csv');
    }

    public function test_daftar_berkas_tampil_di_dasbor(): void
    {
        $props = $this->actingAs($this->user)->get(route('agent.index'))->viewData('page')['props'];

        $this->assertContains('penjualan-2026-08.csv', array_column($props['datasets'], 'name'));
    }
}
