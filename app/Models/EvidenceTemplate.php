<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvidenceTemplate extends Model
{
    use HasUuids;

    public const CATEGORIES = [
        'Berita Acara',
        'Kertas Kerja',
        'Laporan',
        'Checklist',
        'Serah Terima',
    ];

    protected $fillable = [
        'organization_id', 'created_by', 'code', 'name', 'description', 'category',
        'body_html', 'fields', 'orientation', 'is_system', 'is_active',
    ];

    protected $casts = [
        'fields'    => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function creator(): BelongsTo      { return $this->belongsTo(User::class, 'created_by'); }
    public function documents(): HasMany      { return $this->hasMany(EvidenceDocument::class, 'template_id'); }

    /** Template bawaan sistem + template milik organisasi tersebut. */
    public function scopeAvailableTo(Builder $query, ?string $organizationId): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('organization_id')->orWhere('organization_id', $organizationId));
    }
}
