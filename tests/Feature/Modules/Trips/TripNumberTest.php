<?php

use App\Models\AuditLog;
use App\Models\Trip;
use App\Services\Operations\TripService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Feature\Modules\Trips\Trips;
use Tests\Support\Actors;
use Tests\Support\QueryFailure;

/*
| Trip numbers and dates (req 4.2, 08 §9): a per-company sequence taken under a lock, a free
| number typed in while the trip is a draft, locked once confirmed; the sequence never goes back.
| The default date is today in the company's time zone (Z1).
*/

beforeEach(function () {
    Shippers::seedStates();
});

$sequence = fn ($company): ?int => DB::table('trip_number_sequences')->where('company_id', $company->id)->value('next_number');

it('numbers trips 1, 2, 3 per company and keeps companies apart', function () use ($sequence) {
    $a = Actors::company();
    $b = Actors::company();

    $numbers = collect(range(1, 3))->map(fn () => Trips::create($a)->trip_number)->all();
    $other = Trips::create($b)->trip_number;

    expect($numbers)->toBe([1, 2, 3])
        ->and($other)->toBe(1)
        ->and($sequence($a))->toBe(4)
        ->and($sequence($b))->toBe(2);
});

it('creates the sequence row of a company that has none on its first trip', function () use ($sequence) {
    $company = Actors::company();
    DB::table('trip_number_sequences')->where('company_id', $company->id)->delete();

    expect(Trips::create($company)->trip_number)->toBe(1)
        ->and($sequence($company))->toBe(2);
});

it('continues from the row the provisioner made', function () use ($sequence) {
    $company = Actors::company();
    expect($sequence($company))->toBe(1);
    DB::table('trip_number_sequences')->where('company_id', $company->id)->update(['next_number' => 41]);

    expect(Trips::create($company)->trip_number)->toBe(41);
});

it('records who created the trip, and audits it', function () {
    $company = Actors::company();
    $dispatcher = Actors::dispatcher($company);

    $trip = Trips::create($company, [], $dispatcher);

    expect($trip->status)->toBe('draft')
        ->and($trip->getAttribute('created_by_user_id'))->toBe($dispatcher->id)
        ->and(AuditLog::query()->where('action', 'trip.created')->where('subject_id', $trip->id)->value('actor_id'))->toBe($dispatcher->id);
});

it('lets a draft take a free number and skips that number later', function () use ($sequence) {
    $company = Actors::company();
    Trips::create($company);
    $second = Trips::create($company);

    Trips::service($company, fn (TripService $trips) => $trips->updateDetails($second, Trips::details(['trip_number' => 5]), $company->owner));

    expect($second->fresh()->trip_number)->toBe(5)
        ->and($sequence($company))->toBe(3)
        ->and([Trips::create($company)->trip_number, Trips::create($company)->trip_number, Trips::create($company)->trip_number])->toBe([3, 4, 6])
        ->and(AuditLog::query()->where('action', 'trip.number_changed')->value('changes'))->toMatchArray(['from' => 2, 'to' => 5]);
});

it('refuses a number that is used, also by a deleted trip', function () {
    $company = Actors::company();
    $first = Trips::create($company);
    $second = Trips::create($company);
    $third = Trips::create($company);
    Trips::service($company, fn (TripService $trips) => $trips->delete($first));

    foreach ([1, 3] as $taken) {
        expect(fn () => Trips::service($company, fn (TripService $trips) => $trips->updateDetails($second, Trips::details(['trip_number' => $taken]), $company->owner)))
            ->toThrow(ValidationException::class, 'already used');
    }

    expect($second->fresh()->trip_number)->toBe(2)->and($third->fresh()->trip_number)->toBe(3);
});

it('never takes a number back when the newest trip is deleted', function () {
    $company = Actors::company();
    Trips::create($company);
    $second = Trips::create($company);
    Trips::service($company, fn (TripService $trips) => $trips->delete($second));

    expect(Trips::create($company)->trip_number)->toBe(3);
});

