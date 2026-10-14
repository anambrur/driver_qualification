<?php

use App\Models\Company;
use App\Models\User;
use App\Models\Vehicle;
use App\Traits\CompanyFilterTrait;
use Database\Factories\CompanyFactory;
use Tests\Support\Actors;

/*
| What is left of CompanyFilterTrait after WP5: the company of new records and the company
| dropdowns. Row filtering and the 403 access check are gone; the tenant scope and the policies
| replace them (03 §7). The super-admin helpers go with WP8.
*/

function aclScope(?User $user = null): object
{
    if ($user) {
        Actors::actingAsTenant($user);
    }

    return new class
    {
        use CompanyFilterTrait {
            getUserCompanyId as public;
            getAllUserCompanyId as public;
            getOwningCompanyId as public;
            getCompaniesForUser as public;
        }
    };
}

/** @return array{0: User, 1: Company, 2: Vehicle, 3: Vehicle} tenant, its company, its vehicle, another company's vehicle */
function aclTenantWithVehicles(): array
{
    $tenant = Actors::companyOwner();
    $company = Actors::companyOf($tenant);

    return [$tenant, $company, Vehicle::factory()->forCompany($company)->create(), Vehicle::factory()->create()];
}

describe('tenant', function () {
    it('works in its own company', function () {
        [$tenant, $company] = aclTenantWithVehicles();

        expect(aclScope($tenant)->getUserCompanyId())->toBe($company->id);
    });

    it('owns new records itself, whatever company the resource belongs to', function () {
        [$tenant, $company, , $other] = aclTenantWithVehicles();

        expect(aclScope($tenant)->getOwningCompanyId($other->company_id))->toBe($company->id)
            ->and(aclScope($tenant)->getOwningCompanyId(null))->toBe($company->id);
    });

    it('only gets its own company in dropdowns', function () {
        [$tenant, $company] = aclTenantWithVehicles();

        expect(aclScope($tenant)->getCompaniesForUser()->pluck('id')->all())->toBe([$company->id]);
    });
});

describe('super-admin', function () {
    it('is not limited to a company', function () {
        $scope = aclScope(Actors::superAdmin());

        expect($scope->getUserCompanyId())->toBeNull();
    });

    it('files new records under the resource\'s company, or its own when there is none', function () {
        [, , , $other] = aclTenantWithVehicles();
        $admin = Actors::superAdmin();

        expect(aclScope($admin)->getOwningCompanyId($other->company_id))->toBe($other->company_id)
            ->and(aclScope($admin)->getOwningCompanyId(null))->toBe(Actors::companyOf($admin)->id)
            ->and(aclScope($admin)->getAllUserCompanyId())->toBe(Actors::companyOf($admin)->id);
    });

    it('gets only active companies in dropdowns', function () {
        $admin = Actors::superAdmin();
        $inactive = CompanyFactory::new()->create(['status' => 'inactive']);

        expect(aclScope($admin)->getCompaniesForUser()->pluck('id')->all())
            ->toContain(Actors::companyOf($admin)->id)
            ->not->toContain($inactive->id);
    });
});

describe('guest', function () {
    it('gets no company', function () {
        $scope = aclScope();

        expect($scope->getCompaniesForUser())->toBeEmpty()
            ->and($scope->getAllUserCompanyId())->toBeNull();
    });
});
