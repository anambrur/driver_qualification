<?php

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\FuelPurchase;
use App\Models\TripEvent;
use App\Services\Operations\TripEventService;
use App\Support\Ui\Toast;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Documents\Docs;
use Tests\Feature\Modules\TripEvents\TripEvents;
use Tests\Support\Actors;
use Tests\Support\QueryFailure;

/*
| Fuel stops (req 7.6, P2-5): the fuel event and its purchase written together or not at all,
| USD, the fuel type lookup, "Done" once a receipt is uploaded, the total checked with a warning,
| and a voided fuel event leaving its purchase out of the live totals.
*/

beforeEach(function () {
    $this->fuelType = TripEvents::setUp();
    $this->company = Actors::company();
    $this->trip = TripEvents::trip($this->company);
    $this->actingAs(Actors::dispatcher($this->company));
});

it('writes the fuel event and its purchase, done with the receipt', function () {
    $this->post(route('trips.fuel-stops.store', $this->trip), TripEvents::fuel($this->company, $this->fuelType, ['receipts' => [Docs::pdf('receipt.pdf')]]))
        ->assertRedirect(route('trips.show', $this->trip).'#events')->assertSessionHasNoErrors();

    $event = TripEvent::withoutTenancy()->sole();
    $purchase = FuelPurchase::withoutTenancy()->sole();
    expect($event->only(['type', 'driver_id', 'vehicle_id', 'city', 'state_code', 'address']))->toBe([
        'type' => 'fuel', 'driver_id' => $this->trip->driver_id, 'vehicle_id' => $this->trip->vehicle_id, 'city' => 'Waco', 'state_code' => 'TX', 'address' => '4600 I-35',
    ])->and($purchase->only(['trip_id', 'trip_event_id', 'fuel_type_id', 'location_name', 'state_code', 'gallons', 'price_per_gallon', 'total_amount', 'currency', 'odometer']))->toBe([
        'trip_id' => $this->trip->id, 'trip_event_id' => $event->id, 'fuel_type_id' => $this->fuelType, 'location_name' => "Love's #301", 'state_code' => 'TX',
        'gallons' => '120.500', 'price_per_gallon' => '3.899', 'total_amount' => '469.83', 'currency' => 'USD', 'odometer' => 182340,
    ])->and($purchase->purchased_at->equalTo($event->occurred_at))->toBeTrue()
        ->and($purchase->confirmed_at)->not->toBeNull()
        ->and(Document::withoutTenancy()->where('fuel_purchase_id', $purchase->id)->value('title'))->toStartWith('Fuel receipt · Fuel stop ')
        ->and(AuditLog::query()->whereIn('action', ['trip_event.recorded', 'fuel_purchase.confirmed'])->pluck('action')->sort()->values()->all())->toBe(['fuel_purchase.confirmed', 'trip_event.recorded']);
});

it('stays open without a receipt until one is uploaded, confirmed once', function () {
    $this->post(route('trips.fuel-stops.store', $this->trip), TripEvents::fuel($this->company, $this->fuelType))->assertSessionHasNoErrors();
    $purchase = FuelPurchase::withoutTenancy()->sole();
    expect($purchase->confirmed_at)->toBeNull();
    $this->get(route('trips.show', $this->trip))->assertSee('Receipt missing');

    $this->post(route('trip-events.attachments.store', $purchase->trip_event_id), ['slot' => 'other', 'files' => [Docs::pdf()]])->assertSessionHasErrors('slot');
    $this->post(route('trip-events.attachments.store', $purchase->trip_event_id), ['slot' => 'receipt', 'files' => [Docs::pdf('r1.pdf')]])->assertSessionHasNoErrors();
    $this->post(route('trip-events.attachments.store', $purchase->trip_event_id), ['slot' => 'receipt', 'files' => [Docs::pdf('r2.pdf')]])->assertSessionHasNoErrors();

    expect($purchase->refresh()->confirmed_at)->not->toBeNull()
        ->and(Document::withoutTenancy()->where('fuel_purchase_id', $purchase->id)->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'fuel_purchase.confirmed')->count())->toBe(1);
    $this->get(route('trips.show', $this->trip))->assertSee('Done')->assertDontSee('Receipt missing');
});

