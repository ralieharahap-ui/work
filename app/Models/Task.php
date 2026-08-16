<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Task extends Model
{
    use HasUuids;

    public const STATUSES = ['To Do', 'In Progress', 'Review', 'Blocked', 'Done'];
    public const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];
    public const CATEGORIES = ['Operasional', 'Strategis'];

    protected $fillable = [
        'organization_id', 'project_id', 'division_id', 'pic_id', 'created_by',
        'title', 'description', 'category', 'status', 'priority', 'deadline',
        'evidence_path', 'evidence_original_name', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'deadline'  => 'date',
        'closed_at' => 'datetime',
    ];

    protected $appends = ['evidence_url', 'is_overdue'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function project(): BelongsTo      { return $this->belongsTo(TaskProject::class, 'project_id'); }
    public function division(): BelongsTo     { return $this->belongsTo(Division::class); }
    public function pic(): BelongsTo          { return $this->belongsTo(User::class, 'pic_id'); }
    public function creator(): BelongsTo      { return $this->belongsTo(User::class, 'created_by'); }
    public function closer(): BelongsTo       { return $this->belongsTo(User::class, 'closed_by'); }
    public function checklistItems(): HasMany { return $this->hasMany(TaskChecklistItem::class)->orderBy('position'); }
    public function comments(): HasMany       { return $this->hasMany(TaskComment::class)->latest(); }

    public function getEvidenceUrlAttribute(): ?string
    {
        return $this->evidence_path ? Storage::disk('public')->url($this->evidence_path) : null;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->deadline && $this->status !== 'Done' && $this->deadline->isPast();
    }
}
