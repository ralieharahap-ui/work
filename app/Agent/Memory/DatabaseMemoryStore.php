<?php

namespace App\Agent\Memory;

use App\Agent\Contracts\EmbeddingProvider;
use App\Agent\Contracts\MemoryStore;
use App\Models\AgentExperience;
use App\Models\AgentLesson;
use App\Models\AgentMemory;
use App\Models\AgentProcedure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Implementasi MemoryStore di atas basis data relasional; vektor disimpan
 * sebagai json dan pemeringkatan dilakukan di dalam proses.
 *
 * Untuk volume memori kantor (ribuan pengalaman) pendekatan ini memadai dan
 * bebas ketergantungan. Bila kelak perlu jutaan entri, cukup buat implementasi
 * MemoryStore lain yang berbicara dengan vector database — kode agent tidak
 * perlu berubah sama sekali.
 */
class DatabaseMemoryStore implements MemoryStore
{
    /** Jumlah kandidat yang ditarik sebelum pemeringkatan. */
    private const CANDIDATE_LIMIT = 200;

    public function __construct(
        private readonly EmbeddingProvider $embedder,
        private readonly ExperienceScorer $scorer,
    ) {
    }

    public function store(string $collection, array $attributes): string
    {
        $model = $this->model($collection);

        /** @var Model $record */
        $record = $model::create($this->withVector($collection, $attributes));

        return (string) $record->getKey();
    }

    public function retrieve(string $collection, string $id): ?object
    {
        return $this->model($collection)::find($id);
    }

    public function search(string $collection, string $query, array $filters = []): Collection
    {
        $model = $this->model($collection);
        $builder = $model::query();

        if (! empty($filters['organization_id'])) {
            $builder->where('organization_id', $filters['organization_id']);
        }

        // Memori bertipe umum tetap ikut dipertimbangkan lintas jenis task.
        if (! empty($filters['task_type']) && $this->hasColumn($collection, 'task_type')) {
            $builder->where(function ($q) use ($filters) {
                $q->where('task_type', $filters['task_type'])->orWhere('task_type', 'general');
            });
        }

        if (! ($filters['include_inactive'] ?? false)) {
            match ($collection) {
                'experiences' => $builder->where('is_obsolete', false),
                'lessons', 'procedures' => $builder->where('is_active', true),
                default => null,
            };
        }

        return $builder->latest('updated_at')->limit(self::CANDIDATE_LIMIT)->get();
    }

    public function update(string $collection, string $id, array $attributes): bool
    {
        $record = $this->retrieve($collection, $id);

        if (! $record instanceof Model) {
            return false;
        }

        return (bool) $record->fill($this->withVector($collection, $attributes, $record))->save();
    }

    public function delete(string $collection, string $id): bool
    {
        $record = $this->retrieve($collection, $id);

        return $record instanceof Model ? (bool) $record->delete() : false;
    }

    public function rank(Collection $candidates, string $query, array $options = []): Collection
    {
        $vector   = $this->embedder->embed($query);
        $keywords = HashingEmbedder::tokenize($query);
        $minScore = (float) ($options['min_score'] ?? config('agent.memory.retrieval.min_score', 0.08));
        $limit    = (int) ($options['limit'] ?? 5);

        return $candidates
            ->map(function (object $item) use ($vector, $keywords) {
                $scored = $this->scorer->score($item, $vector, $keywords);

                return ['item' => $item, 'score' => $scored['score'], 'breakdown' => $scored['breakdown']];
            })
            ->filter(fn (array $row) => $row['score'] >= $minScore)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /** Teks yang diindeks untuk tiap jenis memori. */
    public function indexText(string $collection, array $attributes): string
    {
        return match ($collection) {
            'experiences' => trim(($attributes['objective'] ?? '') . ' ' . ($attributes['task_type'] ?? '') . ' ' . ($attributes['reusable_strategy'] ?? '')),
            'lessons'     => trim(($attributes['trigger'] ?? '') . ' ' . ($attributes['lesson'] ?? '') . ' ' . ($attributes['subject'] ?? '')),
            'procedures'  => trim(($attributes['name'] ?? '') . ' ' . ($attributes['trigger'] ?? '') . ' ' . ($attributes['task_type'] ?? '')),
            default       => trim(($attributes['subject'] ?? '') . ' ' . ($attributes['content'] ?? '')),
        };
    }

    /**
     * Menyiapkan vektor & kata kunci indeks bila pemanggil belum menyediakannya,
     * sehingga setiap memori yang tersimpan selalu dapat dicari kembali.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withVector(string $collection, array $attributes, ?Model $existing = null): array
    {
        if (isset($attributes['embedding'])) {
            return $attributes;
        }

        $merged = array_merge($existing?->attributesToArray() ?? [], $attributes);
        $text   = $this->indexText($collection, $merged);

        if ($text === '') {
            return $attributes;
        }

        $attributes['embedding'] = $this->embedder->embed($text);
        $attributes['keywords'] ??= HashingEmbedder::tokenize($text);

        return $attributes;
    }

    /** @return class-string<Model> */
    private function model(string $collection): string
    {
        return match ($collection) {
            'experiences' => AgentExperience::class,
            'lessons'     => AgentLesson::class,
            'procedures'  => AgentProcedure::class,
            'semantic'    => AgentMemory::class,
            default       => throw new InvalidArgumentException("Koleksi memori '{$collection}' tidak dikenal."),
        };
    }

    private function hasColumn(string $collection, string $column): bool
    {
        return match ($collection) {
            'semantic' => false,
            default    => $column === 'task_type',
        };
    }
}
