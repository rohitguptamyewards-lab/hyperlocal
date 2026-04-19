<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name', 'email', 'password',
        'merchant_id', 'outlet_id', 'role',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => 'integer',
        ];
    }

    // ── Role helpers ─────────────────────────────────────────

    public function isAdmin(): bool    { return $this->role === 1; }
    public function isManager(): bool  { return $this->role === 2; }
    public function isCashier(): bool  { return $this->role === 3; }

    // ── Relationships ─────────────────────────────────────────

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
