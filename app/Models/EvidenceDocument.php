<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EvidenceDocument extends Model
{
    use HasUuids;

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_SIGNED = 'signed';

    protected $fillable = [
        'organization_id', 'task_id', 'template_id', 'created_by',
        'number', 'title', 'content_html', 'data', 'orientation', 'status',
        'signature_path', 'signer_id', 'signer_name', 'signer_position',
        'signature_place', 'signed_at', 'pdf_path', 'pdf_original_name',
    ];

    protected $casts = [
        'data'      => 'array',
        'signed_at' => 'datetime',
    ];

    protected $appends = ['pdf_url', 'is_signed'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function task(): BelongsTo         { return $this->belongsTo(Task::class); }
    public function template(): BelongsTo     { return $this->belongsTo(EvidenceTemplate::class, 'template_id'); }
    public function creator(): BelongsTo      { return $this->belongsTo(User::class, 'created_by'); }
    public function signer(): BelongsTo       { return $this->belongsTo(User::class, 'signer_id'); }

    public function getIsSignedAttribute(): bool
    {
        return $this->status === self::STATUS_SIGNED;
    }

    public function getPdfUrlAttribute(): ?string
    {
        return $this->pdf_path ? Storage::disk('public')->url($this->pdf_path) : null;
    }

    /** Hapus berkas turunan (tanda tangan & PDF) milik dokumen ini. */
    public function deleteFiles(): void
    {
        foreach ([$this->signature_path, $this->pdf_path] as $path) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
