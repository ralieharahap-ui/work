<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskProject extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id', 'owner_id', 'title', 'description', 'status', 'end_date',
    ];

    protected $casts = ['end_date' => 'date'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function owner(): BelongsTo        { return $this->belongsTo(User::class, 'owner_id'); }
    public function tasks(): HasMany          { return $this->hasMany(Task::class, 'project_id'); }
}
