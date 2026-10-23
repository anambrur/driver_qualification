<?php

use App\Models\DocumentType;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use Database\Factories\AssetGroupFactory;
use Database\Factories\CompanyFactory;
use Database\Factories\DriverComplianceDocumentFactory;
use Database\Factories\DriverFactory;
use Database\Factories\LicenseFactory;
use Database\Factories\PlatformUserFactory;
use Database\Factories\StoredFileFactory;
use Database\Factories\SubscriptionFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
| Rules the database enforces on its own (08 §1, 09). Each test writes rows directly, so a
| constraint that goes missing from a migration fails here even if the app code still guards it.
*/

describe('reference and platform', function () {
    it('allows one current price per plan, interval and currency', function () {
        $plan = Plan::factory()->create();
        PlanPrice::factory()->for($plan)->create(['interval' => 'month']);
        PlanPrice::factory()->for($plan)->archived()->create(['interval' => 'month']);
        PlanPrice::factory()->for($plan)->archived()->create(['interval' => 'month']);
        PlanPrice::factory()->for($plan)->create(['interval' => 'year']);

        expect(fn () => PlanPrice::factory()->for($plan)->create(['interval' => 'month']))
            ->toThrow(QueryException::class);
    });

    it('keeps a plan with prices from being deleted', function () {
        $plan = Plan::factory()->withPrices()->create();

        expect(fn () => DB::table('plans')->where('id', $plan->id)->delete())->toThrow(QueryException::class);
    });

    it('rejects duplicate lookup names', function (string $table) {
        DB::table($table)->insert(['name' => 'Same']);

        expect(fn () => DB::table($table)->insert(['name' => 'Same']))->toThrow(QueryException::class);
    })->with(['vehicle_types', 'fuel_types', 'equipment_types', 'vehicle_groups', 'maintenance_categories']);

    it('rejects a duplicate document type name within a module and a duplicate system key', function () {
        DocumentType::factory()->create(['name' => 'Same', 'module' => 'driver', 'system_key' => 'k']);
        DocumentType::factory()->create(['name' => 'Same', 'module' => 'vehicle']);

        expect(fn () => DocumentType::factory()->create(['name' => 'Same', 'module' => 'driver']))->toThrow(QueryException::class)
            ->and(fn () => DocumentType::factory()->create(['system_key' => 'k']))->toThrow(QueryException::class);
    });

    it('rejects a duplicate state code within a country', function () {
        $countryId = DB::table('countries')->insertGetId(['name' => 'US', 'iso_code' => 'US']);
        DB::table('states')->insert(['country_id' => $countryId, 'name' => 'TX', 'code' => 'TX']);

        expect(fn () => DB::table('states')->insert(['country_id' => $countryId, 'name' => 'Other', 'code' => 'TX']))
            ->toThrow(QueryException::class);
    });
});

describe('tenancy and authorization', function () {
    it('creates a company whose owner is one of its users', function () {
        $company = CompanyFactory::new()->create();

        expect($company->owner->company_id)->toBe($company->id)
            ->and($company->settings)->not->toBeNull();
    });

    it('rejects an owner from another company', function () {
        $a = CompanyFactory::new()->create();
        $b = CompanyFactory::new()->create();

        expect(fn () => DB::table('companies')->where('id', $b->id)->update(['owner_user_id' => $a->owner_user_id]))
            ->toThrow(QueryException::class);
    });

    it('restricts deleting an owner or a company with users', function () {
        $company = CompanyFactory::new()->create();

        expect(fn () => DB::table('users')->where('id', $company->owner_user_id)->delete())->toThrow(QueryException::class)
            ->and(fn () => DB::table('companies')->where('id', $company->id)->delete())->toThrow(QueryException::class);
    });

    it('rejects a status outside the company lifecycle', function () {
        $company = CompanyFactory::new()->create();

        expect(fn () => DB::table('companies')->where('id', $company->id)->update(['status' => 'inactive']))
            ->toThrow(QueryException::class);
    });

    it('stores login emails lowercase and globally unique', function () {
        $user = User::factory()->create(['email' => ' Mixed@Example.COM ']);

        expect($user->email)->toBe('mixed@example.com')
            ->and(fn () => User::factory()->create(['email' => 'MIXED@example.com']))->toThrow(QueryException::class);
    });

    it('enforces the overtime settings checks', function (array $values) {
        $company = CompanyFactory::new()->create();

        expect(fn () => DB::table('company_settings')->where('company_id', $company->id)->update($values))
            ->toThrow(QueryException::class);
    })->with([
        'double OT before OT' => [['double_overtime_daily_after_minutes' => 300]],
        'multiplier below 1' => [['overtime_multiplier' => 0.5]],
        'double below OT multiplier' => [['double_overtime_multiplier' => 1.25]],
    ]);

    it('rejects a role without a team', function () {
        expect(fn () => DB::table('roles')->insert(['name' => 'x', 'guard_name' => 'web', 'team_id' => null]))
            ->toThrow(QueryException::class);
    });

    it('rejects a role pivot that uses another team\'s role', function () {
        $a = CompanyFactory::new()->create();
        $b = CompanyFactory::new()->create();
        $roleOfA = DB::table('roles')->insertGetId(['team_id' => $a->id, 'name' => 'Custom', 'guard_name' => 'web']);

        expect(fn () => DB::table('model_has_roles')->insert([
            'team_id' => $b->id, 'role_id' => $roleOfA, 'model_type' => User::class, 'model_id' => $b->owner_user_id,
        ]))->toThrow(QueryException::class);
    });

    it('assigns a team role through Spatie', function () {
        $company = CompanyFactory::new()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $role = Role::create(['name' => 'Custom', 'guard_name' => 'web']);

        $company->owner->assignRole('Custom');

        expect(DB::table('model_has_roles')->where('role_id', $role->id)->where('model_id', $company->owner_user_id)->value('team_id'))->toBe($company->id);
    });
});

