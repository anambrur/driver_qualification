<?php

/*
| Child process for Limits/ConcurrencyTest: creates one vehicle behind the max_vehicles check,
| starting at the same moment as its sibling. Prints "created" or "limit".
|
| php tests/Fixtures/limit-race.php <database> <company id> <start at, unix seconds>
*/

use App\Exceptions\LimitReached;
use App\Features\Feature;
use App\Models\Company;
use App\Services\Entitlements\Limits;
use App\Tenancy\TenantContext;
use Database\Factories\VehicleFactory;
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

try {
    DB::transaction(function () use ($companyId) {
        Limits::assertCanCreate((int) $companyId, Feature::MaxVehicles);

        // Hold the company lock while the sibling waits on it.
        usleep(300_000);

        $company = Company::query()->findOrFail((int) $companyId);
        app(TenantContext::class)->run($company->id, fn () => VehicleFactory::new()->forCompany($company)->create());
    });

    echo 'created';
} catch (LimitReached) {
    echo 'limit';
}
