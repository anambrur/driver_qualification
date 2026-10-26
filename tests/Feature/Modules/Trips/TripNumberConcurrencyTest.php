<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\Support\Actors;

/*
| 08 §9 on MySQL: parallel creates in one company get distinct numbers, because each takes the
| company row and then the trip_number_sequences row FOR UPDATE (12 §3), also when the sequence
| row does not exist yet. Real PHP processes race on a throwaway database (the Limits
| ConcurrencyTest pattern), which is dropped afterwards.
*/

const TRIPS_DB = 'dq_trips_concurrency';

beforeEach(function () {
    $mysql = config('database.connections.mysql');
    config([
        'database.connections.trips_admin' => array_merge($mysql, ['database' => null]),
        'database.connections.trips' => array_merge($mysql, ['database' => TRIPS_DB]),
    ]);
    DB::connection('trips_admin')->statement('DROP DATABASE IF EXISTS `'.TRIPS_DB.'`');
    DB::connection('trips_admin')->statement('CREATE DATABASE `'.TRIPS_DB.'`');

    $this->previousConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('trips');
    Artisan::call('migrate', ['--database' => 'trips', '--force' => true]);
    (new PermissionSeeder)->run();
})->group('mysql');

afterEach(function () {
    DB::setDefaultConnection($this->previousConnection);
    DB::purge('trips');
    DB::connection('trips_admin')->statement('DROP DATABASE IF EXISTS `'.TRIPS_DB.'`');
    DB::purge('trips_admin');
});

it('gives parallel creates distinct numbers', function (bool $withSequenceRow) {
    $company = Actors::company();
    if (! $withSequenceRow) {
        DB::table('trip_number_sequences')->where('company_id', $company->id)->delete();
    }

    $startAt = sprintf('%.3f', microtime(true) + 3);
    $command = fn () => [PHP_BINARY, base_path('tests/Fixtures/trip-number-race.php'), TRIPS_DB, (string) $company->id, $startAt];
    $processes = collect(range(1, 4))->map(fn () => Process::timeout(60)->start($command()));
    $results = $processes->map->wait();

    foreach ($results as $result) {
        expect($result->successful())->toBeTrue($result->errorOutput());
    }

    $numbers = $results->map(fn ($result) => (int) trim($result->output()))->sort()->values()->all();

    expect($numbers)->toBe([1, 2, 3, 4])
        ->and(DB::table('trips')->where('company_id', $company->id)->count())->toBe(4)
        ->and(DB::table('folders')->where('company_id', $company->id)->where('system_key', 'trip')->count())->toBe(4)
        ->and(DB::table('trip_number_sequences')->where('company_id', $company->id)->value('next_number'))->toBe(5);
})->with(['with the sequence row' => true, 'without it' => false])->group('mysql');
