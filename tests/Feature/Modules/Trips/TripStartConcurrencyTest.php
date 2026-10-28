<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Feature\Modules\Trips\Trips;
use Tests\Support\Actors;

/*
| 08 §9 on MySQL: two assigned trips of the same driver started at the same moment end with one
| in progress; the unique active_driver_id refuses the other, and the service turns that into the
| "already has a trip in progress" message. Real PHP processes race on a throwaway database (the
| TripNumberConcurrencyTest pattern), which is dropped afterwards.
*/

const TRIP_START_DB = 'dq_trip_start_concurrency';

beforeEach(function () {
    $mysql = config('database.connections.mysql');
    config([
        'database.connections.trip_start_admin' => array_merge($mysql, ['database' => null]),
        'database.connections.trip_start' => array_merge($mysql, ['database' => TRIP_START_DB]),
    ]);
    DB::connection('trip_start_admin')->statement('DROP DATABASE IF EXISTS `'.TRIP_START_DB.'`');
    DB::connection('trip_start_admin')->statement('CREATE DATABASE `'.TRIP_START_DB.'`');

    $this->previousConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('trip_start');
    Artisan::call('migrate', ['--database' => 'trip_start', '--force' => true]);
    (new PermissionSeeder)->run();
    Shippers::seedStates();
})->group('mysql');

afterEach(function () {
    DB::setDefaultConnection($this->previousConnection);
    DB::purge('trip_start');
    DB::connection('trip_start_admin')->statement('DROP DATABASE IF EXISTS `'.TRIP_START_DB.'`');
    DB::purge('trip_start_admin');
});

it('starts only one of two trips of the same driver started at once', function () {
    $company = Actors::company();
    $crew = Trips::crew($company);
    $first = Trips::inspected(Trips::assigned($company, $crew));
    $second = Trips::inspected(Trips::assigned($company, ['vehicle' => Trips::crew($company)['vehicle']] + $crew));

    $startAt = sprintf('%.3f', microtime(true) + 3);
    $results = collect([$first, $second])
        ->map(fn ($trip) => Process::timeout(60)->start([PHP_BINARY, base_path('tests/Fixtures/trip-start-race.php'), TRIP_START_DB, (string) $company->id, (string) $trip->id, $startAt]))
        ->map->wait();

    foreach ($results as $result) {
        expect($result->successful())->toBeTrue($result->errorOutput());
    }

    $outputs = $results->map(fn ($result) => trim($result->output()))->sort()->values()->all();
    expect($outputs[1])->toBe('started')
        ->and($outputs[0])->toStartWith('This driver already has a trip in progress (#')
        ->and(DB::table('trips')->where('driver_id', $crew['driver']->id)->where('status', 'in_progress')->count())->toBe(1);
})->group('mysql');
