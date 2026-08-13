<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasUuids;

    protected $fillable = [
        'name', 'email', 'password', 'organization_id', 'division_id',
        'employee_id', 'phone', 'whatsapp_number', 'whatsapp_opt_in',
        'hierarchy', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active'         => 'boolean',
        'whatsapp_opt_in'   => 'boolean',
        'last_login_at'     => 'datetime',
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function division(): BelongsTo     { return $this->belongsTo(Division::class); }

    public function canApprove(string $module): bool
    {
        return $this->hasPermissionTo("$module.approve");
    }
}
