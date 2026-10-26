<?php

/*
| Child process for Trips/TripNumberConcurrencyTest: creates one trip through TripService,
| starting at the same moment as its sibling. Prints the trip number it got.
|
| php tests/Fixtures/trip-number-race.php <database> <company id> <start at, unix seconds>
*/

use App\Models\Company;
use App\Services\Operations\TripService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

[, $database, $companyId, $startAt] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'cache.default' => 'array']);
DB::purge('mysql');
DB::connection()->getPdo();

if ((float) $startAt > microtime(true)) {
    time_sleep_until((float) $startAt);
}

$company = Company::query()->findOrFail((int) $companyId);

echo app(TenantContext::class)->run($company->id, fn () => app(TripService::class)->create(['weight_unit' => 'lb'], $company->owner))->trip_number;
