<?php

namespace App\Models;

use App\Services\Billing\AccessState;
use App\Services\Billing\AccessStateResolver;
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
 * @property Carbon|null $ended_at
 * @property Carbon|null $last_stripe_event_at
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

    public function planName(): string
    {
        return $this->planPrice->plan->name ?? 'your plan';
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

    /**
     * When access ends (07 §4.4). A converted trial no longer falls back to its trial date.
     */
    public function accessEndsAt(): ?CarbonInterface
    {
        if ($this->status === 'trialing') {
            return $this->trial_ends_at;
        }

        if ($this->status === 'canceled' || $this->cancel_at_period_end) {
            return $this->ends_at ?? $this->current_period_end;
        }

        return $this->current_period_end;
    }

    /**
     * The state this row grants (07 §4.3); None when it never granted access (incomplete).
     */
    public function accessState(int $graceDays): AccessState
    {
        if ($this->ended_at !== null) {
            return AccessState::ReadOnly;
        }

        $granted = match ($this->status) {
            'incomplete', 'incomplete_expired' => null,
            'trialing' => $this->trial_ends_at === null || $this->trial_ends_at->isFuture(),
            'active' => $this->periodLeft(),
            'canceled' => (bool) $this->accessEndsAt()?->isFuture(),
            'past_due' => $this->periodLeft() && (bool) $this->graceEndsAt($graceDays)?->isFuture(),
            default => false,
        };

        return match ($granted) {
            null => AccessState::None,
            false => AccessState::ReadOnly,
            true => $this->status === 'past_due' ? AccessState::Grace : AccessState::Full,
        };
    }

    public function isAccessible(?int $graceDays = null): bool
    {
        return $this->accessState($graceDays ?? AccessStateResolver::graceDays())->allowsWrites();
    }

    /**
     * Grace runs from the failed renewal: Stripe starts the new period when it creates the
     * renewal invoice, so that is current_period_start [A].
     */
    public function graceEndsAt(int $graceDays): ?CarbonInterface
    {
        return $this->current_period_start?->copy()->addDays($graceDays);
    }

    /**
     * When the company drops to read-only, for reminders; null while it renews on its own.
     */
    public function readOnlyAt(int $graceDays): ?CarbonInterface
    {
        if ($this->status === 'past_due') {
            return $this->graceEndsAt($graceDays);
        }

        if ($this->status === 'trialing' || $this->status === 'canceled'
            || $this->cancel_at_period_end || $this->source !== 'stripe') {
            return $this->accessEndsAt();
        }

        return null;
    }

    /**
     * A cancelling row with time left, which the company can still resume.
     */
    public function canResume(): bool
    {
        return $this->ended_at === null
            && $this->cancel_at_period_end
            && (bool) $this->accessEndsAt()?->isFuture();
    }

    public function onTrial(): bool
    {
        return $this->status === 'trialing'
            && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture());
    }

    /**
     * A Stripe row renews by itself; a cancelling or local (trial, admin) row ends at its date.
     */
    private function periodLeft(): bool
    {
        if (! $this->cancel_at_period_end && $this->source === 'stripe') {
            return true;
        }

        $endsAt = $this->accessEndsAt();

        return $endsAt === null || $endsAt->isFuture();
    }

    /**
     * SQL mirror of isAccessible() (full or grace); keep the two in sync.
     */
    public function scopeAccessible(Builder $query, ?int $graceDays = null): Builder
    {
        $now = now();
        $graceStart = $now->copy()->subDays($graceDays ?? AccessStateResolver::graceDays());
        // Same branches as accessEndsAt() for a row that is not trialing.
        $endsAt = 'CASE WHEN cancel_at_period_end = 1 OR status = \'canceled\' THEN COALESCE(ends_at, current_period_end) ELSE current_period_end END';
        $periodLeft = function (Builder $q) use ($endsAt, $now) {
            $q->where(fn (Builder $q) => $q->where('cancel_at_period_end', false)->where('source', 'stripe'))
                ->orWhereRaw("({$endsAt}) IS NULL")
                ->orWhereRaw("({$endsAt}) > ?", [$now]);
        };

        return $query->whereNull('ended_at')->where(function (Builder $q) use ($endsAt, $now, $periodLeft, $graceStart) {
            $q->where(fn (Builder $q) => $q->where('status', 'trialing')
                ->where(fn (Builder $q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>', $now)))
                ->orWhere(fn (Builder $q) => $q->where('status', 'active')->where($periodLeft))
                ->orWhere(fn (Builder $q) => $q->where('status', 'canceled')->whereRaw("({$endsAt}) > ?", [$now]))
                ->orWhere(fn (Builder $q) => $q->where('status', 'past_due')->where($periodLeft)
                    ->where('current_period_start', '>', $graceStart));
        });
    }
}
