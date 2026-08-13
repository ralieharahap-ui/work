<?php

namespace App\Agent\Contracts;

use App\Agent\Data\LlmRequest;
use App\Agent\Data\LlmResponse;

/**
 * Abstraksi penyedia model bahasa. Logika agent tidak boleh bergantung pada
 * SDK penyedia mana pun — hanya pada antarmuka ini.
 */
interface LlmProvider
{
    public function name(): string;

    /** True bila kredensial/prasyarat penyedia sudah lengkap. */
    public function isConfigured(): bool;

    public function generate(LlmRequest $request): LlmResponse;

    /**
     * Menghasilkan objek terstruktur sesuai skema JSON yang diberikan.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function structured(LlmRequest $request, array $schema): array;

    /**
     * Aliran token bertahap. Penyedia yang tidak mendukung streaming
     * mengirimkan seluruh teks sebagai satu potongan.
     *
     * @param  callable(string): void  $onChunk
     */
    public function stream(LlmRequest $request, callable $onChunk): LlmResponse;
}
