<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $current_period_start
 * @property \Illuminate\Support\Carbon|null $current_period_end
 * @property \Illuminate\Support\Carbon|null $ends_at
 */
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<SubscriptionNotificationLog, $this>
     */
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

    /**
     * SQL mirror of isAccessible(); keep the two in sync.
     */
    public function scopeAccessible(Builder $query): Builder
    {
        // Same fallback order as accessEndsAt().
        $endsAt = 'COALESCE(ends_at, trial_ends_at, current_period_end)';
        $now = now();

        return $query->where(function (Builder $q) use ($endsAt, $now) {
            $q->where(function (Builder $q) use ($endsAt, $now) {
                $q->whereIn('stripe_status', ['active', 'trialing'])
                    ->where(function (Builder $inner) use ($endsAt, $now) {
                        $inner->where('cancel_at_period_end', false)
                            ->orWhereRaw("{$endsAt} IS NULL")
                            ->orWhereRaw("{$endsAt} >= ?", [$now]);
                    })
                    ->where(function (Builder $inner) use ($endsAt, $now) {
                        $inner->where('billing_cycle', '!=', 'trial')
                            ->orWhereRaw("{$endsAt} IS NULL")
                            ->orWhereRaw("{$endsAt} >= ?", [$now]);
                    });
            })->orWhere(function (Builder $q) use ($endsAt, $now) {
                $q->whereNotIn('stripe_status', ['active', 'trialing', 'incomplete', 'incomplete_expired', 'unpaid'])
                    ->where(function (Builder $inner) {
                        $inner->where('stripe_status', 'canceled')
                            ->orWhere('cancel_at_period_end', true);
                    })
                    ->whereRaw("{$endsAt} > ?", [$now]);
            })->orWhere(function (Builder $q) use ($endsAt, $now) {
                $q->where('stripe_status', 'past_due')
                    ->where('cancel_at_period_end', false)
                    ->where(function (Builder $inner) use ($endsAt, $now) {
                        $inner->whereRaw("{$endsAt} IS NULL")
                            ->orWhereRaw("{$endsAt} > ?", [$now]);
                    });
            });
        });
    }
}
