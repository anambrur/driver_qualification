<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PlanPrice;
use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Services\Stripe\SubscriptionService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Platform billing operations keyed by company (07 §7). WP8 puts the screens under /platform.
 */
class SubscriptionAdminService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly SubscriptionNotificationService $notifications,
    ) {}

    /**
     * A complimentary, time-bounded grant (PO-19: never open-ended), only while nothing is accessible.
     */
    public function grant(PlatformUser $actor, Company $company, PlanPrice $price, CarbonInterface $endsAt): Subscription
    {
        if (! $endsAt->isFuture()) {
            throw new InvalidArgumentException('A grant needs an end date in the future.');
        }

        $subscription = DB::transaction(function () use ($actor, $company, $price, $endsAt) {
            Company::query()->whereKey($company->id)->lockForUpdate()->first();

            if (AccessStateResolver::accessibleSubscription($company)) {
                throw new InvalidArgumentException('The company already has an active subscription. Expire it first.');
            }

            $company->subscriptions()->open()->update(['ended_at' => now()]);

            $subscription = Subscription::query()->create([
                'company_id' => $company->id,
                'plan_price_id' => $price->id,
                'interval' => $price->interval,
                'amount' => 0,
                'currency' => $price->currency,
                'status' => 'active',
                'source' => 'admin',
                'current_period_start' => now(),
                'current_period_end' => $endsAt,
                'cancel_at_period_end' => true,
                'ends_at' => $endsAt,
            ]);

            $this->audit($actor, $company, 'subscription.granted', $subscription, [
                'plan_price_id' => $price->id,
                'ends_at' => $endsAt->toIso8601String(),
            ]);

            return $subscription;
        });

        $this->notifications->sendActivated($subscription);

        return $subscription;
    }

    public function expireNow(PlatformUser $actor, Company $company): Subscription
    {
        return $this->change($actor, $company, 'subscription.expired', fn (Subscription $s) => $this->subscriptions->cancelNow($s));
    }

    /**
     * Cancel at period end; a local row keeps its original end date.
     */
    public function suspend(PlatformUser $actor, Company $company): Subscription
    {
        return $this->change($actor, $company, 'subscription.suspended', fn (Subscription $s) => $this->subscriptions->cancelAtPeriodEnd($s));
    }

    public function reactivate(PlatformUser $actor, Company $company): Subscription
    {
        return $this->change($actor, $company, 'subscription.reactivated', fn (Subscription $s) => $this->subscriptions->resume($s));
    }

    public function moveToPrice(PlatformUser $actor, Company $company, PlanPrice $price): Subscription
    {
        return $this->change($actor, $company, 'subscription.price_moved', fn (Subscription $s) => $this->subscriptions->moveToPrice($s, $price));
    }

    /**
     * @param  callable(Subscription): Subscription  $apply
     */
    private function change(PlatformUser $actor, Company $company, string $action, callable $apply): Subscription
    {
        $subscription = $company->subscriptions()->open()->first()
            ?? throw new InvalidArgumentException('The company has no open subscription.');
        $before = $subscription->only(['status', 'cancel_at_period_end', 'ends_at', 'ended_at', 'plan_price_id']);

        $after = $apply($subscription);

        $this->audit($actor, $company, $action, $after, [
            'before' => $before,
            'after' => $after->only(array_keys($before)),
        ]);

        return $after;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function audit(PlatformUser $actor, Company $company, string $action, Subscription $subscription, array $changes): void
    {
        $company->increment('entitlements_version');
        app(AccessStateResolver::class)->forget($company->id);
        AuditLog::record($actor, $company->id, $action, $subscription, $changes);
    }
}
