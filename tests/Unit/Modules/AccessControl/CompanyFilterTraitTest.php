<?php

use App\Models\Company;
use App\Models\User;
use App\Models\Vehicle;
use App\Traits\CompanyFilterTrait;
use Database\Factories\CompanyFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Actors;

/*
| CompanyFilterTrait is the tenancy boundary for every company-scoped controller. A fresh harness
| per call, because the trait memoizes the company context per instance.
*/

function aclScope(?User $user = null): object
{
    if ($user) {
        test()->actingAs($user);
    }

    return new class
    {
        use CompanyFilterTrait {
            getUserCompanyId as public;
            getAllUserCompanyId as public;
            getOwningCompanyId as public;
            applyCompanyFilter as public;
            userHasAccess as public;
            authorizeCompanyAccess as public;
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
    it('only sees its own company\'s rows', function () {
        [$tenant, $company, $own] = aclTenantWithVehicles();

        expect(aclScope($tenant)->applyCompanyFilter(Vehicle::query())->pluck('id')->all())->toBe([$own->id])
            ->and(aclScope($tenant)->getUserCompanyId())->toBe($company->id);
    });

    it('can access its own rows and is refused another company\'s with 403', function () {
        [$tenant, , $own, $other] = aclTenantWithVehicles();
        $scope = aclScope($tenant);

        expect($scope->userHasAccess($own))->toBeTrue()
            ->and($scope->userHasAccess($other))->toBeFalse()
            ->and(fn () => $scope->authorizeCompanyAccess($other))
            ->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(403));
    });

    it('accepts a company_id stored as a string', function () {
        [$tenant, $company] = aclTenantWithVehicles();

        expect(aclScope($tenant)->userHasAccess((object) ['company_id' => (string) $company->id]))->toBeTrue();
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
    it('sees every company\'s rows and can access them', function () {
        [, , $own, $other] = aclTenantWithVehicles();
        $scope = aclScope(Actors::superAdmin());

        expect($scope->applyCompanyFilter(Vehicle::query())->count())->toBe(2)
            ->and($scope->getUserCompanyId())->toBeNull()
            ->and($scope->userHasAccess($own))->toBeTrue()
            ->and($scope->userHasAccess($other))->toBeTrue();
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

describe('user without a company, and guest', function () {
    it('sees nothing and can access nothing', function (?callable $makeUser) {
        Vehicle::factory()->create();
        $scope = aclScope($makeUser ? $makeUser() : null);

        expect($scope->applyCompanyFilter(Vehicle::query())->count())->toBe(0)
            ->and($scope->userHasAccess(Vehicle::first()))->toBeFalse()
            ->and($scope->getCompaniesForUser())->toBeEmpty()
            ->and($scope->getAllUserCompanyId())->toBeNull();
    })->with([
        'plain user' => [fn () => Actors::plainUser()],
        'guest' => [null],
    ]);
});
