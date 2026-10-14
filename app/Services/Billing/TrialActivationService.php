<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PlanPrice;
use App\Models\SiteSetting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * One free trial per company and per verified owner email (PO-15, 07 §5), on the top tier
 * (Q5). companies.trial_email is unique, so two companies can never claim the same email.
 */
class TrialActivationService
{
    public function __construct(
        private readonly SubscriptionNotificationService $notifications,
    ) {}

    public function activate(Company $company, User $actor): Subscription
    {
        $settings = SiteSetting::getSettings();
        $price = PlanPrice::query()->current()
            ->where('plan_id', $settings->trial_plan_id)
            ->where('interval', 'month')
            ->whereHas('plan', fn ($q) => $q->where('is_active', true))
            ->first();
        $days = (int) $settings->trial_days;

        if (! $price || $days < 1) {
            throw new InvalidArgumentException('The free trial is not available right now.');
        }

        try {
            $subscription = DB::transaction(function () use ($company, $actor, $price, $days) {
                $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
                $owner = $company->owner;

                if (! $owner?->hasVerifiedEmail()) {
                    throw new InvalidArgumentException('The company owner must verify their email before starting a trial.');
                }

                if ($company->trial_used_at !== null
                    || Company::query()->where('trial_email', $owner->email)->exists()) {
                    throw new InvalidArgumentException('The free trial has already been used.');
                }

                if (AccessStateResolver::accessibleSubscription($company)) {
                    throw new InvalidArgumentException('Your company already has an active subscription.');
                }

                // A lapsed open row becomes history; the trial is the open row now.
                $company->subscriptions()->open()->update(['ended_at' => now()]);
                $company->forceFill(['trial_used_at' => now(), 'trial_email' => $owner->email])->save();

                $endsAt = now()->addDays($days);

                $subscription = Subscription::query()->create([
                    'company_id' => $company->id,
                    'actor_user_id' => $actor->id,
                    'plan_price_id' => $price->id,
                    'interval' => $price->interval,
                    'amount' => 0,
                    'currency' => $price->currency,
                    'status' => 'trialing',
                    'source' => 'trial',
                    'trial_ends_at' => $endsAt,
                    'current_period_start' => now(),
                    'current_period_end' => $endsAt,
                    'cancel_at_period_end' => true,
                    'ends_at' => $endsAt,
                ]);

                $company->increment('entitlements_version');
                AuditLog::record($actor, $company->id, 'subscription.trial_started', $subscription, [
                    'plan_price_id' => $price->id,
                    'trial_ends_at' => $endsAt->toIso8601String(),
                ]);

                return $subscription;
            });
        } catch (UniqueConstraintViolationException) {
            // Another request claimed this email at the same moment.
            throw new InvalidArgumentException('The free trial has already been used.');
        }

        app(AccessStateResolver::class)->forget($company->id);
        $this->notifications->sendActivated($subscription);

        return $subscription;
    }
}
