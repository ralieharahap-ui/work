<?php

namespace Tests\Feature\Agent;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dasar pengujian agent: satu organisasi, satu pengguna, dan berkas data
 * contoh di ruang kerja sementara — sehingga tiap test berdiri sendiri.
 */
abstract class AgentTestCase extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(RolePermissionSeeder::class);

        $this->organization = Organization::create(['name' => 'PT Uji Coba', 'slug' => 'pt-uji']);

        $this->user = User::create([
            'name'            => 'Rina Pratiwi',
            'email'           => 'rina@pt-uji.test',
            'password'        => Hash::make('rahasia-uji'),
            'organization_id' => $this->organization->id,
            'hierarchy'       => 'administrator',
            'is_active'       => true,
        ]);

        $this->user->assignRole('super_admin');

        $this->dataset('penjualan-2026-07.csv', [
            'tanggal,produk,total_revenue,units_sold',
            '2026-07-01,Cangkang Sawit,125000000,500',
            '2026-07-05,Wood Pellet,76000000,190',
            '2026-07-14,Cangkang Sawit,142000000,568',
        ]);

        $this->dataset('penjualan-2026-08.csv', [
            'tanggal,produk,total_revenue,units_sold',
            '2026-08-02,Cangkang Sawit,118000000,472',
            '2026-08-07,Wood Pellet,82000000,205',
        ]);
    }

    /** @param array<int, string> $rows */
    protected function dataset(string $name, array $rows): void
    {
        Storage::disk('local')->put(
            trim((string) config('agent.workspace', 'agent'), '/') . '/datasets/' . $name,
            implode("\n", $rows),
        );
    }

    /** @param array<string, mixed> $context */
    protected function reportContext(string $dataset = 'penjualan-2026-07.csv', array $extra = []): array
    {
        return array_merge([
            'dataset'      => $dataset,
            'metrics'      => ['revenue', 'units_sold'],
            'group_by'     => 'produk',
            'report_title' => 'Laporan Penjualan',
        ], $extra);
    }

    /** Menjalankan agent sampai berhenti pada keadaan yang jelas. */
    protected function runToCompletion(\App\Models\AgentTask $task, int $maxTicks = 8): \App\Models\AgentTask
    {
        $runtime = app(\App\Agent\Runtime\AgentRuntime::class);

        for ($i = 0; $i < $maxTicks && $task->isRunnable(); $i++) {
            $runtime->tick($task);
            $task->refresh();
        }

        return $task;
    }
}
