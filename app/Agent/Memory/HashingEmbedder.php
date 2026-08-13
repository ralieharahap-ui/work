<?php

namespace App\Agent\Memory;

use App\Agent\Contracts\EmbeddingProvider;
use Illuminate\Support\Str;

/**
 * Vektor lokal berbasis hashing (hashing trick) atas kata dan pasangan kata.
 *
 * Deterministik, tanpa jaringan, dan cukup untuk mengenali kemiripan pekerjaan
 * kantor yang kosakatanya berulang. Karena berada di balik EmbeddingProvider,
 * penggantinya (model embedding sungguhan) dapat dipasang tanpa mengubah
 * kode pencarian memori.
 */
class HashingEmbedder implements EmbeddingProvider
{
    private const STOPWORDS = [
        'yang', 'untuk', 'dari', 'dan', 'dengan', 'pada', 'agar', 'saya', 'kami', 'tolong',
        'mohon', 'ini', 'itu', 'ke', 'di', 'atau', 'juga', 'sudah', 'akan', 'the', 'a', 'an',
        'to', 'for', 'of', 'and', 'with', 'please',
    ];

    public function __construct(private readonly int $dimensions = 128)
    {
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    /** @return array<int, float> */
    public function embed(string $text): array
    {
        $tokens = self::tokenize($text);
        $vector = array_fill(0, $this->dimensions, 0.0);

        if ($tokens === []) {
            return $vector;
        }

        // Kata tunggal + bigram supaya urutan kata ikut berpengaruh.
        $features = $tokens;
        for ($i = 0; $i < count($tokens) - 1; $i++) {
            $features[] = $tokens[$i] . '_' . $tokens[$i + 1];
        }

        foreach ($features as $feature) {
            $hash  = crc32($feature);
            $index = $hash % $this->dimensions;
            $sign  = ($hash >> 31 & 1) === 1 ? -1.0 : 1.0;
            $vector[$index] += $sign;
        }

        $norm = sqrt(array_sum(array_map(static fn (float $v) => $v * $v, $vector)));

        return $norm > 0 ? array_map(static fn (float $v) => round($v / $norm, 6), $vector) : $vector;
    }

    /** @return array<int, string> */
    public static function tokenize(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}_]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $words,
            static fn (string $word) => mb_strlen($word) > 2 && ! in_array($word, self::STOPWORDS, true),
        ));
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        if ($a === [] || $b === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        foreach ($a as $i => $value) {
            $dot += $value * ($b[$i] ?? 0.0);
        }

        // Vektor sudah dinormalisasi, jadi hasil kali titik = kosinus.
        return max(0.0, min(1.0, $dot));
    }

    /**
     * Kemiripan kata kunci (Jaccard) sebagai pelengkap kosinus — menjaga
     * ketahanan ketika teks pendek dan vektornya kurang informatif.
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    public static function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $union = count(array_unique(array_merge($a, $b)));

        return $union === 0 ? 0.0 : round(count(array_intersect($a, $b)) / $union, 6);
    }
}
