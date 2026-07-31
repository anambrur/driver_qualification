<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'stripe_subscription_id',
        'stripe_status',
        'billing_cycle',
        'amount',
        'currency',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'ends_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(SubscriptionNotificationLog::class);
    }

    public function accessEndsAt(): ?CarbonInterface
    {
        return $this->ends_at
            ?? $this->trial_ends_at
            ?? $this->current_period_end;
    }

    public function isAccessible(): bool
    {
        if (in_array($this->stripe_status, ['incomplete', 'incomplete_expired', 'unpaid'], true)) {
            return false;
        }

        $endsAt = $this->accessEndsAt();

        if (in_array($this->stripe_status, ['active', 'trialing'], true)) {
            if ($this->cancel_at_period_end && $endsAt && $endsAt->isPast()) {
                return false;
            }

            if ($this->billing_cycle === 'trial' && $endsAt && $endsAt->isPast()) {
                return false;
            }

            return true;
        }

        if ($this->stripe_status === 'canceled' || $this->cancel_at_period_end) {
            return $endsAt !== null && $endsAt->isFuture();
        }

        if ($this->stripe_status === 'past_due') {
            return $endsAt === null || $endsAt->isFuture();
        }

        return false;
    }

    public function isExpired(): bool
    {
        return ! $this->isAccessible();
    }

    public function onGracePeriod(): bool
    {
        $endsAt = $this->accessEndsAt();

        return ($this->stripe_status === 'canceled' || $this->cancel_at_period_end)
            && $endsAt !== null
            && $endsAt->isFuture();
    }

    public function onTrial(): bool
    {
        return $this->stripe_status === 'trialing'
            && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture());
    }

    public function active(): bool
    {
        return $this->isAccessible() && in_array($this->stripe_status, ['active', 'trialing'], true);
    }

    public function canceled(): bool
    {
        return $this->stripe_status === 'canceled' || $this->cancel_at_period_end;
    }

    public function daysUntilAccessEnds(): ?int
    {
        $endsAt = $this->accessEndsAt();

        if (! $endsAt) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false);
    }

    public function isEndingSoon(): bool
    {
        if (! $this->shouldReceiveExpiryReminders()) {
            return false;
        }

        $days = $this->daysUntilAccessEnds();

        return $days !== null && $days >= 0 && $days <= 7;
    }

    public function shouldReceiveExpiryReminders(): bool
    {
        if ($this->source === 'trial' || $this->billing_cycle === 'trial') {
            return $this->isAccessible() || $this->justExpired();
        }

        if ($this->source === 'admin' && $this->ends_at) {
            return true;
        }

        if ($this->cancel_at_period_end || $this->stripe_status === 'canceled') {
            return true;
        }

        if ($this->stripe_status === 'past_due') {
            return true;
        }

        return false;
    }

    public function justExpired(): bool
    {
        $endsAt = $this->accessEndsAt();

        return $endsAt !== null
            && $endsAt->isPast()
            && $endsAt->gte(now()->subDay());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('stripe_status', ['active', 'trialing']);
    }

    public function scopeCanceled(Builder $query): Builder
    {
        return $query->where('stripe_status', 'canceled');
    }

    public function scopeAccessible(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('stripe_status', ['active', 'trialing'])
                ->where(function (Builder $inner) {
                    $inner->where('cancel_at_period_end', false)
                        ->orWhereNull('ends_at')
                        ->orWhere('ends_at', '>', now())
                        ->orWhere('current_period_end', '>', now())
                        ->orWhere('trial_ends_at', '>', now());
                });
        })->orWhere(function (Builder $q) {
            $q->where(function (Builder $inner) {
                $inner->where('stripe_status', 'canceled')
                    ->orWhere('cancel_at_period_end', true);
            })->where(function (Builder $inner) {
                $inner->where('ends_at', '>', now())
                    ->orWhere('current_period_end', '>', now())
                    ->orWhere('trial_ends_at', '>', now());
            });
        });
    }
}
