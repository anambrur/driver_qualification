<?php

use App\Authorization\PermissionCatalogue;
use App\Features\Feature;
use Database\Seeders\FeatureRegistrySync;
use Illuminate\Support\Facades\DB;
use Tests\Support\Actors;

/*
| 05 §2: the Feature enum is the registry. The sync upserts it, never overwrites availability
| (the platform owns it), and disables, never deletes, a key that left the code.
*/

it('has a features row for every enum case, with its kind, group and unit', function () {
    $rows = DB::table('features')->get()->keyBy('key');

    foreach (Feature::cases() as $feature) {
        expect($rows->get($feature->value))->not->toBeNull()
            ->and($rows[$feature->value]->kind)->toBe($feature->kind())
            ->and($rows[$feature->value]->group)->toBe($feature->group())
            ->and($rows[$feature->value]->unit)->toBe($feature->unit());
    }

    expect(Feature::MaxDrivers->kind())->toBe('limit')
        ->and(Feature::Maintenance->kind())->toBe('module');
});

it('keeps the availability the platform set when it runs again', function () {
    DB::table('features')->where('key', 'gps_tracking')->update(['availability' => 'override_only']);

    (new FeatureRegistrySync)->run();

    expect(DB::table('features')->where('key', 'gps_tracking')->value('availability'))->toBe('override_only');
});

it('disables a feature removed from the code, keeps the row and drops cached entitlements', function () {
    $company = Actors::company();
    DB::table('features')->insert(['key' => 'fax', 'name' => 'Fax', 'group' => 'Legacy', 'kind' => 'module', 'availability' => 'general', 'created_at' => now(), 'updated_at' => now()]);

    (new FeatureRegistrySync)->run();
    (new FeatureRegistrySync)->run();

    expect(DB::table('features')->where('key', 'fax')->value('availability'))->toBe('disabled')
        ->and($company->fresh()->entitlements_version)->toBe($company->entitlements_version + 1);
});

it('links every catalogue permission to its feature', function () {
    $linked = DB::table('permissions')->where('guard_name', 'web')->pluck('feature_key', 'name');

    foreach (PermissionCatalogue::TENANT as $name => [$feature]) {
        expect($linked[$name])->toBe($feature);
    }

    expect(Feature::Maintenance->permissions())->toContain('maintenance.view', 'scheduled.create')
        ->and(Feature::MaxDrivers->permissions())->toBe([]);
});
