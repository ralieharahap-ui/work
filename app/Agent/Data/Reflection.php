<?php

namespace App\Agent\Data;

/** Hasil refleksi setelah task berakhir — bahan baku memori jangka panjang. */
class Reflection
{
    /**
     * @param  array<int, string>  $successfulPatterns
     * @param  array<int, string>  $failedPatterns
     * @param  array<int, array{scope: string, subject: ?string, trigger: string, lesson: string, recommendation: string, payload: array<string, mixed>, confidence: float}>  $lessons
     */
    public function __construct(
        public readonly string $outcome,           // SUCCESS | PARTIAL | FAILURE
        public readonly string $summary,
        public readonly array $successfulPatterns = [],
        public readonly array $failedPatterns = [],
        public readonly array $lessons = [],
        public readonly string $reusableStrategy = '',
        public readonly float $confidence = 0.5,
        public readonly bool $reliable = true,     // layak dipakai ulang?
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'outcome'             => $this->outcome,
            'summary'             => $this->summary,
            'successful_patterns' => $this->successfulPatterns,
            'failed_patterns'     => $this->failedPatterns,
            'lessons'             => $this->lessons,
            'reusable_strategy'   => $this->reusableStrategy,
            'confidence'          => round($this->confidence, 4),
            'reliable'            => $this->reliable,
        ];
    }
}
