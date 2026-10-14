<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\SiteSetting;
use App\Models\Subscription;

/**
 * Resolves a company's access state (07 §4.3). Bound scoped, so the memo lives for one
 * request or job; forget() after a billing change in the same request.
 */
class AccessStateResolver
{
    /** @var array<int, AccessState> */
    private array $memo = [];

    /** @var array<int, Subscription|null> */
    private array $open = [];

    public function for(Company $company): AccessState
    {
        return $this->memo[$company->id] ??= $this->resolve($company);
    }

    /**
     * The company's open subscription, loaded once per request.
     */
    public function openSubscription(Company $company): ?Subscription
    {
        if (! array_key_exists($company->id, $this->open)) {
            $this->open[$company->id] = $company->subscriptions()->open()->first();
        }

        return $this->open[$company->id];
    }

    public function forget(int $companyId): void
    {
        unset($this->memo[$companyId], $this->open[$companyId]);
    }

    public static function graceDays(): int
    {
        return (int) (SiteSetting::getSettings()->grace_days ?? 7);
    }

    private function resolve(Company $company): AccessState
    {
        if ($company->status !== 'active') {
            return AccessState::Blocked;
        }

        $state = $this->openSubscription($company)?->accessState(self::graceDays()) ?? AccessState::None;

        // A company that had a subscription keeps read-only access to its records (Q2, [L]).
        if ($state === AccessState::None
            && $company->subscriptions()->whereNotNull('ended_at')->exists()) {
            return AccessState::ReadOnly;
        }

        return $state;
    }

    /**
     * The open subscription when it grants full or grace access.
     */
    public static function accessibleSubscription(Company $company): ?Subscription
    {
        $open = $company->subscriptions()->open()->first();

        return $open?->isAccessible() ? $open : null;
    }
}
