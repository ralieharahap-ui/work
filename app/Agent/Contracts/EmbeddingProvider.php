<?php

namespace App\Agent\Contracts;

/**
 * Penghasil vektor untuk pencarian kemiripan. Implementasi bawaan bersifat
 * lokal & deterministik sehingga sistem tetap berjalan tanpa layanan luar.
 */
interface EmbeddingProvider
{
    /** @return array<int, float> */
    public function embed(string $text): array;

    public function dimensions(): int;
}