describe('drivers, fleet, billing, files and audit', function () {
    it('allows one current licence per driver', function () {
        $driver = DriverFactory::new()->withCurrentLicense()->create();
        LicenseFactory::new()->create(['driver_id' => $driver->id]);

        expect(fn () => LicenseFactory::new()->current()->create(['driver_id' => $driver->id]))->toThrow(QueryException::class);
    });

    it('restricts deleting a driver who has a licence', function () {
        $driver = DriverFactory::new()->withCurrentLicense()->create();

        expect(fn () => DB::table('drivers')->where('id', $driver->id)->delete())->toThrow(QueryException::class);
    });

    it('allows one driver_documents row per driver', function () {
        $driver = DriverFactory::new()->create();
        DB::table('driver_documents')->insert(['driver_id' => $driver->id]);

        expect(fn () => DB::table('driver_documents')->insert(['driver_id' => $driver->id]))->toThrow(QueryException::class);
    });

    it('rejects a driver login from another company', function () {
        $other = CompanyFactory::new()->create();

        expect(fn () => DriverFactory::new()->create(['user_id' => $other->owner_user_id]))->toThrow(QueryException::class);
    });

    it('rejects a compliance document whose file belongs to another company', function () {
        $driver = DriverFactory::new()->create();
        $foreignFile = StoredFileFactory::new()->create();

        expect(fn () => DriverComplianceDocumentFactory::new()->create(['driver_id' => $driver->id, 'file_id' => $foreignFile->id]))
            ->toThrow(QueryException::class);
    });

    it('rejects an asset group that mixes companies', function () {
        $group = AssetGroupFactory::new()->create();
        $foreignDriver = DriverFactory::new()->create();

        expect(fn () => DB::table('asset_groups')->where('id', $group->id)->update(['driver_id' => $foreignDriver->id]))
            ->toThrow(QueryException::class);
    });

    it('allows one active asset group per vehicle', function () {
        $group = AssetGroupFactory::new()->create();
        AssetGroupFactory::new()->create(['vehicle_id' => $group->vehicle_id, 'status' => 'inactive']);

        expect(fn () => AssetGroupFactory::new()->create(['vehicle_id' => $group->vehicle_id]))->toThrow(QueryException::class);

        $group->delete(); // soft-deleted groups no longer hold the vehicle
        AssetGroupFactory::new()->create(['vehicle_id' => $group->vehicle_id]);
    });

    it('restricts deleting a company that still has drivers', function () {
        // A bare row with no owner, users or settings, so only the driver can block the delete.
        $companyId = DB::table('companies')->insertGetId(['company_name' => 'Bare Co', 'slug' => 'bare-co', 'status' => 'active']);
        DriverFactory::new()->create(['company_id' => $companyId, 'created_by_user_id' => null]);

        expect(fn () => DB::table('companies')->where('id', $companyId)->delete())->toThrow(QueryException::class);
    });

    it('allows one open subscription per company', function () {
        $company = CompanyFactory::new()->create();
        SubscriptionFactory::new()->forCompany($company)->create();
        SubscriptionFactory::new()->forCompany($company)->expired()->create();

        expect(fn () => SubscriptionFactory::new()->forCompany($company)->create())->toThrow(QueryException::class);
    });

    it('allows one open override per company and feature, and checks its values', function () {
        $company = CompanyFactory::new()->create(); // max_drivers comes from the registry sync
        $override = fn (array $values = []) => DB::table('company_feature_overrides')->insert($values + [
            'company_id' => $company->id, 'feature_key' => 'max_drivers', 'limit_value' => 10, 'reason' => 'test', 'starts_at' => '2026-10-01 00:00:00',
        ]);

        $override(['revoked_at' => now()]);
        $override();

        expect(fn () => $override())->toThrow(QueryException::class)
            ->and(fn () => $override(['revoked_at' => now(), 'is_unlimited' => true]))->toThrow(QueryException::class)
            ->and(fn () => $override(['revoked_at' => now(), 'ends_at' => '2026-09-01 00:00:00']))->toThrow(QueryException::class);
    });

    it('requires both approvers and four eyes on support sessions', function () {
        $company = CompanyFactory::new()->create();
        $requester = PlatformUserFactory::new()->create();
        $session = fn (array $values) => DB::table('platform_support_sessions')->insert($values + [
            'company_id' => $company->id, 'platform_user_id' => $requester->id, 'reason' => 'ticket 1', 'scope' => 'read',
        ]);

        $session(['status' => 'requested']);

        expect(fn () => $session(['status' => 'approved', 'tenant_approved_by_user_id' => $company->owner_user_id]))->toThrow(QueryException::class)
            ->and(fn () => $session(['status' => 'requested', 'platform_approved_by_platform_user_id' => $requester->id]))->toThrow(QueryException::class);

        $session([
            'status' => 'approved',
            'tenant_approved_by_user_id' => $company->owner_user_id,
            'platform_approved_by_platform_user_id' => PlatformUserFactory::new()->create()->id,
        ]);
    });

    it('keeps asset transfers between two companies, with ids of the declared kind and company', function () {
        $source = CompanyFactory::new()->create();
        $target = CompanyFactory::new()->create();
        $vehicle = VehicleFactory::new()->forCompany($source)->create();
        $transfer = fn (array $values) => DB::table('asset_transfers')->insert($values + [
            'asset_type' => 'vehicle', 'source_company_id' => $source->id, 'target_company_id' => $target->id,
            'source_vehicle_id' => $vehicle->id, 'reason' => 'sold', 'status' => 'completed',
            'performed_by_platform_user_id' => PlatformUserFactory::new()->create()->id, 'performed_at' => now(),
        ]);

        expect(fn () => $transfer(['target_company_id' => $source->id]))->toThrow(QueryException::class)
            ->and(fn () => $transfer(['asset_type' => 'trailer']))->toThrow(QueryException::class)
            // The source vehicle must belong to the source company (composite FK)
            ->and(fn () => $transfer(['source_company_id' => $target->id, 'target_company_id' => $source->id]))->toThrow(QueryException::class);

        $transfer([]);
    });
});

