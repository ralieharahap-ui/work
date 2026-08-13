<?php

namespace App\Agent\Data;

/**
 * Hasil pemeriksaan. Skor dihitung dari bukti (kriteria yang benar-benar
 * lolos), bukan dari klaim keyakinan model.
 */
class Verification
{
    /**
     * @param  array<int, array{criterion: string, passed: bool, detail: string}>  $checks
     * @param  array<int, string>  $issues
     */
    public function __construct(
        public readonly bool $passed,
        public readonly float $score,
        public readonly array $checks = [],
        public readonly array $issues = [],
        public readonly string $summary = '',
    ) {
    }

    /** @param array<int, array{criterion: string, passed: bool, detail: string}> $checks */
    public static function fromChecks(array $checks, string $summary = '', array $extraIssues = []): self
    {
        $total  = count($checks);
        $passed = count(array_filter($checks, static fn (array $c) => $c['passed']));
        $issues = array_values(array_merge(
            array_map(static fn (array $c) => $c['criterion'] . ' — ' . $c['detail'],
                array_filter($checks, static fn (array $c) => ! $c['passed'])),
            $extraIssues,
        ));

        return new self(
            passed: $issues === [] && ($total === 0 || $passed === $total),
            score: $total === 0 ? 0.7 : round($passed / $total, 4),
            checks: $checks,
            issues: $issues,
            summary: $summary,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'passed'  => $this->passed,
            'score'   => $this->score,
            'checks'  => $this->checks,
            'issues'  => $this->issues,
            'summary' => $this->summary,
        ];
    }
}
