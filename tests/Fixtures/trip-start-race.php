<?php

/*
| Child process for Trips/TripStartConcurrencyTest: starts one assigned trip through
| TripLifecycleService at the same moment as its sibling. Prints "started" or the error.
|
| php tests/Fixtures/trip-start-race.php <database> <company id> <trip id> <start at, unix seconds>
*/

use App\Models\Company;
use App\Models\Trip;
use App\Services\Operations\TripLifecycleService;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

[, $database, $companyId, $tripId, $startAt] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'cache.default' => 'array']);
DB::purge('mysql');
DB::connection()->getPdo();

$company = Company::query()->findOrFail((int) $companyId);

if ((float) $startAt > microtime(true)) {
    time_sleep_until((float) $startAt);
}

try {
    app(TenantContext::class)->run($company->id, fn () => app(TripLifecycleService::class)->start(Trip::query()->findOrFail((int) $tripId), $company->owner));
    echo 'started';
} catch (ValidationException $e) {
    echo collect($e->errors())->flatten()->first();
}
