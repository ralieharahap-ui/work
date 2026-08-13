<?php

namespace Tests\Unit\Agent;

use App\Agent\Data\ToolDefinition;
use App\Agent\Policy\PolicyEngine;
use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\AgentTaskStep;
use Tests\TestCase;

/** Gerbang kebijakan sebelum tool dijalankan. */
class PolicyEngineTest extends TestCase
{
    private PolicyEngine $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new PolicyEngine();
    }

    public function test_langkah_berisiko_rendah_berjalan_sendiri(): void
    {
        $decision = $this->policy->evaluate(
            $this->task(), $this->step('low'), $this->tool('spreadsheet.read', 'low'), null, 0.9,
        );

        $this->assertTrue($decision->allowed);
        $this->assertFalse($decision->requiresApproval);
    }

    public function test_langkah_berisiko_tinggi_selalu_minta_persetujuan(): void
    {
        config()->set('agent.policy.autonomy', 'autonomous');

        $decision = $this->policy->evaluate(
            $this->task(), $this->step('high'), $this->tool('email.send', 'high', readOnly: false), null, 0.99,
        );

        $this->assertTrue($decision->requiresApproval);
        $this->assertSame('high', $decision->riskLevel);
    }

    public function test_risiko_sedang_bergantung_mode_otonomi_dan_keyakinan(): void
    {
        $task = $this->task();
        $step = $this->step('medium');
        $tool = $this->tool('document.create', 'medium', readOnly: false);

        config()->set('agent.policy.autonomy', 'supervised');
        $this->assertTrue($this->policy->evaluate($task, $step, $tool, null, 0.95)->requiresApproval);

        config()->set('agent.policy.autonomy', 'balanced');
        config()->set('agent.policy.confidence_threshold', 0.55);
        $this->assertTrue($this->policy->evaluate($task, $step, $tool, null, 0.3)->requiresApproval);
        $this->assertFalse($this->policy->evaluate($task, $step, $tool, null, 0.8)->requiresApproval);

        config()->set('agent.policy.autonomy', 'autonomous');
        $this->assertFalse($this->policy->evaluate($task, $step, $tool, null, 0.3)->requiresApproval);
    }

    public function test_tool_yang_diblokir_dan_di_luar_kemampuan_agent_ditolak(): void
    {
        config()->set('agent.policy.blocked_tools', ['whatsapp.send']);

        $decision = $this->policy->evaluate(
            $this->task(), $this->step('low'), $this->tool('whatsapp.send', 'high'), null, 0.9,
        );
        $this->assertFalse($decision->allowed);

        $task = $this->task();
        $task->agent->capabilities = ['spreadsheet.read'];

        $decision = $this->policy->evaluate(
            $task, $this->step('low'), $this->tool('document.create', 'low'), null, 0.9,
        );
        $this->assertFalse($decision->allowed);
        $this->assertStringContainsString('kemampuan', $decision->reason);
    }

    public function test_batas_jumlah_langkah_menghentikan_pekerjaan_yang_berputar(): void
    {
        config()->set('agent.limits.max_steps', 5);

        $task = $this->task();
        $task->steps_executed = 5;

        $decision = $this->policy->evaluate($task, $this->step('low'), $this->tool('agent.note', 'low'), null, 0.9);

        $this->assertFalse($decision->allowed);
    }

    private function task(): AgentTask
    {
        $agent = new Agent(['name' => 'Uji', 'autonomy' => 'balanced']);

        $task = new AgentTask(['task_type' => 'general', 'steps_executed' => 0]);
        $task->setRelation('agent', $agent);
        $task->setRelation('user', null);
        $task->steps_executed = 0;

        return $task;
    }

    private function step(string $risk): AgentTaskStep
    {
        return new AgentTaskStep(['objective' => 'langkah uji', 'risk_level' => $risk]);
    }

    private function tool(string $name, string $risk, bool $readOnly = true): ToolDefinition
    {
        return new ToolDefinition(
            name: $name, title: $name, description: 'uji',
            riskLevel: $risk, readOnly: $readOnly,
        );
    }
}
