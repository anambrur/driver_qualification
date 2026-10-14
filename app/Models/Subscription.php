<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $ends_at
 */
class Subscription extends Model
{
    protected $fillable = [
        'company_id',
        'actor_user_id',
        'plan_price_id',
        'stripe_subscription_id',
        'stripe_price_id',
        'interval',
        'amount',
        'currency',
        'status',
        'source',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'ends_at',
        'ended_at',
        'last_stripe_event_at',
        'needs_review',
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
            'ended_at' => 'datetime',
            'last_stripe_event_at' => 'datetime',
            'needs_review' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The user who started or last changed it; null for webhooks and the platform.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<PlanPrice, $this>
     */
    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
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
        if (in_array($this->status, ['incomplete', 'incomplete_expired', 'unpaid'], true)) {
            return false;
        }

        $endsAt = $this->accessEndsAt();

        if (in_array($this->status, ['active', 'trialing'], true)) {
            if ($this->cancel_at_period_end && $endsAt && $endsAt->isPast()) {
                return false;
            }

            if ($this->source === 'trial' && $endsAt && $endsAt->isPast()) {
                return false;
            }

            return true;
        }

        if ($this->status === 'canceled' || $this->cancel_at_period_end) {
            return $endsAt !== null && $endsAt->isFuture();
        }

        if ($this->status === 'past_due') {
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

        return ($this->status === 'canceled' || $this->cancel_at_period_end)
            && $endsAt !== null
            && $endsAt->isFuture();
    }

    public function onTrial(): bool
    {
        return $this->status === 'trialing'
            && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture());
    }

    public function active(): bool
    {
        return $this->isAccessible() && in_array($this->status, ['active', 'trialing'], true);
    }

    public function canceled(): bool
    {
        return $this->status === 'canceled' || $this->cancel_at_period_end;
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
        if ($this->source === 'trial') {
            return $this->isAccessible() || $this->justExpired();
        }

        if ($this->source === 'admin' && $this->ends_at) {
            return true;
        }

        if ($this->cancel_at_period_end || $this->status === 'canceled') {
            return true;
        }

        if ($this->status === 'past_due') {
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
        return $query->whereIn('status', ['active', 'trialing']);
    }

    public function scopeCanceled(Builder $query): Builder
    {
        return $query->where('status', 'canceled');
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
                $q->whereIn('status', ['active', 'trialing'])
                    ->where(function (Builder $inner) use ($endsAt, $now) {
                        $inner->where('cancel_at_period_end', false)
                            ->orWhereRaw("{$endsAt} IS NULL")
                            ->orWhereRaw("{$endsAt} >= ?", [$now]);
                    })
                    ->where(function (Builder $inner) use ($endsAt, $now) {
                        $inner->where('source', '!=', 'trial')
                            ->orWhereRaw("{$endsAt} IS NULL")
                            ->orWhereRaw("{$endsAt} >= ?", [$now]);
                    });
            })->orWhere(function (Builder $q) use ($endsAt, $now) {
                $q->whereNotIn('status', ['active', 'trialing', 'incomplete', 'incomplete_expired', 'unpaid'])
                    ->where(function (Builder $inner) {
                        $inner->where('status', 'canceled')
                            ->orWhere('cancel_at_period_end', true);
                    })
                    ->whereRaw("{$endsAt} > ?", [$now]);
            })->orWhere(function (Builder $q) use ($endsAt, $now) {
                $q->where('status', 'past_due')
                    ->where('cancel_at_period_end', false)
                    ->where(function (Builder $inner) use ($endsAt, $now) {
                        $inner->whereRaw("{$endsAt} IS NULL")
                            ->orWhereRaw("{$endsAt} > ?", [$now]);
                    });
            });
        });
    }
}
