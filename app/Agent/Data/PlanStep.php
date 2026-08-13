<?php

namespace App\Agent\Data;

/** Satu langkah rencana dalam bentuk data terstruktur (bukan teks bebas). */
class PlanStep
{
    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<int, string>  $dependencies
     * @param  array<int, string>  $successCriteria
     */
    public function __construct(
        public readonly string $id,
        public readonly string $objective,
        public readonly ?string $tool = null,
        public array $inputs = [],
        public readonly array $dependencies = [],
        public readonly array $successCriteria = [],
        public readonly string $riskLevel = 'low',
    ) {
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw, int $index = 0): self
    {
        return new self(
            id: (string) ($raw['id'] ?? 'step_' . ($index + 1)),
            objective: trim((string) ($raw['objective'] ?? 'Langkah tanpa tujuan')),
            tool: ($raw['tool'] ?? null) ? (string) $raw['tool'] : null,
            inputs: is_array($raw['inputs'] ?? null) ? $raw['inputs'] : [],
            dependencies: array_values(array_map('strval', (array) ($raw['dependencies'] ?? []))),
            successCriteria: array_values(array_map('strval', (array) ($raw['success_criteria'] ?? []))),
            riskLevel: in_array($raw['risk_level'] ?? 'low', ['low', 'medium', 'high'], true)
                ? (string) $raw['risk_level']
                : 'low',
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'objective'        => $this->objective,
            'tool'             => $this->tool,
            'inputs'           => $this->inputs,
            'dependencies'     => $this->dependencies,
            'success_criteria' => $this->successCriteria,
            'risk_level'       => $this->riskLevel,
        ];
    }
}
