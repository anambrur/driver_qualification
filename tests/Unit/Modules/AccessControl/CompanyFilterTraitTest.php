<?php

use App\Models\Company;
use App\Models\User;
use App\Models\Vehicle;
use App\Traits\CompanyFilterTrait;
use Tests\Support\Actors;

/*
| What is left of CompanyFilterTrait after WP5: the company of new records and the company
| dropdowns. Row filtering and the 403 access check are gone; the tenant scope and the policies
| replace them (03 §7). The super-admin helpers are gone (WP8): tenant code only ever works in
| its own company, and the platform works through /platform.
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

    it('files new records under its own company', function () {
        [$tenant, $company] = aclTenantWithVehicles();

        expect(aclScope($tenant)->getAllUserCompanyId())->toBe($company->id);
    });

    it('only gets its own company in dropdowns', function () {
        [$tenant, $company] = aclTenantWithVehicles();

        expect(aclScope($tenant)->getCompaniesForUser()->pluck('id')->all())->toBe([$company->id]);
    });
});

describe('guest', function () {
    it('gets no company', function () {
        $scope = aclScope();

        expect($scope->getCompaniesForUser())->toBeEmpty()
            ->and($scope->getAllUserCompanyId())->toBeNull();
    });
});
