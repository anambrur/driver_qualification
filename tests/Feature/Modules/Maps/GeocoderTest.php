<?php

use App\Services\Maps\Geocoder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Support\Actors;

/*
| Address search for the map picker (req 6.1, 14 §4): Photon or Nominatim behind one interface,
| US and Canada only, an identifying User-Agent, cached by normalised query, one request per
| second for the whole app, a per-user route throttle, and "none" for manual entry only.
*/

beforeEach(function () {
    Shippers::seedStates();
    config(['maps.geocoder.driver' => 'photon', 'maps.geocoder.contact' => 'maps@dq.test', 'app.name' => 'DQ']);
});

function photonFeature(array $props, array $lngLat): array
{
    return ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => $lngLat], 'properties' => $props];
}

function photonResults(): array
{
    return ['type' => 'FeatureCollection', 'features' => [
        photonFeature(['osm_type' => 'W', 'osm_id' => 123, 'name' => 'Acme Foods DC', 'housenumber' => '1200', 'street' => 'Commerce St',
            'city' => 'Dallas', 'state' => 'Texas', 'postcode' => '75202', 'countrycode' => 'US', 'type' => 'house'], [-96.797, 32.7767]),
        photonFeature(['osm_type' => 'R', 'osm_id' => 7, 'name' => 'Toronto', 'state' => 'Ontario', 'countrycode' => 'CA', 'type' => 'city'], [-79.38, 43.65]),
        photonFeature(['osm_type' => 'N', 'osm_id' => 9, 'name' => 'Monterrey', 'state' => 'Nuevo León', 'countrycode' => 'MX', 'type' => 'city'], [-100.3, 25.7]),
    ]];
}

function geocode($test, string $q)
{
    return $test->getJson(route('maps.search', ['q' => $q]))->assertOk();
}

it('searches Photon in the US and Canada and maps the results', function () {
    Http::fake(['photon.komoot.io/api*' => Http::response(photonResults())]);
    $this->actingAs(Actors::companyOwner());

    $results = geocode($this, '  Acme   FOODS ')->assertJsonPath('message', null)->json('results');

    expect($results)->toHaveCount(2)
        ->and($results[0])->toMatchArray([
            'address_line1' => '1200 Commerce St', 'city' => 'Dallas', 'state_code' => 'TX', 'zip' => '75202', 'country_code' => 'US',
            'latitude' => 32.7767, 'longitude' => -96.797, 'place_source' => 'photon', 'place_ref' => 'W123',
        ])
        ->and($results[0]['label'])->toBe('Acme Foods DC, 1200 Commerce St, Dallas, Texas, 75202, US')
        ->and($results[1])->toMatchArray(['city' => 'Toronto', 'state_code' => 'ON', 'country_code' => 'CA', 'place_ref' => 'R7', 'address_line1' => null]);

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'q=acme+foods')
        && str_contains($request->url(), '&countrycode=US&countrycode=CA')
        && $request->header('User-Agent') === ['DQ/1.0 (+'.config('app.url').'; maps@dq.test)']);
});

it('answers a repeated query from the cache', function () {
    Http::fake(['photon.komoot.io/*' => Http::response(photonResults())]);
    $this->actingAs(Actors::companyOwner());

    geocode($this, 'Acme Foods');
    RateLimiter::clear('maps:geocoder:photon');
    geocode($this, 'acme  foods')->assertJsonCount(2, 'results');

    Http::assertSentCount(1);
});

it('keeps to one request per second for the whole app', function () {
    Http::fake(['photon.komoot.io/*' => Http::response(photonResults())]);

    geocode($this->actingAs(Actors::companyOwner()), 'first query');
    geocode($this->actingAs(Actors::companyOwner()), 'second query')
        ->assertJsonPath('results', [])->assertJsonPath('message', 'Address search is busy. Try again in a moment.');

    Http::assertSentCount(1);

    // The refusal was not cached
    RateLimiter::clear('maps:geocoder:photon');
    geocode($this, 'second query')->assertJsonCount(2, 'results');
});

it('answers a service error with no results and a message, and does not cache it', function () {
    Http::fake(['photon.komoot.io/*' => Http::sequence()->push('Bad gateway', 502)->push(photonResults())]);
    $this->actingAs(Actors::companyOwner());

    geocode($this, 'dallas')->assertJsonPath('results', [])
        ->assertJsonPath('message', 'Address search is unavailable right now. Enter the address by hand.');

    RateLimiter::clear('maps:geocoder:photon');
    geocode($this, 'dallas')->assertJsonCount(2, 'results');
});

