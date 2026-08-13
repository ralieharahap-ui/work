<?php

namespace Tests\Feature\Agent;

use App\Agent\Integrations\IntegrationManager;
use App\Agent\Runtime\AgentTaskService;
use App\Agent\Runtime\ApprovalService;
use App\Models\AgentApproval;
use App\Models\AgentEvent;
use App\Models\AgentTask;
use App\Models\AgentToolExecution;

/** Kebijakan risiko, persetujuan manusia, idempotensi, dan permintaan akses. */
class AgentPolicyTest extends AgentTestCase
{
    public function test_tindakan_berisiko_tinggi_berhenti_menunggu_persetujuan(): void
    {
        $task = $this->emailTask();

        $this->assertSame(AgentTask::WAITING_APPROVAL, $task->status);
        $this->assertTrue($task->approval_required);

        $approval = AgentApproval::where('task_id', $task->id)->firstOrFail();
        $this->assertSame('email.send', $approval->tool);
        $this->assertSame('high', $approval->risk_level);
        $this->assertSame('pending', $approval->status);

        // Tidak ada email yang dikirim sebelum manusia memutuskan.
        $this->assertSame(0, AgentToolExecution::where('task_id', $task->id)->where('tool', 'email.send')->count());
    }

    public function test_penolakan_melewati_tindakan_tanpa_menjalankannya(): void
    {
        $task     = $this->emailTask();
        $approval = AgentApproval::where('task_id', $task->id)->firstOrFail();

        app(ApprovalService::class)->decide($approval, false, $this->user, 'web');

        $task = $this->runToCompletion($task->refresh());

        $this->assertSame('skipped', $task->steps()->where('tool', 'email.send')->first()->status);
        $this->assertSame(0, AgentToolExecution::where('task_id', $task->id)->where('tool', 'email.send')->count());
        $this->assertContains('APPROVAL_REJECTED', AgentEvent::where('task_id', $task->id)->pluck('type')->all());
    }

    public function test_persetujuan_melanjutkan_pekerjaan_dan_akses_yang_kurang_diminta_dengan_panduan(): void
    {
        $task     = $this->emailTask();
        $approval = AgentApproval::where('task_id', $task->id)->firstOrFail();

        app(ApprovalService::class)->decide($approval, true, $this->user, 'web');

        $task = $this->runToCompletion($task->refresh());

        // Tanpa akses email, agent berhenti dan meminta akses — bukan gagal diam-diam.
        $this->assertSame(AgentTask::PAUSED, $task->status);
        $this->assertSame('microsoft365', $task->working_memory['blocked_on']);
        $this->assertContains('smtp_email', $task->working_memory['blocked_alternatives']);

        $event = AgentEvent::where('task_id', $task->id)->where('type', 'ACCESS_REQUESTED')->firstOrFail();
        $this->assertNotEmpty($event->payload['guidance']);
    }

    public function test_akses_pengganti_membuka_jalan_dan_pekerjaan_dilanjutkan_denyut_berikutnya(): void
    {
        $task     = $this->emailTask();
        $approval = AgentApproval::where('task_id', $task->id)->firstOrFail();
        app(ApprovalService::class)->decide($approval, true, $this->user, 'web');
        $this->runToCompletion($task->refresh());

        app(IntegrationManager::class)->connect(
            $this->organization->id, 'smtp_email', ['from_address' => 'asisten@pt-uji.test'], $this->user,
        );

        $this->artisan('agent:tick')->assertSuccessful();

        $task = $this->runToCompletion($task->refresh());

        $this->assertSame(AgentTask::COMPLETED, $task->status);
        $this->assertSame(1, AgentToolExecution::where('task_id', $task->id)
            ->where('tool', 'email.send')->where('status', 'succeeded')->count());
    }

    public function test_tindakan_yang_sama_tidak_pernah_dijalankan_dua_kali(): void
    {
        $task     = $this->emailTask();
        $approval = AgentApproval::where('task_id', $task->id)->firstOrFail();
        app(ApprovalService::class)->decide($approval, true, $this->user, 'web');

        app(IntegrationManager::class)->connect(
            $this->organization->id, 'smtp_email', ['from_address' => 'asisten@pt-uji.test'], $this->user,
        );

        $task = $this->runToCompletion($task->refresh());
        $this->assertSame(AgentTask::COMPLETED, $task->status);

        // Menjalankan ulang langkah yang sama: hasilnya dipakai ulang,
        // tool tidak dipanggil lagi, email tidak terkirim dua kali.
        $step = $task->steps()->where('tool', 'email.send')->firstOrFail();
        $step->update(['status' => 'pending']);
        $task->update(['status' => AgentTask::EXECUTING]);

        $this->runToCompletion($task->refresh());

        $this->assertSame(1, AgentToolExecution::where('task_id', $task->id)
            ->where('tool', 'email.send')->where('status', 'succeeded')->count());
        $this->assertContains('TOOL_REPLAYED', AgentEvent::where('task_id', $task->id)->pluck('type')->all());
    }

    public function test_tool_tanpa_izin_aplikasi_ditolak_kebijakan(): void
    {
        // Pengguna tanpa izin modul tugas tidak boleh memakai tool tasks.search
        // walaupun agent merencanakannya.
        $this->user->syncRoles([]);
        $this->user->givePermissionTo('agent.view', 'agent.create');

        $task = app(AgentTaskService::class)->create($this->user, 'Ingatkan pekerjaan yang belum selesai', [
            'idempotency_key' => 'uji-izin',
        ]);

        $task = $this->runToCompletion($task);

        $blocked = AgentEvent::where('task_id', $task->id)->where('type', 'POLICY_BLOCKED')->first();
        $this->assertNotNull($blocked, 'Kebijakan tidak menghalangi tool yang izinnya tidak dimiliki.');
        $this->assertStringContainsString('tasks.view', (string) $blocked->message);
    }

    private function emailTask(): AgentTask
    {
        $task = app(AgentTaskService::class)->create($this->user, 'Kirim email ringkasan kepada klien', [
            'context'         => ['email_to' => ['klien@example.test'], 'subject' => 'Ringkasan'],
            'idempotency_key' => 'uji-email-' . uniqid(),
        ]);

        return $this->runToCompletion($task);
    }
}
