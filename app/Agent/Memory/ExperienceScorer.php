<?php

namespace App\Agent\Memory;

use Illuminate\Support\Carbon;

/**
 * Skor kegunaan sebuah memori pada konteks tertentu:
 *
 *   skor = w1·kemiripan + w2·tingkat_keberhasilan + w3·keyakinan + w4·kebaruan
 *
 * Bentuk penjumlahan berbobot dipilih ketimbang perkalian murni agar satu
 * faktor bernilai nol (mis. pengalaman baru yang belum pernah dipakai ulang)
 * tidak menghapus seluruh nilai memori tersebut.
 */
class ExperienceScorer
{
    /**
     * @param  array<int, float>  $queryVector
     * @param  array<int, string>  $queryKeywords
     * @return array{score: float, breakdown: array<string, float>}
     */
    public function score(object $item, array $queryVector, array $queryKeywords): array
    {
        $weights   = (array) config('agent.memory.weights');
        $halfLife  = max(1, (int) config('agent.memory.retrieval.recency_half_life', 45));

        $vector    = (array) ($item->embedding ?? []);
        $keywords  = (array) ($item->keywords ?? []);

        $cosine    = HashingEmbedder::cosine($queryVector, $vector);
        $jaccard   = HashingEmbedder::jaccard($queryKeywords, $keywords);
        $similarity = round(0.7 * $cosine + 0.3 * $jaccard, 6);

        $success = $this->successRate($item);
        $confidence = (float) ($item->confidence ?? $item->success_rate ?? 0.5);

        $updatedAt = $item->last_used_at ?? $item->updated_at ?? $item->created_at ?? null;
        $ageDays   = $updatedAt instanceof Carbon ? max(0, $updatedAt->diffInDays(now())) : 30;
        $recency   = round(pow(0.5, $ageDays / $halfLife), 6);

        $score = ($weights['similarity'] ?? 0.45) * $similarity
            + ($weights['success'] ?? 0.25) * $success
            + ($weights['confidence'] ?? 0.15) * $confidence
            + ($weights['recency'] ?? 0.15) * $recency;

        // Memori yang sudah usang tetap tersimpan sebagai riwayat, tetapi
        // prioritasnya ditekan agar tidak menyesatkan rencana baru.
        if (($item->is_obsolete ?? false) || (($item->is_active ?? true) === false)) {
            $score *= 0.2;
        }

        return [
            'score' => round($score, 6),
            'breakdown' => [
                'similarity' => $similarity,
                'success'    => round($success, 6),
                'confidence' => round($confidence, 6),
                'recency'    => $recency,
            ],
        ];
    }

    private function successRate(object $item): float
    {
        if (method_exists($item, 'successRate')) {
            return (float) $item->successRate();
        }

        if (isset($item->success_rate)) {
            return (float) $item->success_rate;
        }

        $used = (int) ($item->success_count ?? 0) + (int) ($item->failure_count ?? 0);

        return $used === 0 ? 0.5 : round(((int) $item->success_count) / $used, 4);
    }
}
