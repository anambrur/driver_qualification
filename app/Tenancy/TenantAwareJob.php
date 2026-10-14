<?php

namespace App\Tenancy;

/**
 * A queued job that runs as one company; pair it with the TenantAware job middleware.
 */
interface TenantAwareJob
{
    public function companyId(): int;
}
