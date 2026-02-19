<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Events\SubscriptionExpired;
use App\Events\SubscriptionExpiringSoon;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /** Grace period in days after expiry before full lockout */
    const GRACE_PERIOD_DAYS = 3;

    /** Days before expiry to send warning notifications */
    const EXPIRY_WARNING_DAYS = [7, 3, 1];

    // ─── Create / Purchase ────────────────────────────────────────────────────

    /**
     * Subscribe a user to a plan. Handles trial, paid, and lifetime plans.
     *
     * @param  array $paymentData  ['method'=>'manual','transaction_id'=>'...','amount'=>99]
     */
    public function subscribe(User $user, Plan $plan, array $paymentData = []): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $paymentData) {
            // Cancel any existing active subscription
            $this->cancelExistingSubscriptions($user);

            $now      = now();
            $isTrial  = $plan->isTrial() || (!empty($paymentData['is_trial']));
            $status   = $isTrial ? 'trial' : 'active';

            // Calculate dates
            $startsAt      = $now;
            $endsAt        = $plan->isLifetime() ? null : $now->copy()->addDays($plan->duration_days);
            $trialEndsAt   = $isTrial ? $now->copy()->addDays($plan->trial_days ?: $plan->duration_days) : null;
            $graceEndsAt   = $endsAt?->copy()->addDays(self::GRACE_PERIOD_DAYS);

            $subscription = Subscription::create([
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'status'         => $status,
                'starts_at'      => $startsAt,
                'ends_at'        => $isTrial ? null : $endsAt,
                'trial_ends_at'  => $trialEndsAt,
                'grace_ends_at'  => $isTrial ? null : $graceEndsAt,
                'last_renewed_at'=> $now,
                'payment_method' => $paymentData['method'] ?? null,
                'auto_renew'     => $paymentData['auto_renew'] ?? false,
                'external_subscription_id' => $paymentData['external_id'] ?? null,
            ]);

            // Record payment (skip for free/trial plans)
            if (!$plan->isFree() && !$isTrial && !empty($paymentData)) {
                $this->recordPayment($user, $subscription, $plan, $paymentData, 'paid');
            }

            Log::info("Subscription created", [
                'user_id' => $user->id,
                'plan'    => $plan->slug,
                'status'  => $status,
                'ends_at' => $endsAt,
            ]);

            return $subscription->fresh(['plan', 'user']);
        });
    }

    /**
     * Renew an existing subscription for the same plan.
     */
    public function renew(Subscription $subscription, array $paymentData = []): Subscription
    {
        return DB::transaction(function () use ($subscription, $paymentData) {
            $plan = $subscription->plan;
            $now  = now();

            // If still active, extend from end; otherwise from now
            $base    = ($subscription->ends_at && $subscription->ends_at->isFuture())
                       ? $subscription->ends_at
                       : $now;

            $endsAt      = $plan->isLifetime() ? null : $base->copy()->addDays($plan->duration_days);
            $graceEndsAt = $endsAt?->copy()->addDays(self::GRACE_PERIOD_DAYS);

            $subscription->update([
                'status'         => 'active',
                'ends_at'        => $endsAt,
                'grace_ends_at'  => $graceEndsAt,
                'last_renewed_at'=> $now,
                'cancelled_at'   => null,
            ]);

            if (!$plan->isFree() && !empty($paymentData)) {
                $this->recordPayment(
                    $subscription->user,
                    $subscription,
                    $plan,
                    $paymentData,
                    'paid'
                );
            }

            Log::info("Subscription renewed", [
                'subscription_id' => $subscription->id,
                'new_ends_at'     => $endsAt,
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Upgrade or downgrade to a different plan.
     */
    public function changePlan(Subscription $subscription, Plan $newPlan, array $paymentData = []): Subscription
    {
        return DB::transaction(function () use ($subscription, $newPlan, $paymentData) {
            // Cancel old, create new
            $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            return $this->subscribe($subscription->user, $newPlan, $paymentData);
        });
    }

    /**
     * Cancel subscription (marks as cancelled; user keeps access until end_at).
     */
    public function cancel(Subscription $subscription, bool $immediately = false): Subscription
    {
        $subscription->update([
            'status'       => $immediately ? 'expired' : 'cancelled',
            'cancelled_at' => now(),
            'auto_renew'   => false,
            'ends_at'      => $immediately ? now() : $subscription->ends_at,
        ]);

        Log::info("Subscription cancelled", [
            'subscription_id' => $subscription->id,
            'immediately'     => $immediately,
        ]);

        return $subscription->fresh();
    }

    /**
     * Suspend a subscription (admin action).
     */
    public function suspend(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => 'suspended']);

        Log::warning("Subscription suspended", ['subscription_id' => $subscription->id]);

        return $subscription->fresh();
    }

    /**
     * Reactivate a suspended subscription.
     */
    public function reactivate(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status'       => $subscription->ends_at?->isFuture() ? 'active' : 'expired',
            'cancelled_at' => null,
        ]);

        return $subscription->fresh();
    }

    // ─── Scheduled Checks ─────────────────────────────────────────────────────

    /**
     * Mark expired subscriptions. Run via scheduler daily.
     * Returns count of subscriptions marked expired.
     */
    public function processExpiredSubscriptions(): int
    {
        $count = 0;

        // Active subscriptions whose end_at has passed grace period
        $expired = Subscription::whereIn('status', ['active', 'grace'])
            ->whereNotNull('ends_at')
            ->where('grace_ends_at', '<', now())
            ->with('user')
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);
            event(new SubscriptionExpired($subscription));
            $count++;

            Log::info("Subscription expired", ['subscription_id' => $subscription->id]);
        }

        // Move active subscriptions that passed ends_at into grace period
        Subscription::where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->where('grace_ends_at', '>', now())
            ->update(['status' => 'grace']);

        // Expire trial subscriptions
        $trialExpired = Subscription::where('status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->update(['status' => 'expired']);

        $count += $trialExpired;

        return $count;
    }

    /**
     * Send expiry warning notifications. Run via scheduler daily.
     */
    public function sendExpiryWarnings(): void
    {
        foreach (self::EXPIRY_WARNING_DAYS as $days) {
            $subscriptions = Subscription::active()
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [
                    now()->startOfDay()->addDays($days),
                    now()->endOfDay()->addDays($days),
                ])
                ->with('user', 'plan')
                ->get();

            foreach ($subscriptions as $subscription) {
                event(new SubscriptionExpiringSoon($subscription, $days));
            }
        }
    }

    // ─── Admin: Manual Payment ────────────────────────────────────────────────

    /**
     * Admin grants subscription manually (e.g., after bank transfer).
     */
    public function grantManually(
        User $user,
        Plan $plan,
        string $notes = '',
        ?Carbon $customEndDate = null
    ): Subscription {
        return DB::transaction(function () use ($user, $plan, $notes, $customEndDate) {
            $this->cancelExistingSubscriptions($user);

            $now    = now();
            $endsAt = $customEndDate ?? ($plan->isLifetime() ? null : $now->copy()->addDays($plan->duration_days));

            $subscription = Subscription::create([
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'status'         => 'active',
                'starts_at'      => $now,
                'ends_at'        => $endsAt,
                'grace_ends_at'  => $endsAt?->copy()->addDays(self::GRACE_PERIOD_DAYS),
                'last_renewed_at'=> $now,
                'payment_method' => 'manual',
            ]);

            // Create a paid payment record
            $this->recordPayment($user, $subscription, $plan, [
                'method' => 'manual',
                'notes'  => $notes ?: 'Manually granted by admin',
                'amount' => $plan->price,
            ], 'paid');

            Log::info("Subscription manually granted", [
                'user_id'    => $user->id,
                'plan'       => $plan->slug,
                'granted_by' => auth()->id(),
            ]);

            return $subscription->fresh(['plan', 'user']);
        });
    }

    // ─── Internals ────────────────────────────────────────────────────────────

    private function cancelExistingSubscriptions(User $user): void
    {
        Subscription::forUser($user->id)
            ->whereIn('status', ['active', 'trial', 'grace'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    private function recordPayment(
        User $user,
        Subscription $subscription,
        Plan $plan,
        array $data,
        string $status = 'paid'
    ): Payment {
        return Payment::create([
            'user_id'          => $user->id,
            'subscription_id'  => $subscription->id,
            'plan_id'          => $plan->id,
            'invoice_number'   => Payment::generateInvoiceNumber(),
            'amount'           => $data['amount'] ?? $plan->price,
            'currency'         => $plan->currency,
            'status'           => $status,
            'payment_method'   => $data['method'] ?? null,
            'transaction_id'   => $data['transaction_id'] ?? null,
            'gateway_response' => $data['gateway_response'] ?? null,
            'paid_at'          => $status === 'paid' ? now() : null,
            'notes'            => $data['notes'] ?? null,
        ]);
    }
}
