<?php

use App\Mail\DriverComplianceDigestMail;
use App\Models\DocumentType;
use App\Services\Compliance\DriverComplianceService;
use Database\Factories\DriverFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Actors;

/*
| LKP-04 follow-up: document types stay one global catalog, but each company can switch
| individual types off for itself. A switched-off type no longer counts towards that
| company's compliance %, missing lists or reminder digests, and other companies are unaffected.
*/

function disableForCompany(int $companyId, DocumentType $type): void
{
    DB::table('company_document_type')->insert([
        'company_id' => $companyId,
        'document_type_id' => $type->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

describe('switching a type off', function () {
    it('lets a tenant switch a global type off and back on for their own company only', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        $other = Actors::companyOf(Actors::companyOwner());
        $type = DocumentType::factory()->create();

        $this->actingAs($owner)
            ->postJson(route('admin.settings.document-types.company-toggle', $type->id))
            ->assertOk()
            ->assertJson(['success' => true, 'data' => ['enabled' => false]]);

        expect(DB::table('company_document_type')->where('company_id', $company->id)->where('document_type_id', $type->id)->exists())->toBeTrue()
            ->and(DB::table('company_document_type')->where('company_id', $other->id)->exists())->toBeFalse()
            ->and($type->fresh()->status)->toBeTrue();

        $this->actingAs($owner)
            ->postJson(route('admin.settings.document-types.company-toggle', $type->id))
            ->assertOk()
            ->assertJson(['data' => ['enabled' => true]]);

        expect(DB::table('company_document_type')->where('company_id', $company->id)->exists())->toBeFalse();
    });

    it('returns 404 for an unknown document type', function () {
        $this->actingAs(Actors::companyOwner())
            ->postJson(route('admin.settings.document-types.company-toggle', 999999))
            ->assertNotFound();
    });

    it('forbids a user without companies.edit', function () {
        $type = DocumentType::factory()->create();

        $this->actingAs(Actors::plainUser())
            ->postJson(route('admin.settings.document-types.company-toggle', $type->id))
            ->assertForbidden();
    });

    it('shows the My Company column to tenants only', function () {
        $this->actingAs(Actors::companyOwner())
            ->get(route('admin.settings.document-types.index'))
            ->assertOk()
            ->assertSee('My Company')
            ->assertDontSee('Add Document Type');

        $this->actingAs(Actors::superAdmin())
            ->get(route('admin.settings.document-types.index'))
            ->assertOk()
            ->assertDontSee('My Company')
            ->assertSee('Add Document Type');
    });

    it("shows a tenant which types are switched off for their company", function () {
        $owner = Actors::companyOwner();
        $type = DocumentType::factory()->create();
        disableForCompany(Actors::companyOf($owner)->id, $type);

        $row = $this->actingAs($owner)
            ->getJson(route('admin.settings.document-types.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('data.0');

        expect($row['company_enabled'])->toContain('Off')
            ->and($row['action'])->toContain('data-action="company-toggle"')
            ->not->toContain('editDocumentType');
    });
});

describe('compliance respects the company opt-out', function () {
    it('drops a switched-off type from a driver compliance calculation', function () {
        $company = Actors::companyOf(Actors::companyOwner());
        $kept = DocumentType::factory()->create(['name' => 'MVR']);
        $dropped = DocumentType::factory()->create(['name' => 'Road Test']);
        disableForCompany($company->id, $dropped);

        $result = app(DriverComplianceService::class)->forDriver(DriverFactory::new()->forCompany($company)->create());

        expect($result['total_docs'])->toBe(1)
            ->and(collect($result['document_details'])->pluck('type_id')->all())->toBe([$kept->id]);
    });

    it("leaves other companies' driver compliance unchanged", function () {
        $companyA = Actors::companyOf(Actors::companyOwner());
        $companyB = Actors::companyOf(Actors::companyOwner());
        DocumentType::factory()->create();
        disableForCompany($companyA->id, DocumentType::factory()->create());

        $result = app(DriverComplianceService::class)->forDriver(DriverFactory::new()->forCompany($companyB)->create());

        expect($result['total_docs'])->toBe(2);
    });

    it('uses each driver\'s own company on the driver compliance dashboard', function () {
        $companyA = Actors::companyOf(Actors::companyOwner());
        $companyB = Actors::companyOf(Actors::companyOwner());
        DocumentType::factory()->create();
        disableForCompany($companyA->id, DocumentType::factory()->create());
        $driverA = DriverFactory::new()->forCompany($companyA)->create();
        $driverB = DriverFactory::new()->forCompany($companyB)->create();

        $this->actingAs(Actors::superAdmin())
            ->get(route('admin.compliance.drivers'))
            ->assertOk()
            ->assertViewHas('drivers', function (array $drivers) use ($driverA, $driverB) {
                $totals = collect($drivers)->pluck('total_docs', 'id');

                return $totals[$driverA->id] === 1 && $totals[$driverB->id] === 2;
            });
    });

    it('uses each vehicle\'s own company on the fleet compliance dashboard', function () {
        $companyA = Actors::companyOf(Actors::companyOwner());
        $companyB = Actors::companyOf(Actors::companyOwner());
        DocumentType::factory()->module('vehicle')->create();
        disableForCompany($companyA->id, DocumentType::factory()->module('vehicle')->create());
        $vehicleA = VehicleFactory::new()->forCompany($companyA)->create();
        $vehicleB = VehicleFactory::new()->forCompany($companyB)->create();

        $this->actingAs(Actors::superAdmin())
            ->get(route('admin.compliance.fleet'))
            ->assertOk()
            ->assertViewHas('vehicles', function (array $vehicles) use ($vehicleA, $vehicleB) {
                $totals = collect($vehicles)->pluck('total_docs', 'id');

                return $totals[$vehicleA->id] === 1 && $totals[$vehicleB->id] === 2;
            });
    });

    it('drops a switched-off type from the vehicle details modal', function () {
        $owner = Actors::companyOwner();
        $company = Actors::companyOf($owner);
        DocumentType::factory()->module('vehicle')->create();
        disableForCompany($company->id, DocumentType::factory()->module('vehicle')->create());
        $vehicle = VehicleFactory::new()->forCompany($company)->create();

        $this->actingAs($owner)
            ->getJson(route('admin.compliance.vehicle.details', $vehicle->id))
            ->assertOk()
            ->assertJsonPath('vehicle.total_docs', 1);
    });

    it('drops a switched-off type from the vehicle compliance attributes', function () {
        $company = Actors::companyOf(Actors::companyOwner());
        DocumentType::factory()->module('vehicle')->create(['name' => 'Insurance']);
        disableForCompany($company->id, DocumentType::factory()->module('vehicle')->create(['name' => 'IFTA']));
        $vehicle = VehicleFactory::new()->forCompany($company)->create();

        expect($vehicle->missing_documents)->toBe(['Insurance'])
            ->and($vehicle->compliance_percentage)->toEqual(0);
    });

    it('leaves a switched-off type out of the daily digest email', function () {
        Mail::fake();
        $company = Actors::companyOf(Actors::companyOwner());
        DocumentType::factory()->create(['name' => 'MVR']);
        disableForCompany($company->id, DocumentType::factory()->create(['name' => 'Road Test']));
        DriverFactory::new()->forCompany($company)->create();

        $this->artisan('compliance:send-digest-reminders')->assertSuccessful();

        Mail::assertQueued(DriverComplianceDigestMail::class, function (DriverComplianceDigestMail $mail) {
            return collect($mail->issues)->pluck('name')->all() === ['MVR'];
        });
    });
});
