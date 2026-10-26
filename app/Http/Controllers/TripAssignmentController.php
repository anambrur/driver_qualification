<?php

namespace App\Http\Controllers;

use App\Http\Requests\Operations\AssignTripRequest;
use App\Models\Trip;
use App\Services\Operations\TripAssignmentService;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Driver, vehicle and trailer on a trip, and the equipment confirmation (req 4.5, 4.6).
 */
class TripAssignmentController extends Controller
{
    public function __construct(private TripAssignmentService $assignment) {}

    public function update(AssignTripRequest $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('assign', $trip);
        $trip = $this->assignment->assign($trip, $request->validated(), $request->user());

        Toast::success("Trip {$trip->label()} assigned.");

        return redirect()->route('trips.show', $trip);
    }

    public function destroy(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('assign', $trip);
        $trip = $this->assignment->unassign($trip, $request->user());

        Toast::success("Trip {$trip->label()} is planned again, without a driver.");

        return redirect()->route('trips.show', $trip);
    }

    public function confirmEquipment(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('assign', $trip);
        $trip = $this->assignment->confirmEquipment($trip, $request->user());

        Toast::success('Tractor and trailer confirmed.');

        return redirect()->route('trips.show', $trip);
    }
}
