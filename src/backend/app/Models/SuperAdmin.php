<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * SuperAdmin — platform-level administrator.
 *
 * Completely separate from App\Models\User (merchant users).
 * Has NO merchant_id — operates at platform scope.
 *
 * Guard: 'super-admin' (see config/auth.php).
 * Tokens stored in personal_access_tokens with
 * tokenable_type = 'App\Models\SuperAdmin'.
 *
 * IMPORTANT: Never use auth:sanctum alone on super admin routes.
 * Always use the SuperAdminAuth middleware which explicitly
 * checks instanceof SuperAdmin after token resolution.
 */
class SuperAdmin extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'super_admins';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'       => 'hashed',
            'last_login_at'  => 'datetime',
        ];
    }
}
