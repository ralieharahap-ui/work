<?php

namespace App\Agent\Policy;

use App\Agent\Data\ToolDefinition;
use App\Models\AgentTask;
use App\Models\AgentTaskStep;
use App\Models\User;

/**
 * Gerbang wajib sebelum setiap pemanggilan tool.
 *
 * Tidak ada tool yang boleh dijalankan tanpa melewati kelas ini: ia memeriksa
 * kemampuan agent, izin aplikasi milik pemilik pekerjaan, tingkat risiko, dan
 * batas eksekusi — lalu memutuskan jalan sendiri, minta persetujuan, atau tolak.
 */
class PolicyEngine
{
    public function evaluate(
        AgentTask $task,
        AgentTaskStep $step,
        ToolDefinition $definition,
        ?User $owner,
        float $confidence,
    ): PolicyDecision {
        $tool = $definition->name;

        if (in_array($tool, (array) config('agent.policy.blocked_tools', []), true)) {
            return PolicyDecision::deny("Tool '{$tool}' diblokir oleh konfigurasi.");
        }

        if ($task->agent && ! $task->agent->allowsTool($tool)) {
            return PolicyDecision::deny("Agent ini tidak diberi kemampuan '{$tool}'.");
        }

        if ($task->steps_executed >= (int) config('agent.limits.max_steps', 40)) {
            return PolicyDecision::deny('Batas jumlah langkah untuk satu pekerjaan sudah tercapai.');
        }

        // Agent tidak boleh melampaui hak pemilik pekerjaan di aplikasi ini.
        if ($definition->permission && $owner && ! $owner->can($definition->permission)) {
            return PolicyDecision::deny(
                "Pemilik pekerjaan tidak memiliki izin '{$definition->permission}' yang dibutuhkan tool '{$tool}'."
            );
        }

        $risk     = $this->highestRisk($step->risk_level, $definition->riskLevel);
        $autonomy = (string) config('agent.policy.autonomy', 'balanced');

        if (in_array($tool, (array) config('agent.policy.always_approve', []), true)) {
            return PolicyDecision::needsApproval($risk, "Tindakan '{$tool}' berdampak keluar dan tidak dapat ditarik kembali.");
        }

        if ($risk === 'high') {
            return PolicyDecision::needsApproval('high', 'Langkah berisiko tinggi wajib disetujui manusia.');
        }

        if ($risk === 'medium') {
            if ($autonomy === 'supervised') {
                return PolicyDecision::needsApproval('medium', 'Mode pengawasan penuh: langkah berisiko sedang perlu persetujuan.');
            }

            $threshold = (float) config('agent.policy.confidence_threshold', 0.55);

            if ($autonomy === 'balanced' && $confidence < $threshold) {
                return PolicyDecision::needsApproval(
                    'medium',
                    sprintf('Keyakinan agent %.0f%% di bawah ambang %.0f%% untuk langkah berisiko sedang.', $confidence * 100, $threshold * 100),
                );
            }
        }

        if (! $definition->readOnly && $risk === 'low') {
            return PolicyDecision::allow($risk, 'Perubahan terbatas pada ruang kerja agent.');
        }

        return PolicyDecision::allow($risk);
    }

    private function highestRisk(string ...$levels): string
    {
        $rank = ['low' => 0, 'medium' => 1, 'high' => 2];
        $max  = 'low';

        foreach ($levels as $level) {
            if (($rank[$level] ?? 0) > ($rank[$max] ?? 0)) {
                $max = $level;
            }
        }

        return $max;
    }
}
