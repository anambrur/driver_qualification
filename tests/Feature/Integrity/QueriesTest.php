<?php

use App\Console\Commands\IntegrityCheck;
use App\Models\User;
use Database\Factories\PlatformUserFactory;
use Database\Factories\StoredFileFactory;
use Database\Factories\SubscriptionFactory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\Actors;

/*
| The integrity queries of 11 §6 return no rows on seeded data, and each one does report the
| kind of row it exists to catch. Queries 5 and 8 are skipped until their tables exist.
*/

function integrityOutput(): array
{
    $exit = Artisan::call('integrity:check');

    return [$exit, Artisan::output()];
}

it('returns no rows on seeded data', function () {
    Http::fake(['*' => Http::response([], 500)]);
    $this->seed(DatabaseSeeder::class);

    [$exit, $output] = integrityOutput();

    expect($exit)->toBe(0)
        ->and(substr_count($output, '| ok'))->toBe(8)
        ->and(substr_count($output, 'skipped (table absent'))->toBe(2);
});

it('reports the rows that break a rule', function (int $query, Closure $breakIt) {
    $company = Actors::company();
    $breakIt($company);

    [$exit, $output] = integrityOutput();
    $line = collect(explode("\n", $output))->first(fn ($l) => str_starts_with(trim($l), "| {$query} "));

    expect($exit)->toBe(1)->and($line)->toContain('row(s)');
})->with([
    'role with platform guard in a company team' => [1, fn ($c) => DB::table('roles')->insert(['team_id' => $c->id, 'name' => 'x', 'guard_name' => 'platform'])],
    'role pivot in another company' => [2, function ($c) {
        $other = Actors::company();
        DB::table('model_has_roles')->insert([
            'team_id' => $other->id,
            'role_id' => DB::table('roles')->where('team_id', $other->id)->where('system_key', 'driver')->value('id'),
            'model_type' => User::class,
            'model_id' => $c->owner_user_id,
        ]);
    }],
    'owner role held by a second user' => [3, function ($c) {
        $admin = Actors::companyAdmin($c);
        DB::table('model_has_roles')->insert([
            'team_id' => $c->id,
            'role_id' => DB::table('roles')->where('team_id', $c->id)->where('system_key', 'owner')->value('id'),
            'model_type' => User::class,
            'model_id' => $admin->id,
        ]);
    }],
    'company without settings' => [4, fn ($c) => DB::table('company_settings')->where('company_id', $c->id)->delete()],
    'storage counter out of step' => [6, function ($c) {
        StoredFileFactory::new()->forCompany($c)->create();
        DB::table('companies')->where('id', $c->id)->update(['storage_bytes' => 1]);
    }],
    // The unique index already prevents a second open row; drop it to prove the query still
    // catches one. SQLite only: on MySQL the DDL would commit the test transaction.
    'two open subscriptions' => [7, function ($c) {
        if (DB::getDriverName() !== 'sqlite') {
            test()->markTestSkipped('Needs transactional DDL (SQLite).');
        }

        DB::statement('drop index subscriptions_company_id_open_flag_unique');
        SubscriptionFactory::new()->forCompany($c)->create();
    }],
    // A raw insert bypasses EmailIdentityService and the lowercase mutator
    'email both a tenant and a platform login' => [9, fn ($c) => DB::table('platform_users')->insert(
        PlatformUserFactory::new()->raw(['email' => strtoupper((string) $c->owner->email), 'password' => 'x'])
    )],
    // Activated outside SupportSessionService::start()
    'support user active without a session' => [10, fn ($c) => DB::table('users')
        ->where('company_id', $c->id)->where('user_type', 'support')->update(['status' => 'active'])],
]);

it('lists every query of 11 §6', function () {
    expect(array_keys(IntegrityCheck::QUERIES))->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
});
