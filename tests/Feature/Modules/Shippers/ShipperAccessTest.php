<?php

use App\Features\Feature;
use App\Models\Shipper;
use App\Support\Navigation\CompanyMenu;
use Database\Factories\ShipperFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Support\Actors;

/*
| Who reaches the shippers pages and the address search (req 4.4, 6.1): shippers.view reads,
| shippers.manage writes, the trips feature gates both, and a read-only company can't write (Q2).
*/

beforeEach(function () {
    Shippers::seedStates();
});

/**
 * Every shippers write route with its method and parameters.
 *
 * @return list<array{0: string, 1: string}>
 */
function shipperWriteRoutes(Shipper $shipper): array
{
    return [
        ['GET', route('shippers.create')],
        ['POST', route('shippers.store')],
        ['GET', route('shippers.edit', $shipper)],
        ['PUT', route('shippers.update', $shipper)],
        ['POST', route('shippers.activate', $shipper)],
        ['POST', route('shippers.deactivate', $shipper)],
        ['DELETE', route('shippers.destroy', $shipper)],
    ];
}

it('lets shippers.view read the list but not write', function () {
    $company = Actors::company();
    $shipper = ShipperFactory::new()->forCompany($company)->create();
    $viewer = Actors::memberWithPermissions($company, ['shippers.view']);
    $this->actingAs($viewer);

    $this->get(route('shippers.index'))->assertOk()->assertDontSee(route('shippers.create'));
    $row = $this->getJson(route('shippers.data').'?draw=1&start=0&length=10')->assertOk()->json('data.0');
    expect($row['action'])->toBe('')->and($row['shipper'])->not->toContain(route('shippers.edit', $shipper));

    foreach (shipperWriteRoutes($shipper) as [$method, $url]) {
        $this->json($method, $url, Shippers::payload())->assertForbidden();
    }
    $this->getJson(route('maps.search', ['q' => 'dallas']))->assertForbidden();
});

it('refuses a member without shipper permissions', function () {
    $company = Actors::company();
    $shipper = ShipperFactory::new()->forCompany($company)->create();
    $this->actingAs(Actors::plainUser($company));

    $this->get(route('shippers.index'))->assertForbidden();
    $this->getJson(route('shippers.data'))->assertForbidden();
    foreach (shipperWriteRoutes($shipper) as [$method, $url]) {
        $this->json($method, $url, Shippers::payload())->assertForbidden();
    }
});

it('gives the Dispatcher the shippers pages', function () {
    $company = Actors::company();
    $this->actingAs(Actors::dispatcher($company));

    $this->get(route('shippers.index'))->assertOk()->assertSee(route('shippers.create'));
    $this->post(route('shippers.store'), Shippers::payload())->assertRedirect(route('shippers.index'));
});

it('refuses every write for a read-only company but keeps the list', function () {
    $company = Actors::readOnly(Actors::company());
    $shipper = ShipperFactory::new()->forCompany($company)->create();
    $this->actingAs($company->owner);

    $this->get(route('shippers.index'))->assertOk();
    foreach (shipperWriteRoutes($shipper) as [$method, $url]) {
        $this->json($method, $url, Shippers::payload())->assertForbidden();
    }
    $this->getJson(route('maps.search', ['q' => 'dallas']))->assertForbidden();
    expect($shipper->fresh()->trashed())->toBeFalse();
});

it('hides the menu and refuses the routes without the trips feature', function () {
    Http::fake();
    $company = Actors::company();
    Actors::withPlan($company, collect(Feature::cases())->reject(fn (Feature $f) => $f === Feature::Trips || $f->isLimit())->map->value->all());
    $shipper = ShipperFactory::new()->forCompany($company)->create();
    $this->actingAs($company->owner);

    $this->get(route('shippers.index'))->assertForbidden();
    $this->getJson(route('shippers.data'))->assertForbidden();
    foreach (shipperWriteRoutes($shipper) as [$method, $url]) {
        $this->json($method, $url, Shippers::payload())->assertForbidden();
    }
    $this->getJson(route('maps.search', ['q' => 'dallas']))->assertForbidden();
    $this->getJson(route('maps.reverse', ['lat' => 32.7, 'lng' => -96.8]))->assertForbidden();
    Http::assertNothingSent();

    $owner = Actors::actingAsTenant($company->owner);
    $labels = collect(CompanyMenu::for($owner, new Request))->flatMap(fn ($group) => collect($group['items'])->pluck('label'));
    expect($labels)->not->toContain('Shippers');
});

it('shows the Shippers menu item with the trips feature', function () {
    $owner = Actors::actingAsTenant(Actors::companyOwner());

    $operations = collect(CompanyMenu::for($owner, new Request))->firstWhere('label', 'Operations');
    expect(collect($operations['items'])->pluck('label'))->toContain('Shippers');
});

it('lets staff who enter trip addresses search without shippers.manage', function () {
    Http::fake(['photon.komoot.io/*' => Http::response(['features' => []])]);
    $company = Actors::company();

    $this->actingAs(Actors::memberWithPermissions($company, ['trips.create']))
        ->getJson(route('maps.search', ['q' => 'dallas']))->assertOk()->assertJsonPath('results', []);
});
