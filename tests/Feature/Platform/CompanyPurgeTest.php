<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\StoredFile;
use App\Services\Company\CompanyPurgeService;
use App\Services\Platform\SupportSessionService;
use App\Tenancy\TenantContext;
use Database\Factories\AssetGroupFactory;
use Database\Factories\AssetTransferFactory;
use Database\Factories\DocumentFactory;
use Database\Factories\DriverFactory;
use Database\Factories\InspectionFactory;
use Database\Factories\LicenseFactory;
use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\PaymentFactory;
use Database\Factories\ServiceLogFactory;
use Database\Factories\ShipperFactory;
use Database\Factories\StoredFileFactory;
use Database\Factories\TripFactory;
use Database\Factories\TripStopFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Support\Actors;

/*
| companies:purge (08 §15, 10 §11; decided 23 Oct 2026 with the default retention periods): a
| deactivated company is hard-deleted only after every retention period, children in FK order,
| files after commit; audit logs and other companies are untouched.
*/

beforeEach(function () {
    Storage::fake('local');
});

/** A company holding a row in most tenant tables, with its files on the fake disk. */
function companyWithEverything(): Company
{
    $company = Actors::company();
    Actors::companyAdmin($company);

    app(TenantContext::class)->run($company->id, function () use ($company) {
        [$driver, $dqFile] = Docs::dqFile($company);
        LicenseFactory::new()->current()->create(['driver_id' => $driver->id]);
        $folder = Docs::childOf(Docs::childOf($dqFile));
        $image = StoredFileFactory::new()->forCompany($company)->create();
        $pdf = StoredFileFactory::new()->forCompany($company)->create(['converted_from_file_id' => $image->id]);
        DocumentFactory::new()->inFolder($folder)->create(['file_id' => $pdf->id, 'original_file_id' => $image->id]);

        $vehicle = VehicleFactory::new()->forCompany($company)->create();
        Docs::systemFolder($vehicle);
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create();
        ServiceLogFactory::new()->forVehicle($vehicle)->create(['maintenance_schedule_id' => $schedule->id, 'service_date' => now()->subYears(9)]);
        AssetGroupFactory::new()->forCompany($company)->create();
        $shipper = ShipperFactory::new()->forCompany($company)->create();
        $trip = TripFactory::new()->forCompany($company)->completed()->create(['shipper_id' => $shipper->id]);
        TripStopFactory::new()->onTrip($trip, 'start', 1)->create(['shipper_id' => $shipper->id]);
        $folder = Docs::systemFolder($trip);
        $inspection = InspectionFactory::new()->forTrip($trip)->completed('defects_found')->create();
        $item = $inspection->template->items()->first();
        $inspection->results()->create(['template_item_id' => $item->id, 'item_label' => $item->label, 'result' => 'fail']);
        DocumentFactory::new()->inFolder($folder)->create(['trip_id' => null, 'inspection_id' => $inspection->id]);
        $shipper->delete();
    });

    PaymentFactory::new()->forCompany($company)->create(['created_at' => now()->subYears(9)]);
    AssetTransferFactory::new()->create(['source_company_id' => $company->id]);
    app(SupportSessionService::class)->request(Actors::platformOwner(), $company, 'read', 'Ticket 1: owner asked for help');

    foreach (StoredFile::withoutTenancy()->where('company_id', $company->id)->get() as $file) {
        Storage::disk('local')->put($file->path, 'x');
    }

    DB::table('subscriptions')->where('company_id', $company->id)->update(['status' => 'canceled', 'ended_at' => now()->subYears(8)]);

    return deactivated($company, now()->subYears(8));
}

function deactivated(Company $company, $at): Company
{
    $company->forceFill(['status' => 'deactivated', 'status_changed_at' => $at])->save();

    return $company->fresh();
}

it('knows every table with a company_id column', function () {
    $tables = collect(Schema::getTableListing(Schema::getCurrentSchemaName(), schemaQualified: false))
        ->filter(fn (string $table) => Schema::hasColumn($table, 'company_id'))->sort()->values()->all();

    expect($tables)->toBe(collect(CompanyPurgeService::COMPANY_TABLES)->sort()->values()->all());
});

it('purges every row and file of the company and nothing of another', function () {
    $company = companyWithEverything();
    $other = Actors::company();
    DriverFactory::new()->forCompany($other)->create();
    $otherCounts = app(CompanyPurgeService::class)->counts($other);
    $id = $company->id;

    $this->artisan('companies:purge', ['company' => $id, '--confirm' => true])->assertSuccessful();

    foreach (array_diff(CompanyPurgeService::COMPANY_TABLES, ['audit_logs']) as $table) {
        expect(DB::table($table)->where('company_id', $id)->count())->toBe(0, $table);
    }
    expect(DB::table('companies')->where('id', $id)->exists())->toBeFalse()
        ->and(DB::table('roles')->where('team_id', $id)->exists())->toBeFalse()
        ->and(DB::table('licenses')->exists())->toBeFalse()
        ->and(DB::table('asset_transfers')->where('source_company_id', $id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles("companies/{$id}"))->toBe([])
        ->and(AuditLog::query()->where('company_id', $id)->where('action', 'company.purged')->value('changes'))->toHaveKeys(['drivers', 'documents', 'users'])
        ->and(app(CompanyPurgeService::class)->counts($other))->toBe($otherCounts);
});

it('changes nothing on a dry run', function () {
    $company = companyWithEverything();
    $before = app(CompanyPurgeService::class)->counts($company);

    $this->artisan('companies:purge', ['company' => $company->id])->expectsOutputToContain('Dry run')->assertSuccessful();

    expect(app(CompanyPurgeService::class)->counts($company))->toBe($before);
});

it('refuses a company that is not deactivated', function () {
    $company = Actors::company();

    $this->artisan('companies:purge', ['company' => $company->id, '--confirm' => true])
        ->expectsOutputToContain('not deactivated')->assertFailed();

    expect(Company::query()->whereKey($company->id)->exists())->toBeTrue();
});

it('refuses while a retention period runs: DQ files 3 years after closing', function () {
    $company = Actors::company([], subscribed: false);
    DriverFactory::new()->forCompany($company)->create();
    $company = deactivated($company, now()->subYears(2));

    expect(app(CompanyPurgeService::class)->blockers($company))->toBe(['Records must be kept until '.now()->subYears(2)->addYears(3)->toDateString().' (08 §15).']);

    $this->artisan('companies:purge', ['company' => $company->id, '--confirm' => true])->assertFailed();
    expect(Company::query()->whereKey($company->id)->exists())->toBeTrue();
});

it('keeps payments 7 years and service logs 1 year', function () {
    $company = Actors::company([], subscribed: false);
    PaymentFactory::new()->forCompany($company)->create(['created_at' => now()->subYears(5)]);
    $company = deactivated($company, now()->subYears(6));

    expect(app(CompanyPurgeService::class)->retainedUntil($company)->toDateString())->toBe(now()->subYears(5)->addYears(7)->toDateString());
});

it('refuses while a subscription is still open', function () {
    $company = deactivated(Actors::company(), now()->subYears(8));

    expect(app(CompanyPurgeService::class)->blockers($company))->toBe(['The company still has an open subscription. Cancel it in Stripe first.']);
});

it('purges an empty deactivated company at once', function () {
    $company = deactivated(Actors::company([], subscribed: false), now());

    $this->artisan('companies:purge', ['company' => $company->id, '--confirm' => true])->assertSuccessful();

    expect(Company::query()->whereKey($company->id)->exists())->toBeFalse();
});