describe('folders and documents', function () {
    beforeEach(function () {
        $this->company = CompanyFactory::new()->create();
        $this->folder = fn (array $values) => DB::table('folders')->insertGetId($values + [
            'company_id' => $this->company->id, 'name' => 'Folder '.fake()->unique()->numberBetween(1, 99999),
        ]);
    });

    it('allows at most one binding per folder', function () {
        $driver = DriverFactory::new()->forCompany($this->company)->create();
        $vehicle = VehicleFactory::new()->forCompany($this->company)->create();

        ($this->folder)(['driver_id' => $driver->id]);

        expect(fn () => ($this->folder)(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]))->toThrow(QueryException::class);
    });

    it('keeps sibling names unique, root folders included, until a folder is deleted', function () {
        $root = ($this->folder)(['name' => 'Insurance']);
        ($this->folder)(['name' => 'Claims', 'parent_id' => $root]);
        ($this->folder)(['name' => 'Claims']);

        expect(fn () => ($this->folder)(['name' => 'Insurance']))->toThrow(QueryException::class)
            ->and(fn () => ($this->folder)(['name' => 'Claims', 'parent_id' => $root]))->toThrow(QueryException::class);

        DB::table('folders')->where('id', $root)->update(['deleted_at' => now()]);
        ($this->folder)(['name' => 'Insurance']);
    });

    it('rejects a parent folder of another company', function () {
        $foreign = DB::table('folders')->insertGetId(['company_id' => CompanyFactory::new()->create()->id, 'name' => 'Theirs']);

        expect(fn () => ($this->folder)(['parent_id' => $foreign]))->toThrow(QueryException::class);
    });

    it('allows at most one subject per document and only files of its company', function () {
        $driver = DriverFactory::new()->forCompany($this->company)->create();
        $vehicle = VehicleFactory::new()->forCompany($this->company)->create();
        $file = StoredFileFactory::new()->forCompany($this->company)->create();
        $document = fn (array $values) => DB::table('documents')->insert($values + [
            'company_id' => $this->company->id, 'file_id' => $file->id, 'title' => 'Doc',
        ]);

        $document(['driver_id' => $driver->id]);
        $document(['trip_id' => 1]); // no FK until Phase 2, still counted by the CHECK

        expect(fn () => $document(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]))->toThrow(QueryException::class)
            ->and(fn () => $document(['vehicle_id' => $vehicle->id, 'expense_id' => 1]))->toThrow(QueryException::class)
            ->and(fn () => $document(['file_id' => StoredFileFactory::new()->create()->id]))->toThrow(QueryException::class);
    });

    it('restricts deleting a folder that still has documents', function () {
        $folder = ($this->folder)([]);
        DB::table('documents')->insert([
            'company_id' => $this->company->id, 'folder_id' => $folder, 'title' => 'Doc',
            'file_id' => StoredFileFactory::new()->forCompany($this->company)->create()->id,
        ]);

        expect(fn () => DB::table('folders')->where('id', $folder)->delete())->toThrow(QueryException::class);
    });
});
