<?php

use App\Console\Commands\IntegrityCheck;
use App\Models\User;
use Database\Factories\DocumentFactory;
use Database\Factories\DriverFactory;
use Database\Factories\PlatformUserFactory;
use Database\Factories\ServiceLogFactory;
use Database\Factories\StoredFileFactory;
use Database\Factories\SubscriptionFactory;
use Database\Factories\VehicleFactory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Support\Actors;

/*
| The integrity queries of 11 §6 return no rows on seeded data, and each one does report the
| kind of row it exists to catch. Query 8 is skipped until its table exists.
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
        ->and(substr_count($output, '| ok'))->toBe(12)
        ->and(substr_count($output, 'skipped (table absent'))->toBe(1);
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
    // The service files a document with its folder's binding; a raw update breaks it
    'DQ-file document about another driver' => [5, function ($c) {
        $document = DocumentFactory::new()->inFolder(Docs::dqFile($c)[1])->create();
        DB::table('documents')->where('id', $document->id)->update(['driver_id' => DriverFactory::new()->forCompany($c)->create()->id]);
    }],
    'vehicle-folder document about nothing' => [5, function ($c) {
        $document = DocumentFactory::new()->inFolder(Docs::systemFolder(VehicleFactory::new()->forCompany($c)->create()))->create();
        DB::table('documents')->where('id', $document->id)->update(['vehicle_id' => null]);
    }],
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
    // FolderService stops at 5 levels; the factory doesn't
    'folder six levels deep' => [11, function ($c) {
        $folder = Docs::companyFolder($c);
        foreach (range(2, 6) as $level) {
            $folder = Docs::childOf($folder);
        }
    }],
    'child folder without its parent binding' => [12, function ($c) {
        $child = Docs::childOf(Docs::dqFile($c)[1]);
        DB::table('folders')->where('id', $child->id)->update(['driver_id' => null]);
    }],
    // ConvertImageToPdf links both sides in one transaction; a raw update links only one
    'converted document whose PDF came from another image' => [13, function ($c) {
        $document = DocumentFactory::new()->inFolder(Docs::companyFolder($c))->create();
        DB::table('documents')->where('id', $document->id)->update(['original_file_id' => StoredFileFactory::new()->forCompany($c)->create()->id]);
    }],
]);

it('accepts a vehicle-folder document about one of its service logs', function () {
    $company = Actors::company();
    $vehicle = VehicleFactory::new()->forCompany($company)->create();
    $document = DocumentFactory::new()->inFolder(Docs::systemFolder($vehicle))->create();
    DB::table('documents')->where('id', $document->id)->update([
        'vehicle_id' => null, 'service_log_id' => ServiceLogFactory::new()->forVehicle($vehicle)->create()->id,
    ]);

    [$exit] = integrityOutput();

    expect($exit)->toBe(0);
});

it('lists every query of 11 §6', function () {
    expect(array_keys(IntegrityCheck::QUERIES))->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13]);
});
