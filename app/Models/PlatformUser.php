<?php

namespace App\Models;

use App\Authorization\TenantPermissionResolver;
use App\Notifications\PlatformResetPasswordNotification;
use BackedEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * The reset link points at the platform area, not the tenant one.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PlatformResetPasswordNotification($token));
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

    /**
     * Stored lowercase, like users.email, so the D3a check compares like with like.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => mb_strtolower(trim($value)));
    }
}
