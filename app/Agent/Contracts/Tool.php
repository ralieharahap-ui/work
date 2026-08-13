<?php

namespace App\Agent\Contracts;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;

/**
 * Kontrak tunggal yang dipakai runtime agent. Runtime tidak pernah memanggil
 * layanan luar secara langsung — selalu lewat abstraksi ini, sehingga setiap
 * tindakan dapat divalidasi, diotorisasi, dicatat, dan dinilai.
 */
interface Tool
{
    public function name(): string;

    public function describe(): ToolDefinition;

    /**
     * Validasi & normalisasi input. Melempar InvalidArgumentException bila
     * input tidak memenuhi skema; mengembalikan input yang sudah dirapikan.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array;

    /** @param array<string, mixed> $input */
    public function execute(array $input, ToolContext $context): ToolResult;
}
