<?php

use App\Features\Feature;
use App\Services\Entitlements\Limits;
use App\Services\Entitlements\PlanFeatureService;
use Database\Factories\VehicleFactory;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\Feature\Modules\Entitlements\Entitled;
use Tests\Support\Actors;

/*
| 05 §5 step 3, on MySQL: two parallel creates at limit - 1 cannot both pass, because every
| path locks the company row first. Two real PHP processes race on a throwaway database
| (local MySQL from .env, like PermissionScaleTest), which is dropped afterwards.
*/

const LIMITS_DB = 'dq_limits_concurrency';

beforeEach(function () {
    $mysql = config('database.connections.mysql');
    config([
        'database.connections.limits_admin' => array_merge($mysql, ['database' => null]),
        'database.connections.limits' => array_merge($mysql, ['database' => LIMITS_DB]),
    ]);
    DB::connection('limits_admin')->statement('DROP DATABASE IF EXISTS `'.LIMITS_DB.'`');
    DB::connection('limits_admin')->statement('CREATE DATABASE `'.LIMITS_DB.'`');

    $this->previousConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('limits');
    Artisan::call('migrate', ['--database' => 'limits', '--force' => true]);
    (new PermissionSeeder)->run();
})->group('mysql');

afterEach(function () {
    DB::setDefaultConnection($this->previousConnection);
    DB::purge('limits');
    DB::connection('limits_admin')->statement('DROP DATABASE IF EXISTS `'.LIMITS_DB.'`');
    DB::purge('limits_admin');
});

it('lets only one of two parallel creates at limit - 1 pass', function () {
    $company = Actors::company();
    VehicleFactory::new()->forCompany($company)->create();
    app(PlanFeatureService::class)->set(Actors::platformOwner(), Entitled::planOf($company), Feature::MaxVehicles, true, 2);

    $startAt = sprintf('%.3f', microtime(true) + 3);
    $command = fn () => [PHP_BINARY, base_path('tests/Fixtures/limit-race.php'), LIMITS_DB, (string) $company->id, $startAt];
    $first = Process::timeout(60)->start($command());
    $second = Process::timeout(60)->start($command());
    $results = [$first->wait(), $second->wait()];

    foreach ($results as $result) {
        expect($result->successful())->toBeTrue($result->errorOutput());
    }

    $outputs = array_map(fn ($result) => trim($result->output()), $results);
    sort($outputs);

    expect($outputs)->toBe(['created', 'limit'])
        ->and(DB::table('vehicles')->where('company_id', $company->id)->whereNull('deleted_at')->count())->toBe(2);
})->group('mysql');

it('refuses to check a limit outside a transaction', function () {
    $company = Actors::company();

    expect(DB::transactionLevel())->toBe(0)
        ->and(fn () => Limits::assertCanCreate($company, Feature::MaxVehicles))->toThrow(LogicException::class);
})->group('mysql');
