<?php

namespace App\Agent\Contracts;

use Illuminate\Support\Collection;

/**
 * Penyimpanan memori jangka panjang. Implementasi bawaan memakai basis data
 * relasional dengan vektor tersimpan sebagai json; implementasi lain (vector
 * database) dapat menggantikannya tanpa mengubah kode agent.
 */
interface MemoryStore
{
    /** @param array<string, mixed> $attributes */
    public function store(string $collection, array $attributes): string;

    public function retrieve(string $collection, string $id): ?object;

    /**
     * Pencarian relevansi. `$filters` minimal mendukung organization_id,
     * task_type, dan limit.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function search(string $collection, string $query, array $filters = []): Collection;

    /** @param array<string, mixed> $attributes */
    public function update(string $collection, string $id, array $attributes): bool;

    public function delete(string $collection, string $id): bool;

    /**
     * Mengurutkan kandidat berdasarkan skor gabungan (kemiripan, keberhasilan,
     * keyakinan, kebaruan) dan mengembalikan pasangan [item, score].
     *
     * @param  Collection<int, object>  $candidates
     * @return Collection<int, array{item: object, score: float, breakdown: array<string, float>}>
     */
    public function rank(Collection $candidates, string $query, array $options = []): Collection;
}
