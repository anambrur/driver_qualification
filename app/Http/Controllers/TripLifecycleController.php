<?php

namespace App\Http\Controllers;

use App\Http\Requests\Operations\CompleteTripRequest;
use App\Http\Requests\Operations\TripStopTimesRequest;
use App\Models\Trip;
use App\Services\Operations\TripLifecycleService;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A trip on the road (10 §13): start, picked up, stop times and complete, recorded by staff for
 * the driver until the app (Phase 3).
 */
class TripLifecycleController extends Controller
{
    public function __construct(private TripLifecycleService $lifecycle) {}

    public function start(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);
        $trip = $this->lifecycle->start($trip, $request->user());

        Toast::success("Trip {$trip->label()} started.");

        return redirect()->route('trips.show', $trip);
    }

    public function pickedUp(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);
        $trip = $this->lifecycle->pickedUp($trip, $request->user());

        Toast::success("Trip {$trip->label()}: load picked up.");

        return redirect()->route('trips.show', $trip);
    }

    public function stopTimes(TripStopTimesRequest $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);
        $trip = $this->lifecycle->saveStopTimes($trip, $request->validated('stops'), $request->user());

        Toast::success('Stop times saved.');

        return redirect()->to(route('trips.show', $trip).'#stops');
    }

    public function complete(CompleteTripRequest $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);
        $miles = $request->validated('actual_distance_miles');
        $trip = $this->lifecycle->complete($trip, $miles === null ? null : (float) $miles, $request->user());

        Toast::success("Trip {$trip->label()} completed.");

        return redirect()->route('trips.show', $trip);
    }
}
