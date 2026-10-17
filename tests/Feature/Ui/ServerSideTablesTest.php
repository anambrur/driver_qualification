<?php

use App\Models\FuelType;
use Database\Factories\DriverFactory;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Support\Actors;

/*
| UI-03: lists are server-side (yajra) — the server pages, filters and searches; the page only
| ever receives one page of rows.
*/

function driverRows($test, array $query = [])
{
    return $test->withHeaders(DriverAdmin::ajax())
        ->get(route('admin.driver.index', array_merge(['draw' => 1, 'start' => 0, 'length' => 10], $query)))
        ->assertOk();
}

describe('drivers', function () {
    it('pages on the server', function () {
        $owner = Actors::companyOwner();
        DriverFactory::new()->forCompany(DriverAdmin::companyOf($owner))->count(12)->create();
        $this->actingAs($owner);

        $first = driverRows($this, ['length' => 5]);
        $last = driverRows($this, ['start' => 10, 'length' => 5]);

        expect($first->json('recordsTotal'))->toBe(12)
            ->and($first->json('data'))->toHaveCount(5)
            ->and($last->json('data'))->toHaveCount(2);
    });

    it('filters by status and searches by name', function () {
        $owner = Actors::companyOwner();
        $company = DriverAdmin::companyOf($owner);
        DriverFactory::new()->forCompany($company)->status('active')->create(['first_name' => 'Amelia']);
        DriverFactory::new()->forCompany($company)->status('inactive')->create(['first_name' => 'Qxborisq']);
        $this->actingAs($owner);

        expect(driverRows($this, ['status' => 'active'])->json('data.*.full_name'))->toHaveCount(1)
            ->and(driverRows($this, ['status' => 'active'])->json('data.0.full_name'))->toStartWith('Amelia')
            ->and(driverRows($this, ['search' => ['value' => 'xboris']])->json('data.0.full_name'))->toStartWith('Qxborisq')
            ->and(driverRows($this, ['search' => ['value' => 'xboris']])->json('recordsFiltered'))->toBe(1);
    });

    it('never lists another company\'s drivers', function () {
        [$owner] = DriverAdmin::tenantWithDriver(['first_name' => 'Mine']);
        DriverFactory::new()->create(['first_name' => 'Theirs']);

        $names = driverRows($this->actingAs($owner))->json('data.*.full_name');

        expect($names)->toHaveCount(1)->and($names[0])->toStartWith('Mine');
    });

    it('renders status as a badge partial', function () {
        [$owner] = DriverAdmin::tenantWithDriver();

        expect(driverRows($this->actingAs($owner))->json('data.0.status'))->toContain('class="badge"')->toContain('data-tone=');
    });
});

describe('platform lookups', function () {
    it('pages, searches and sorts fuel types on the server', function () {
        foreach (['Diesel', 'Petrol', 'Electric', 'Hybrid'] as $name) {
            FuelType::query()->create(['name' => $name]);
        }

        $json = fn (array $query) => $this->actingAs(Actors::platformOwner(), 'platform')
            ->getJson(route('platform.lookups.fuel-types.index', ['draw' => 1, 'start' => 0, 'length' => 2] + $query), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $sorted = $json([
            'columns' => [['data' => 'DT_RowIndex', 'name' => '', 'orderable' => 'false'], ['data' => 'name', 'name' => 'name', 'orderable' => 'true', 'searchable' => 'true']],
            'order' => [['column' => 1, 'dir' => 'asc']],
        ]);

        expect($sorted->json('recordsTotal'))->toBe(4)
            ->and($sorted->json('data.*.name'))->toBe(['Diesel', 'Electric'])
            ->and($json([
                'columns' => [['data' => 'name', 'name' => 'name', 'searchable' => 'true']],
                'search' => ['value' => 'hyb'],
            ])->json('data.*.name'))->toBe(['Hybrid']);
    });
});