it('writes neither the event nor the files when the purchase fails', function () {
    Storage::fake('local');
    $before = DB::table('trip_events')->count();
    QueryFailure::on('fuel_purchases');

    expect(fn () => app(TenantContext::class)->run($this->company->id, fn () => app(TripEventService::class)->recordFuel(
        $this->trip, ['occurred_at' => TripEvents::at($this->company), 'state_code' => 'TX', 'fuel_type_id' => $this->fuelType, 'gallons' => '10', 'price_per_gallon' => '4', 'total_amount' => '40'], [Docs::pdf()], $this->company->owner,
    )))->toThrow(QueryException::class);

    expect(DB::table('trip_events')->count())->toBe($before)
        ->and(DB::table('documents')->whereNotNull('trip_event_id')->orWhereNotNull('fuel_purchase_id')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('warns, without refusing, when the total is not gallons × price', function () {
    $this->post(route('trips.fuel-stops.store', $this->trip), TripEvents::fuel($this->company, $this->fuelType, ['total_amount' => '400.00']))->assertSessionHasNoErrors();
    expect(collect(session(Toast::SESSION_KEY))->pluck('category'))->toContain('warning');
    session()->forget(Toast::SESSION_KEY);

    $this->post(route('trips.fuel-stops.store', $this->trip), TripEvents::fuel($this->company, $this->fuelType, ['total_amount' => '470.00']))->assertSessionHasNoErrors();
    expect(collect(session(Toast::SESSION_KEY))->pluck('category'))->not->toContain('warning')
        ->and(FuelPurchase::withoutTenancy()->count())->toBe(2);
});

it('needs the state, an active fuel type, gallons, price and total', function () {
    DB::table('fuel_types')->where('id', $this->fuelType)->update(['is_active' => false]);

    $this->post(route('trips.fuel-stops.store', $this->trip), TripEvents::fuel($this->company, $this->fuelType, ['fuel_state_code' => null, 'gallons' => '0', 'price_per_gallon' => null, 'total_amount' => null]))
        ->assertSessionHasErrors(['fuel_state_code', 'fuel_type_id', 'gallons', 'price_per_gallon', 'total_amount']);

    expect(TripEvent::withoutTenancy()->count())->toBe(0);
});

it('leaves a voided fuel stop\'s purchase out of the live purchases', function () {
    $this->post(route('trips.fuel-stops.store', $this->trip), TripEvents::fuel($this->company, $this->fuelType));
    $this->post(route('trips.fuel-stops.store', $this->trip), TripEvents::fuel($this->company, $this->fuelType, ['gallons' => '50', 'total_amount' => '194.95']));
    $voided = TripEvent::withoutTenancy()->oldest('id')->first();

    $this->post(route('trip-events.void', $voided), ['void_reason' => 'Duplicate'])->assertSessionHasNoErrors();

    expect(app(TenantContext::class)->run($this->company->id, fn () => FuelPurchase::query()->live()->pluck('gallons')->all()))->toBe(['50.000'])
        ->and(AuditLog::query()->where('action', 'trip_event.voided')->value('changes'))->toMatchArray(['fuel_purchase_id' => FuelPurchase::withoutTenancy()->where('trip_event_id', $voided->id)->value('id')]);
});

it('keeps a fuel type that fuel stops use from being deleted', function () {
    $this->post(route('trips.fuel-stops.store', $this->trip), TripEvents::fuel($this->company, $this->fuelType));

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->deleteJson(route('platform.lookups.fuel-types.destroy', $this->fuelType))
        ->assertStatus(400)->assertJson(['success' => false]);

    expect(DB::table('fuel_types')->where('id', $this->fuelType)->exists())->toBeTrue();
});

it('shows why a receipt was refused in the reopened dialog', function () {
    $this->from(route('trips.show', $this->trip))
        ->post(route('trips.fuel-stops.store', $this->trip), TripEvents::fuel($this->company, $this->fuelType, ['_form' => 'fuel', 'receipts' => [UploadedFile::fake()->create('receipt.heic', 100, 'image/heic')]]))
        ->assertSessionHasErrors('receipts.0');

    $this->get(route('trips.show', $this->trip))->assertSee('HEIC photos aren')->assertSee('trip-fuel-dialog', false);
});
