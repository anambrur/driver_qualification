<?php

use App\Models\AuditLog;
use App\Models\Shipper;
use App\Tenancy\TenantContext;
use Database\Factories\ShipperFactory;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Support\Actors;

/*
| Saved shippers (req 4.4, 08 §9): add, edit, activate/deactivate, soft delete. The address
| comes from the map picker or is typed in; only place_source + place_ref are kept.
*/

beforeEach(function () {
    Shippers::seedStates();
});

it('adds a shipper with the picked place', function () {
    $company = Actors::company();

    $this->actingAs($company->owner)->post(route('shippers.store'), Shippers::payload())
        ->assertRedirect(route('shippers.index'));

    $shipper = Shippers::latest($company);
    expect($shipper->only(['name', 'city', 'state_code', 'zip', 'country_code', 'place_source', 'place_ref', 'is_active']))->toEqual([
        'name' => 'Acme Foods DC', 'city' => 'Dallas', 'state_code' => 'TX', 'zip' => '75202', 'country_code' => 'US',
        'place_source' => 'photon', 'place_ref' => 'W123456', 'is_active' => true,
    ])->and($shipper->latitude)->toBe(32.7767)->and($shipper->longitude)->toBe(-96.797);
});

it('normalises a Canadian address and marks a pin without a source as manual', function () {
    $company = Actors::company();

    $this->actingAs($company->owner)->post(route('shippers.store'), Shippers::payload([
        'state_code' => 'on', 'country_code' => 'ca', 'zip' => 'm5v3l9', 'place_source' => '', 'place_ref' => 'W1',
    ]))->assertRedirect();

    expect(Shippers::latest($company)->only(['state_code', 'country_code', 'zip', 'place_source', 'place_ref']))->toEqual([
        'state_code' => 'ON', 'country_code' => 'CA', 'zip' => 'M5V 3L9', 'place_source' => 'manual', 'place_ref' => null,
    ]);
});

it('clears the place reference when there is no pin', function () {
    $company = Actors::company();

    $this->actingAs($company->owner)->post(route('shippers.store'), Shippers::payload(['latitude' => '', 'longitude' => '']))->assertRedirect();

    expect(Shippers::latest($company)->only(['latitude', 'longitude', 'place_source', 'place_ref']))->toEqual([
        'latitude' => null, 'longitude' => null, 'place_source' => null, 'place_ref' => null,
    ]);
});

it('refuses invalid input', function (array $input, string $field) {
    $company = Actors::company();

    $this->actingAs($company->owner)->from(route('shippers.create'))->post(route('shippers.store'), Shippers::payload($input))
        ->assertSessionHasErrors($field);

    expect(Shipper::withoutTenancy()->count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'no street' => [['address_line1' => ''], 'address_line1'],
    'no city' => [['city' => ''], 'city'],
    'state outside US and Canada' => [['state_code' => 'MH'], 'state_code'],
    'Canadian province with US' => [['state_code' => 'ON'], 'state_code'],
    'US state with Canada' => [['country_code' => 'CA', 'zip' => 'M5V 3L9'], 'state_code'],
    'other country' => [['country_code' => 'MX'], 'country_code'],
    'bad US ZIP' => [['zip' => '7520'], 'zip'],
    'US ZIP in Canada' => [['country_code' => 'CA', 'state_code' => 'ON', 'zip' => '75202'], 'zip'],
    'latitude without longitude' => [['longitude' => ''], 'longitude'],
    'latitude out of range' => [['latitude' => '91'], 'latitude'],
    'longitude out of range' => [['longitude' => '-181'], 'longitude'],
    'unknown place source' => [['place_source' => 'google'], 'place_source'],
    'bad place ref' => [['place_ref' => 'ChIJ123'], 'place_ref'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'long phone' => [['phone' => str_repeat('1', 21)], 'phone'],
]);

it('edits a shipper', function () {
    $company = Actors::company();
    $shipper = ShipperFactory::new()->forCompany($company)->create();

    $this->actingAs($company->owner)->get(route('shippers.edit', $shipper))->assertOk()->assertSee($shipper->name)
        ->assertSee('data-address-picker', false);

    $this->put(route('shippers.update', $shipper), Shippers::payload(['name' => 'Renamed', 'latitude' => '', 'longitude' => '']))
        ->assertRedirect(route('shippers.index'));

    expect($shipper->fresh()->only(['name', 'place_source']))->toEqual(['name' => 'Renamed', 'place_source' => null]);
});

it('deactivates and activates a shipper, keeping the row', function () {
    $company = Actors::company();
    $shipper = ShipperFactory::new()->forCompany($company)->create();
    $this->actingAs($company->owner);

    $this->post(route('shippers.deactivate', $shipper))->assertRedirect();
    expect($shipper->fresh()->is_active)->toBeFalse()
        ->and(app(TenantContext::class)->run($company->id, fn () => Shipper::query()->active()->count()))->toBe(0)
        ->and(app(TenantContext::class)->run($company->id, fn () => Shipper::query()->count()))->toBe(1);

    $this->post(route('shippers.activate', $shipper))->assertRedirect();
    expect($shipper->fresh()->is_active)->toBeTrue();
});

it('soft deletes a shipper, audits it, and then 404s on it', function () {
    $company = Actors::company();
    $shipper = ShipperFactory::new()->forCompany($company)->create();

    $this->actingAs($company->owner)->delete(route('shippers.destroy', $shipper))->assertRedirect(route('shippers.index'));

    expect(Shipper::withoutTenancy()->withTrashed()->find($shipper->id)->trashed())->toBeTrue()
        ->and(AuditLog::query()->where('company_id', $company->id)->where('action', 'shipper.deleted')
            ->where('subject_id', $shipper->id)->where('actor_id', $company->owner_user_id)->exists())->toBeTrue();

    $this->get(route('shippers.edit', $shipper->id))->assertNotFound();
    $this->post(route('shippers.activate', $shipper->id))->assertNotFound();
});

it('renders the pages as private', function () {
    $company = Actors::company();
    $shipper = ShipperFactory::new()->forCompany($company)->create();
    $this->actingAs($company->owner);

    foreach ([route('shippers.index'), route('shippers.create'), route('shippers.edit', $shipper)] as $url) {
        $this->get($url)->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
});
