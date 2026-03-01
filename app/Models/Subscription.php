<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Subscription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'grace_ends_at',
        'cancelled_at',
        'last_renewed_at',
        'auto_renew',
        'payment_method',
        'external_subscription_id',
        'metadata',
    ];

    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'trial_ends_at'  => 'datetime',
        'grace_ends_at'  => 'datetime',
        'cancelled_at'   => 'datetime',
        'last_renewed_at' => 'datetime',
        'auto_renew'     => 'boolean',
        'metadata'       => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

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

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'trial', 'grace']);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired');
    }

    public function scopeExpiringSoon(Builder $query, int $days = 7): Builder
    {
        return $query->active()
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->addDays($days)]);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    // ─── Status Checkers ──────────────────────────────────────────────────────

    /**
     * Whether the user can access the system right now.
     */
    public function isAccessible(): bool
    {
        return match ($this->status) {
            'active' => $this->ends_at === null || $this->ends_at->isFuture(),
            'trial'  => $this->trial_ends_at !== null && $this->trial_ends_at->isFuture(),
            'grace'  => $this->grace_ends_at !== null && $this->grace_ends_at->isFuture(),
            default  => false,
        };
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isFuture();
    }

    public function isInGrace(): bool
    {
        return $this->status === 'grace' && $this->grace_ends_at?->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->ends_at !== null && $this->ends_at->isPast() && !$this->isInGrace());
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isLifetime(): bool
    {
        return $this->ends_at === null && $this->status === 'active';
    }

    /**
     * Days remaining until expiry (returns null for lifetime).
     */
    public function daysRemaining(): ?int
    {
        if ($this->isLifetime()) {
            return null;
        }

        $end = $this->ends_at ?? $this->trial_ends_at ?? $this->grace_ends_at;

        if (!$end || $end->isPast()) {
            return 0;
        }

        return (int) now()->diffInDays($end);
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        $remaining = $this->daysRemaining();
        return $remaining !== null && $remaining <= $days && $remaining > 0;
    }

    // ─── Status Label ─────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active'    => 'Active',
            'trial'     => 'Trial',
            'grace'     => 'Grace Period',
            'expired'   => 'Expired',
            'cancelled' => 'Cancelled',
            'suspended' => 'Suspended',
            default     => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active'    => 'success',
            'trial'     => 'info',
            'grace'     => 'warning',
            'expired'   => 'danger',
            'cancelled' => 'secondary',
            'suspended' => 'dark',
            default     => 'secondary',
        };
    }
}
