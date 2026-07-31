<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TrialActivationService
{
    public function activate(User $user, Plan $plan): Subscription
    {
        if (! $plan->is_active) {
            throw new InvalidArgumentException('This plan is not available.');
        }

        if (! $plan->isTrial() || ! $plan->isFree()) {
            throw new InvalidArgumentException('Only free trial plans can be activated without a card.');
        }

        if ((int) $plan->trial_days < 1) {
            throw new InvalidArgumentException('Trial plan must define trial_days.');
        }

        if ($user->hasActiveSubscription()) {
            throw new InvalidArgumentException('You already have an active subscription.');
        }

        if ($user->hasUsedTrial()) {
            throw new InvalidArgumentException('You have already used your free trial.');
        }

        $endsAt = now()->addDays((int) $plan->trial_days);

        return DB::transaction(function () use ($user, $plan, $endsAt) {
            return Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'stripe_subscription_id' => null,
                'stripe_status' => 'trialing',
                'billing_cycle' => 'trial',
                'amount' => 0,
                'currency' => strtoupper($plan->currency ?: 'USD'),
                'trial_ends_at' => $endsAt,
                'current_period_start' => now(),
                'current_period_end' => $endsAt,
                'cancel_at_period_end' => true,
                'ends_at' => $endsAt,
                'source' => 'trial',
            ]);
        });
    }
}
