<?php

namespace App\Console\Commands;

use App\Services\Authorization\CompanyRoleSync;
use Illuminate\Console\Command;

/**
 * Run after PermissionSeeder on deploy, so new permissions reach every company's built-in roles.
 */
class SyncCompanyRoles extends Command
{
    protected $signature = 'roles:sync-company {company? : Only this company id}';

    protected $description = "Bring every company's built-in roles up to the role templates (edited roles are kept)";

    public function handle(CompanyRoleSync $sync): int
    {
        $company = $this->argument('company');
        $changed = $company !== null ? $sync->sync((int) $company) : $sync->syncAll();

        $this->info("{$changed} role(s) updated.");

        return self::SUCCESS;
    }
}