it('answers a timeout with no results and a message', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));
    $this->actingAs(Actors::companyOwner());

    geocode($this, 'dallas')->assertJsonPath('results', [])
        ->assertJsonPath('message', 'Address search is unavailable right now. Enter the address by hand.');
});

it('sends a short timeout', function () {
    config(['maps.geocoder.timeout' => 3]);
    $sent = [];
    Http::fake(function (HttpRequest $request, array $options) use (&$sent) {
        $sent = $options;

        return Http::response(photonResults());
    });

    app(Geocoder::class)->search('dallas');

    expect($sent)->toMatchArray(['timeout' => 3, 'connect_timeout' => 3]);
});

it('turns search off with the none driver and keeps manual entry', function () {
    config(['maps.geocoder.driver' => 'none']);
    Http::fake();
    $this->actingAs(Actors::companyOwner());

    geocode($this, 'dallas')->assertJsonPath('results', [])->assertJsonPath('message', 'Address search is off. Enter the address by hand.');
    $this->get(route('shippers.create'))->assertOk()
        ->assertSee('data-address-picker', false)->assertDontSee('data-picker-search', false)->assertSee('name="address_line1"', false);

    Http::assertNothingSent();
});

it('searches Nominatim as the second driver', function () {
    config(['maps.geocoder.driver' => 'nominatim']);
    Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([
        ['osm_type' => 'way', 'osm_id' => 55, 'lat' => '32.7767', 'lon' => '-96.797', 'display_name' => 'Acme, 1200 Commerce St, Dallas, TX',
            'address' => ['house_number' => '1200', 'road' => 'Commerce St', 'city' => 'Dallas', 'state' => 'Texas',
                'ISO3166-2-lvl4' => 'US-TX', 'postcode' => '75202', 'country_code' => 'us']],
        ['osm_type' => 'node', 'osm_id' => 1, 'lat' => '25.7', 'lon' => '-100.3', 'address' => ['country_code' => 'mx']],
    ])]);
    $this->actingAs(Actors::companyOwner());

    $results = geocode($this, 'acme dallas')->json('results');

    expect($results)->toHaveCount(1)->and($results[0])->toMatchArray([
        'address_line1' => '1200 Commerce St', 'city' => 'Dallas', 'state_code' => 'TX', 'country_code' => 'US',
        'place_source' => 'nominatim', 'place_ref' => 'W55', 'latitude' => 32.7767,
    ]);
    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'countrycodes=us%2Cca') && str_contains($request->url(), 'format=jsonv2'));
});

it('finds the address at a pin', function () {
    Http::fake(['photon.komoot.io/reverse*' => Http::response(['features' => [photonResults()['features'][0]]])]);
    $this->actingAs(Actors::companyOwner());

    $this->getJson(route('maps.reverse', ['lat' => 32.7767, 'lng' => -96.797]))->assertOk()
        ->assertJsonPath('results.0.place_ref', 'W123')->assertJsonPath('results.0.state_code', 'TX');
    $this->getJson(route('maps.reverse', ['lat' => 95, 'lng' => -96.797]))->assertUnprocessable();

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/reverse?lat=32.7767&lon=-96.797'));
});

it('throttles each user on the search route', function () {
    Http::fake(['photon.komoot.io/*' => Http::response(photonResults())]);
    $this->actingAs(Actors::companyOwner());

    foreach (range(1, 30) as $i) {
        geocode($this, 'dallas');
    }

    $this->getJson(route('maps.search', ['q' => 'dallas']))->assertTooManyRequests();
});

it('keeps an empty answer for a day only', function () {
    Http::fake(['photon.komoot.io/*' => Http::sequence()->push(['features' => []])->push(photonResults())]);
    $this->actingAs(Actors::companyOwner());

    geocode($this, 'new warehouse')->assertJsonPath('results', [])->assertJsonPath('message', 'No matching address in the US or Canada.');
    RateLimiter::clear('maps:geocoder:photon');
    geocode($this, 'new warehouse')->assertJsonPath('results', []);
    Http::assertSentCount(1);

    $this->travel(25)->hours();
    RateLimiter::clear('maps:geocoder:photon');
    geocode($this, 'new warehouse')->assertJsonCount(2, 'results');
});

it('refuses a query shorter than three characters', function () {
    Http::fake();

    $this->actingAs(Actors::companyOwner())->getJson(route('maps.search', ['q' => 'tx']))->assertUnprocessable();

    Http::assertNothingSent();
});
