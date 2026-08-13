<?php

namespace App\Agent\Policy;

/** Keputusan kebijakan atas satu langkah sebelum tool dijalankan. */
class PolicyDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly bool $requiresApproval,
        public readonly string $riskLevel,
        public readonly string $reason,
    ) {
    }

    public static function allow(string $riskLevel = 'low', string $reason = 'Risiko rendah, agent boleh jalan sendiri.'): self
    {
        return new self(true, false, $riskLevel, $reason);
    }

    public static function needsApproval(string $riskLevel, string $reason): self
    {
        return new self(true, true, $riskLevel, $reason);
    }

    public static function deny(string $reason, string $riskLevel = 'high'): self
    {
        return new self(false, false, $riskLevel, $reason);
    }
}
