<?php

namespace App\Services\Billing;

use App\Models\Company;

/**
 * What a company may do, shared by all its users and drivers (07 §4.3).
 */
enum AccessState: string
{
    case Full = 'full';
    case Grace = 'grace';
    case ReadOnly = 'read_only';
    case None = 'none';
    case Blocked = 'blocked';

    public static function for(Company $company): self
    {
        return app(AccessStateResolver::class)->for($company);
    }

    /**
     * Full or grace: every permission the user holds applies.
     */
    public function allowsWrites(): bool
    {
        return $this === self::Full || $this === self::Grace;
    }

    /**
     * Tenant pages open (read-only included); none and blocked go to pricing or login.
     */
    public function allowsTenantPages(): bool
    {
        return $this->allowsWrites() || $this === self::ReadOnly;
    }
}
