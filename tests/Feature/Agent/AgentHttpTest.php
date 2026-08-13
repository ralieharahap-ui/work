<?php

namespace Tests\Feature\Agent;

use App\Agent\Integrations\IntegrationManager;
use App\Agent\Runtime\AgentTaskService;
use App\Models\AgentTask;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\AssertableJson;

/** Antarmuka web: dasbor, pembuatan pekerjaan, dan batas hak akses. */
class AgentHttpTest extends AgentTestCase
{
    public function test_dasbor_menampilkan_pekerjaan_memori_dan_kebutuhan_akses(): void
    {
        $task = $this->runToCompletion(app(AgentTaskService::class)->create(
            $this->user, 'Buat laporan penjualan Juli', ['context' => $this->reportContext()],
        ));

        $this->actingAs($this->user)
            ->get(route('agent.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableJson $page) => $page
                ->component('Agent/Index')
                ->where('task.id', $task->id)
                ->where('task.status', AgentTask::COMPLETED)
                ->has('task.steps', 3)
                ->has('task.events')
                ->has('memory.experiences', 1)
                ->has('integrations', 6)
                ->has('onboarding.script')
                ->etc());
    }

    public function test_kredensial_tidak_pernah_dikirim_ke_antarmuka(): void
    {
        app(IntegrationManager::class)->connect(
            $this->organization->id, 'smtp_email', ['from_address' => 'asisten@pt-uji.test'], $this->user,
        );

        $response = $this->actingAs($this->user)->get(route('agent.index'));

        $response->assertOk();
        $response->assertDontSee('from_address" : "asisten', escape: false);

        $integrations = collect($response->viewData('page')['props']['integrations']);
        $smtp = $integrations->firstWhere('key', 'smtp_email');

        $this->assertSame('connected', $smtp['status']);
        $this->assertArrayNotHasKey('credentials', $smtp);
        // Yang dikirim hanya penanda "sudah terisi", bukan nilainya.
        $this->assertTrue($smtp['fields'][0]['filled']);
    }

    public function test_pengguna_dapat_menugaskan_pekerjaan_lewat_web(): void
    {
        $this->actingAs($this->user)
            ->post(route('agent.tasks.store'), [
                'objective' => 'Rangkum tugas yang belum selesai minggu ini',
                'priority'  => 'High',
            ])
            ->assertRedirect();

        $task = AgentTask::firstOrFail();

        $this->assertSame('web', $task->source);
        $this->assertSame('High', $task->priority);
        $this->assertSame($this->user->id, $task->user_id);
    }

    public function test_konteks_pekerjaan_menolak_titipan_rahasia(): void
    {
        $this->actingAs($this->user)->post(route('agent.tasks.store'), [
            'objective' => 'Buat laporan penjualan Juli',
            'context'   => ['dataset' => 'penjualan-2026-07.csv', 'api_key' => 'sk-ant-rahasia'],
        ])->assertRedirect();

        $task = AgentTask::firstOrFail();

        $this->assertArrayHasKey('dataset', $task->context);
        $this->assertArrayNotHasKey('api_key', $task->context);
    }

    public function test_anggota_biasa_hanya_melihat_pengalaman_dari_pekerjaannya_sendiri(): void
    {
        $rekan = User::create([
            'name' => 'Rekan Kerja', 'email' => 'rekan-memori@pt-uji.test',
            'password' => Hash::make('rahasia'), 'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $rekan->assignRole('drafter');

        // Pekerjaan milik pengguna lain menghasilkan pengalaman di organisasi ini.
        $this->runToCompletion(app(AgentTaskService::class)->create(
            $this->user, 'Buat laporan penjualan Juli', ['context' => $this->reportContext()],
        ));

        $props = $this->actingAs($rekan)->get(route('agent.index'))->viewData('page')['props'];

        $this->assertCount(0, $props['memory']['experiences'],
            'Pengalaman dari pekerjaan rekan kerja tidak boleh ikut terlihat.');

        // Pengetahuan operasional (pelajaran & statistik tool) tetap dibagikan.
        $this->assertNotEmpty($props['memory']['tool_stats']);

        $adminProps = $this->actingAs($this->user)->get(route('agent.index'))->viewData('page')['props'];
        $this->assertCount(1, $adminProps['memory']['experiences']);
    }

    public function test_pengguna_tanpa_izin_ditolak(): void
    {
        $outsider = User::create([
            'name' => 'Tanpa Izin', 'email' => 'tanpa@pt-uji.test',
            'password' => Hash::make('rahasia'), 'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);

        $this->actingAs($outsider)->get(route('agent.index'))->assertForbidden();
        $this->actingAs($outsider)->post(route('agent.tasks.store'), ['objective' => 'apa saja'])->assertForbidden();
    }

    public function test_pekerjaan_milik_orang_lain_tidak_dapat_dikendalikan(): void
    {
        $rekan = User::create([
            'name' => 'Rekan Kerja', 'email' => 'rekan@pt-uji.test',
            'password' => Hash::make('rahasia'), 'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $rekan->assignRole('drafter');

        $task = app(AgentTaskService::class)->create($this->user, 'Pekerjaan milik Rina');

        $this->actingAs($rekan)->post(route('agent.tasks.cancel', $task))->assertForbidden();
        $this->actingAs($rekan)->get(route('agent.tasks.download', ['task' => $task->id, 'index' => 0]))->assertForbidden();
    }

    public function test_hanya_administrator_yang_boleh_memberi_akses_tool(): void
    {
        $staf = User::create([
            'name' => 'Staf', 'email' => 'staf@pt-uji.test',
            'password' => Hash::make('rahasia'), 'organization_id' => $this->organization->id,
            'is_active' => true,
        ]);
        $staf->assignRole('drafter');

        $this->actingAs($staf)
            ->put(route('agent.integrations.update', 'telegram'), ['credentials' => ['bot_token' => '1:abc']])
            ->assertForbidden();

        $this->assertSame(0, \App\Models\AgentIntegration::where('status', 'connected')->count());
    }
}
