<?php

namespace App\Http\Controllers;

use App\Http\Requests\Operations\FuelStopRequest;
use App\Http\Requests\Operations\TripEventAttachmentRequest;
use App\Http\Requests\Operations\TripEventRequest;
use App\Http\Requests\Operations\VoidTripEventRequest;
use App\Models\Document;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Services\Documents\DocumentService;
use App\Services\Operations\TripEventService;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Events and fuel stops of a trip (req 7.1–7.6), recorded by staff for the driver until the app
 * (Phase 3), shown on the trip page's Events tab. Evidence is voided, never deleted.
 */
class TripEventController extends Controller
{
    public function __construct(private TripEventService $events) {}

    public function store(TripEventRequest $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('view', $trip);
        $event = $this->events->record($trip, $request->eventData(['files']), $request->slotFiles(), $request->user());

        Toast::success("{$event->label()} recorded.");

        return $this->toEvents($trip->id);
    }

    public function storeFuel(FuelStopRequest $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('view', $trip);
        $purchase = $this->events->recordFuel($trip, $request->eventData(['receipts']), $request->receipts(), $request->user());

        $purchase->isConfirmed() ? Toast::success('Fuel stop recorded. Done.') : Toast::success('Fuel stop recorded. Upload the receipt to finish it.');
        if ($request->totalMismatch()) {
            Toast::warning('The total is not gallons × price per gallon. Check the receipt.');
        }

        return $this->toEvents($trip->id);
    }

    public function attach(TripEventAttachmentRequest $request, TripEvent $tripEvent): RedirectResponse
    {
        Gate::authorize('view', $tripEvent);
        $count = $this->events->attach($tripEvent, $request->validated('slot'), $request->uploads(), $request->user());

        Toast::success($count === 1 ? 'File added to the trip folder.' : "{$count} files added to the trip folder.");

        return $this->toEvents($tripEvent->trip_id);
    }

    public function void(VoidTripEventRequest $request, TripEvent $tripEvent): RedirectResponse
    {
        Gate::authorize('view', $tripEvent);
        $event = $this->events->void($tripEvent, $request->validated('void_reason'), $request->user());

        Toast::success("{$event->label()} voided. It stays on the trip, struck through.");

        return $this->toEvents($event->trip_id);
    }

    public function attachment(Request $request, TripEvent $tripEvent, Document $document, DocumentService $documents): StreamedResponse
    {
        Gate::authorize('view', $tripEvent);
        $purchaseId = $tripEvent->type === 'fuel' ? $tripEvent->fuelPurchase()->value('id') : null;
        abort_unless((int) $document->trip_event_id === $tripEvent->id || ($purchaseId !== null && (int) $document->fuel_purchase_id === $purchaseId), 404);
        // The trip folder's own rule (trips.view), as its Documents tab applies it
        Gate::authorize('view', $document);

        return $documents->download($document, $request->user(), inline: true);
    }

    private function toEvents(int $tripId): RedirectResponse
    {
        return redirect()->to(route('trips.show', $tripId).'#events');
    }
}
