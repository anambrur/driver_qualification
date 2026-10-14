<?php

namespace App\Models;

use App\Authorization\TenantPermissionResolver;
use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Traits\HasRoles;

/**
 * Platform staff, behind the `platform` guard. Never a row in `users` (D3a).
 */
class PlatformUser extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'platform';

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Decided database-first in the user's own team (06 §4.3), never from Spatie's role cache.
     * hasAnyPermission, hasAllPermissions and checkPermissionTo all call this.
     *
     * @param  string|int|PermissionContract|BackedEnum  $permission
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        return app(TenantPermissionResolver::class)->allows($this, $permission, $guardName);
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
