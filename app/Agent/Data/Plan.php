<?php

namespace App\Agent\Data;

/** Rencana kerja terstruktur beserta asal-usulnya (untuk keperluan audit). */
class Plan
{
    /**
     * @param  array<int, PlanStep>  $steps
     * @param  array<int, string>  $notes
     */
    public function __construct(
        public readonly string $taskType,
        public readonly array $steps,
        public readonly string $strategy = '',
        public readonly string $origin = 'heuristic', // procedure | experience | llm | heuristic
        public readonly ?string $procedureId = null,
        public readonly float $confidence = 0.5,
        public readonly array $notes = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->steps === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'task_type'    => $this->taskType,
            'strategy'     => $this->strategy,
            'origin'       => $this->origin,
            'procedure_id' => $this->procedureId,
            'confidence'   => round($this->confidence, 4),
            'notes'        => $this->notes,
            'steps'        => array_map(fn (PlanStep $s) => $s->toArray(), $this->steps),
        ];
    }
}
