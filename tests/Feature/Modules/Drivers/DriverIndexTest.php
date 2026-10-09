<?php

use Database\Factories\DriverFactory;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Support\Actors;
use Tests\Support\Perf;

/*
| DRV-07: DataTables' order[0][dir] went straight into orderBy(); any value other than
| asc/desc made the query builder throw, which was a 500.
| DRV-10: the list page ran one COUNT per status (6 queries) on every load.
*/

function listDrivers($test, array $query = [])
{
    return $test->withHeaders(DriverAdmin::ajax())
        ->get(route('admin.driver.index', array_merge(['draw' => 1, 'start' => 0, 'length' => 10], $query)));
}

describe('DRV-07: sort direction is whitelisted', function () {
    it('falls back to a safe direction for an invalid value', function (string $dir) {
        [$owner] = DriverAdmin::tenantWithDriver();

        listDrivers($this->actingAs($owner), ['order' => [['column' => 1, 'dir' => $dir]]])
            ->assertOk()
            ->assertJsonCount(1, 'data');
    })->with(['sideways', 'asc; drop table drivers', '']);

    it('does not crash when the sort column is missing', function () {
        [$owner] = DriverAdmin::tenantWithDriver();

        listDrivers($this->actingAs($owner), ['order' => [['dir' => 'asc']]])->assertOk();
    });

    it('still sorts by name in both directions', function () {
        $owner = Actors::companyOwner();
        DriverAdmin::driverOf($owner, ['first_name' => 'Alice']);
        DriverAdmin::driverOf($owner, ['first_name' => 'Zed']);
        $this->actingAs($owner);

        $asc = listDrivers($this, ['order' => [['column' => 1, 'dir' => 'asc']]])->json('data.*.full_name');
        $desc = listDrivers($this, ['order' => [['column' => 1, 'dir' => 'DESC']]])->json('data.*.full_name');

        expect($asc[0])->toStartWith('Alice')->and($desc[0])->toStartWith('Zed');
    });
});

describe('DRV-10: the driver list has a query budget', function () {
    it('counts drivers by status in one query', function () {
        $owner = Actors::companyOwner();
        $company = DriverAdmin::companyOf($owner);
        foreach (['pending', 'pending', 'active', 'inactive', 'rejected', 'draft'] as $status) {
            DriverFactory::new()->forCompany($company)->status($status)->create();
        }
        DriverFactory::new()->status('active')->create(); // another tenant
        $this->actingAs($owner);

        $countQueries = 0;
        \Illuminate\Support\Facades\DB::listen(function ($q) use (&$countQueries) {
            if (str_contains(strtolower($q->sql), 'count(')) {
                $countQueries++;
            }
        });

        $response = $this->get(route('admin.driver.index'))->assertOk();

        expect($countQueries)->toBe(1)
            ->and($response->viewData('statusCounts'))->toBe([
                'all' => 5, 'draft' => 0, 'pending' => 2, 'active' => 1, 'inactive' => 1, 'rejected' => 1,
            ]);
    });

    it('shows a super-admin every status, drafts included', function () {
        DriverFactory::new()->status('draft')->create();
        DriverFactory::new()->status('active')->create();

        $counts = $this->actingAs(Actors::superAdmin())->get(route('admin.driver.index'))->viewData('statusCounts');

        expect($counts)->toMatchArray(['all' => 2, 'draft' => 1, 'active' => 1]);
    });

    it('does not grow with the number of drivers in the ajax list', function () {
        $owner = Actors::companyOwner();
        $company = DriverAdmin::companyOf($owner);
        $this->actingAs($owner);

        Perf::assertConstantQueries(
            fn (int $n) => DriverFactory::new()->forCompany($company)->count($n)->create(),
            fn () => listDrivers($this, ['search' => ['value' => 'a']])->assertOk(),
        );
    });
});
