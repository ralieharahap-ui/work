<?php

namespace App\Agent\Data;

use App\Models\AgentExperience;
use App\Models\AgentLesson;
use App\Models\AgentMemory;
use App\Models\AgentProcedure;
use Illuminate\Support\Collection;

/**
 * Kumpulan memori relevan yang diambil sebelum merencanakan sebuah task.
 *
 * @property Collection<int, array{item: AgentExperience, score: float, breakdown: array<string, float>}> $experiences
 * @property Collection<int, array{item: AgentLesson, score: float, breakdown: array<string, float>}> $lessons
 * @property Collection<int, array{item: AgentProcedure, score: float, breakdown: array<string, float>}> $procedures
 * @property Collection<int, array{item: AgentMemory, score: float, breakdown: array<string, float>}> $semantic
 */
class MemoryBundle
{
    public function __construct(
        public readonly Collection $experiences,
        public readonly Collection $lessons,
        public readonly Collection $procedures,
        public readonly Collection $semantic,
        public readonly array $toolStats = [],
    ) {
    }

    public static function empty(): self
    {
        return new self(collect(), collect(), collect(), collect(), []);
    }

    public function bestProcedure(): ?AgentProcedure
    {
        return $this->procedures->first()['item'] ?? null;
    }

    public function bestExperience(): ?AgentExperience
    {
        return $this->experiences->first()['item'] ?? null;
    }

    /** @return array<int, AgentLesson> */
    public function lessonModels(): array
    {
        return $this->lessons->pluck('item')->all();
    }

    public function isEmpty(): bool
    {
        return $this->experiences->isEmpty() && $this->lessons->isEmpty()
            && $this->procedures->isEmpty() && $this->semantic->isEmpty();
    }

    /** Ringkasan aman untuk ditampilkan di UI/jejak audit. */
    public function summary(): array
    {
        $map = fn (Collection $rows, callable $label) => $rows->map(fn (array $row) => [
            'id'    => $row['item']->id,
            'label' => $label($row['item']),
            'score' => round($row['score'], 4),
            'breakdown' => array_map(fn ($v) => round((float) $v, 4), $row['breakdown']),
        ])->values()->all();

        return [
            'experiences' => $map($this->experiences, fn (AgentExperience $e) => $e->objective),
            'lessons'     => $map($this->lessons, fn (AgentLesson $l) => $l->lesson),
            'procedures'  => $map($this->procedures, fn (AgentProcedure $p) => $p->label()),
            'semantic'    => $map($this->semantic, fn (AgentMemory $m) => $m->subject),
            'tool_stats'  => $this->toolStats,
        ];
    }
}
