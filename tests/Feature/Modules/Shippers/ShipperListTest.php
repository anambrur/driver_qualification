<?php

use App\Models\Shipper;
use Database\Factories\ShipperFactory;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Support\Actors;
use Tests\Support\Perf;

/*
| The shippers list (req 4.4): server-side, searched within the company, filtered by status,
| with a constant number of queries (12 §3).
*/

beforeEach(function () {
    Shippers::seedStates();
});

function shipperRows($test, array $query = []): array
{
    return $test->getJson(route('shippers.data').'?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => 50] + $query))
        ->assertOk()->json('data');
}

it('lists only the own company, with city and state', function () {
    $company = Actors::company();
    ShipperFactory::new()->forCompany($company)->create(['name' => 'Ours', 'city' => 'Austin', 'state_code' => 'TX', 'zip' => '73301']);
    ShipperFactory::new()->forCompany(Actors::company())->create(['name' => 'Theirs']);
    $this->actingAs($company->owner);

    $rows = shipperRows($this);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['shipper'])->toContain('Ours')
        ->and($rows[0]['place'])->toBe('Austin, TX 73301')
        ->and($rows[0]['action'])->toContain(route('shippers.edit', Shipper::withoutTenancy()->where('name', 'Ours')->value('id')));
});

it('searches and filters by status, and leaves deleted shippers out', function () {
    $company = Actors::company();
    ShipperFactory::new()->forCompany($company)->create(['name' => 'Acme Foods', 'city' => 'Dallas']);
    ShipperFactory::new()->forCompany($company)->inactive()->create(['name' => 'Bolt Paper', 'city' => 'Houston']);
    ShipperFactory::new()->forCompany($company)->create(['name' => 'Gone Co'])->delete();
    $this->actingAs($company->owner);

    $names = fn (array $query) => collect(shipperRows($this, $query))->map(fn ($row) => strip_tags($row['shipper']))
        ->map(fn ($cell) => trim(preg_split('/\s{2,}/', trim($cell))[0]))->all();

    expect($names([]))->toEqualCanonicalizing(['Acme Foods', 'Bolt Paper'])
        ->and($names(['status' => 'active']))->toBe(['Acme Foods'])
        ->and($names(['status' => 'inactive']))->toBe(['Bolt Paper'])
        ->and($names(['search' => ['value' => 'houst']]))->toBe(['Bolt Paper'])
        ->and($names(['search' => ['value' => '100%']]))->toBe([]);
});

it('escapes shipper names in the list', function () {
    $company = Actors::company();
    ShipperFactory::new()->forCompany($company)->create(['name' => '<script>alert(1)</script>', 'city' => '<b>x</b>']);
    $this->actingAs($company->owner);

    $row = shipperRows($this)[0];

    expect($row['shipper'])->not->toContain('<script>')->and($row['place'])->not->toContain('<b>');
});

it('keeps the list query count constant', function () {
    $company = Actors::company();
    $this->actingAs($company->owner);

    Perf::assertConstantQueries(
        fn (int $n) => ShipperFactory::new()->forCompany($company)->count($n)->create(),
        fn () => shipperRows($this),
        small: 2, large: 25,
    );
});