it('validates the typed number and refuses one on create', function () {
    $company = Actors::company();
    $trip = Trips::create($company);
    $this->actingAs($company->owner);

    $this->put(route('trips.update', $trip), Trips::details(['trip_number' => 0]))->assertSessionHasErrors('trip_number');
    $this->put(route('trips.update', $trip), Trips::details(['trip_number' => Trip::MAX_NUMBER + 1]))->assertSessionHasErrors('trip_number');
    $this->put(route('trips.update', $trip), Trips::details(['trip_number' => 'abc']))->assertSessionHasErrors('trip_number');
    $this->post(route('trips.store'), Trips::details(['trip_number' => 9]))->assertSessionHasErrors('trip_number');

    $this->put(route('trips.update', $trip), Trips::details(['trip_number' => 77]))->assertSessionHasNoErrors();
    expect($trip->fresh()->trip_number)->toBe(77);
});

it('locks the number and the date once confirmed', function () {
    $company = Actors::company();
    $trip = Trips::create($company, ['trip_date' => '2026-10-20']);
    $this->actingAs($company->owner);

    $this->post(route('trips.confirm-number', $trip))->assertRedirect(route('trips.show', $trip));
    $trip->refresh();

    expect($trip->number_confirmed_at)->not->toBeNull()
        ->and($trip->getAttribute('number_confirmed_by_user_id'))->toBe($company->owner->id)
        ->and(AuditLog::query()->where('action', 'trip.number_confirmed')->value('changes'))->toMatchArray(['trip_number' => 1, 'trip_date' => '2026-10-20']);

    $this->put(route('trips.update', $trip), Trips::details(['trip_number' => 9]))->assertSessionHasErrors('trip_number');
    $this->put(route('trips.update', $trip), Trips::details(['trip_date' => '2026-10-21']))->assertSessionHasErrors('trip_date');
    // The disabled fields are not posted; the same values pass too
    $this->put(route('trips.update', $trip), Trips::details(['trip_number' => 1, 'trip_date' => '2026-10-20', 'product' => 'Lumber']))->assertSessionHasNoErrors();
    $this->post(route('trips.confirm-number', $trip))->assertSessionHasErrors('trip');

    expect($trip->fresh())->trip_number->toBe(1)->trip_date->toDateString()->toBe('2026-10-20')->product->toBe('Lumber');
});

it('dates a new trip today in the company time zone', function (string $zone, string $utc, string $expected) {
    $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
    $company = Actors::company(['timezone' => $zone]);

    expect(Trips::create($company)->trip_date->toDateString())->toBe($expected);
})->with([
    'New York, after its midnight' => ['America/New_York', '2026-10-27 04:30:00', '2026-10-27'],
    'Chicago, before its midnight' => ['America/Chicago', '2026-10-27 04:30:00', '2026-10-26'],
    'Los Angeles, UTC already tomorrow' => ['America/Los_Angeles', '2026-10-27 05:30:00', '2026-10-26'],
    'Honolulu' => ['Pacific/Honolulu', '2026-10-27 09:59:00', '2026-10-26'],
]);

it('keeps a typed trip date', function () {
    expect(Trips::create(Actors::company(), ['trip_date' => '2026-11-02'])->trip_date->toDateString())->toBe('2026-11-02');
});

it('prefills the create page with today in the company time zone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-27 04:30:00', 'UTC'));
    $company = Actors::company(['timezone' => 'America/Chicago']);

    $this->actingAs($company->owner)->get(route('trips.create'))->assertOk()->assertSee('value="2026-10-26"', false);
});

it('rolls the trip and its number back when its folder cannot be made', function () use ($sequence) {
    $company = Actors::company();
    Trips::create($company);
    QueryFailure::on('folders');

    expect(fn () => Trips::create($company))->toThrow(QueryException::class);

    expect(Trip::withoutTenancy()->where('company_id', $company->id)->count())->toBe(1)
        ->and($sequence($company))->toBe(2)
        ->and(AuditLog::query()->where('action', 'trip.created')->count())->toBe(1);
});
