<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Folder;
use App\Models\Trailer;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Documents\FolderService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Run on deploy: the standard folder of every driver, vehicle, trailer and trip that existed
 * before its folder did. Retired (soft-deleted) ones too, since their history stays viewable.
 * Public applicants get their DQ file at hire. Safe to run again.
 */
class EnsureSystemFolders extends Command
{
    protected $signature = 'folders:ensure-system {company? : Only this company id}';

    protected $description = 'Create the missing DQ file, vehicle, trailer and trip folders';

    public function handle(FolderService $folders, TenantContext $tenant): int
    {
        $created = 0;
        $companies = Company::query()
            ->when($this->argument('company') !== null, fn ($q) => $q->whereKey((int) $this->argument('company')))
            ->lazyById(200);

        foreach ($companies as $company) {
            $tenant->run($company->id, function () use ($folders, &$created) {
                $drivers = Driver::withTrashed()
                    ->where(fn (Builder $q) => $q->where('source', 'admin')->orWhere('employment_status', '<>', 'applicant'));

                foreach ([$drivers, Vehicle::withTrashed(), Trailer::withTrashed(), Trip::withTrashed()] as $query) {
                    $column = $query->getModel()->getForeignKey();

                    $query->whereNotExists(fn (QueryBuilder $q) => $q->from('folders')
                        ->whereColumn("folders.{$column}", $query->getModel()->getQualifiedKeyName())
                        ->where('folders.system_key', Folder::BINDINGS[$column]))
                        ->chunkById(200, function ($entities) use ($folders, &$created) {
                            DB::transaction(fn () => $entities->each(fn ($entity) => $folders->ensureSystemFolder($entity)));
                            $created += $entities->count();
                        });
                }
            });
        }

        $this->info("{$created} system folder(s) created.");

        return self::SUCCESS;
    }
}
