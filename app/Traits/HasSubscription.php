<?php

namespace App\Traits;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasSubscription
{
    // ─── Relationships ────────────────────────────────────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The latest/current subscription (regardless of status).
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latest();
    }

    /**
     * Only the active subscription.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'trial', 'grace'])
            ->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ─── Subscription Helpers ─────────────────────────────────────────────────

    /**
     * Check if user has an accessible (non-expired) subscription.
     * This is the MAIN check used throughout the app.
     */
    public function hasActiveSubscription(): bool
    {
        $sub = $this->activeSubscription;
        return $sub !== null && $sub->isAccessible();
    }

    /**
     * Get the current accessible subscription or null.
     */
    public function currentSubscription(): ?Subscription
    {
        $sub = $this->activeSubscription;
        return ($sub && $sub->isAccessible()) ? $sub : null;
    }

    /**
     * Check if user is subscribed to a specific plan.
     */
    public function subscribedTo(string $planSlug): bool
    {
        $sub = $this->currentSubscription();
        return $sub && $sub->plan->slug === $planSlug;
    }

    /**
     * Check if user is on trial.
     */
    public function onTrial(): bool
    {
        return $this->activeSubscription?->isOnTrial() ?? false;
    }

    /**
     * Check if user's subscription is expiring soon.
     */
    public function subscriptionExpiringSoon(int $days = 7): bool
    {
        return $this->activeSubscription?->isExpiringSoon($days) ?? false;
    }

    /**
     * Days remaining on subscription.
     */
    public function subscriptionDaysRemaining(): ?int
    {
        return $this->activeSubscription?->daysRemaining();
    }
}
