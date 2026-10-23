<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\Company\CompanyPurgeService;
use Illuminate\Console\Command;

/**
 * Permanently deletes a deactivated company after its retention periods (08 §15, 10 §11).
 * Without --confirm it only reports what would be deleted and what still blocks it.
 */
class PurgeCompany extends Command
{
    protected $signature = 'companies:purge {company : The company id} {--confirm : Delete for real}';

    protected $description = 'Permanently delete a deactivated company after its retention periods';

    public function handle(CompanyPurgeService $purge): int
    {
        $company = Company::query()->find((int) $this->argument('company'));

        if ($company === null) {
            $this->error('No company with that id.');

            return self::FAILURE;
        }

        $this->info("Company #{$company->id}: {$company->company_name} ({$company->status})");
        $this->table(['Table', 'Rows'], collect($purge->counts($company))->map(fn ($rows, $table) => [$table, $rows])->values()->all());

        if ($blockers = $purge->blockers($company)) {
            foreach ($blockers as $blocker) {
                $this->error($blocker);
            }

            return self::FAILURE;
        }

        if (! $this->option('confirm')) {
            $this->warn('Dry run: nothing deleted. Run again with --confirm to purge.');

            return self::SUCCESS;
        }

        $deleted = $purge->purge($company);
        $this->info('Purged: '.collect($deleted)->map(fn ($rows, $table) => "{$table} {$rows}")->implode(', '));

        return self::SUCCESS;
    }
}
