<?php

namespace App\Models;

use App\Authorization\TenantPermissionResolver;
use BackedEnum;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Traits\HasRoles;

/**
 * Email addresses are verified (PO-15): a free trial is limited to one per verified email.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'user_type',
        'name',
        'email',
        'password',
        'phone',
        'status',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Login emails are global and stored lowercase (D3).
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => mb_strtolower(trim($value)));
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id')
            ->orderByDesc('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at');
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->accessible()
            ->get()
            ->first(fn (Subscription $subscription) => $subscription->isAccessible());
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    public function hasUsedTrial(): bool
    {
        return $this->subscriptions()
            ->where(function ($query) {
                $query->where('source', 'trial')
                    ->orWhere('billing_cycle', 'trial');
            })
            ->exists();
    }

    public function notificationPhone(): ?string
    {
        $phone = $this->company?->phone;

        return $phone ? preg_replace('/\s+/', '', $phone) : null;
    }
}
