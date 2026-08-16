<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklistItem extends Model
{
    use HasUuids;

    protected $fillable = ['task_id', 'text', 'is_completed', 'position'];

    protected $casts = ['is_completed' => 'boolean'];

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
}
