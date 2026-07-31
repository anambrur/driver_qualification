<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasRoles, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'stripe_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

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
