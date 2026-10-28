<?php

namespace App\Http\Controllers;

use App\DataTables\Operations\TripTable;
use App\Http\Requests\Operations\CancelTripRequest;
use App\Http\Requests\Operations\TripRequest;
use App\Http\Requests\Operations\TripStopsRequest;
use App\Models\Driver;
use App\Models\FuelType;
use App\Models\Shipper;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\Documents\FolderBrowser;
use App\Services\Operations\TripAssignmentService;
use App\Services\Operations\TripEventService;
use App\Services\Operations\TripLifecycleService;
use App\Services\Operations\TripService;
use App\Support\Ui\Toast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Trips (req 4.1–4.3, 6.1, 6.3): list, create, details, stops, number confirmation, cancel and
 * delete; assignment and the lifecycle have their own controllers (P2-3). Route model binding goes through the company scope, so
 * another company's trip is a 404.
 */
class TripController extends Controller
{
    public function __construct(private TripService $trips) {}

    public function index(Request $request): View
    {
        // Opened from a driver or vehicle page, or the dashboard: the list starts filtered by it
        $filter = null;
        if ($driver = Driver::withTrashed()->find($request->integer('driver_id') ?: null, ['id', 'first_name', 'last_name'])) {
            $filter = ['name' => 'driver_id', 'value' => $driver->id, 'label' => 'Driver: '.trim($driver->first_name.' '.$driver->last_name)];
        } elseif ($vehicle = Vehicle::withTrashed()->find($request->integer('vehicle_id') ?: null, ['id', 'unit_no'])) {
            $filter = ['name' => 'vehicle_id', 'value' => $vehicle->id, 'label' => 'Vehicle: '.$vehicle->unit_no];
        } elseif ($request->query('pickup') === 'pending') {
            $filter = ['name' => 'pickup', 'value' => 'pending', 'label' => 'Pending pickup'];
        }
        $status = in_array($request->query('status'), Trip::STATUSES, true) ? $request->query('status') : '';

        return view('trips.index', ['statuses' => Trip::STATUSES, 'filter' => $filter, 'status' => $status]);
    }

    public function data(Request $request): JsonResponse
    {
        return (new TripTable($request))->json();
    }

    public function create(): View
    {
        $trip = new Trip(['weight_unit' => 'lb']);
        $trip->trip_date = Carbon::parse($this->trips->today());

        return view('trips.create', ['trip' => $trip, 'shippers' => $this->shipperOptions(null)]);
    }

    public function store(TripRequest $request): RedirectResponse
    {
        $trip = $this->trips->create($request->validated(), $request->user());

        Toast::success("Trip {$trip->label()} created. Add its stops next.");

        return redirect()->to(route('trips.show', $trip).'#stops');
    }

    public function show(Request $request, Trip $trip, FolderBrowser $browser, TripAssignmentService $assignment, TripLifecycleService $lifecycle): View
    {
        Gate::authorize('view', $trip);

        $trip->load(['stops.shipper:id,name', 'shipper:id,name,deleted_at', 'driver:id,first_name,last_name', 'vehicle:id,unit_no', 'trailer:id,unit_no', 'equipmentConfirmedBy:id,name']);
        if ($trip->status === 'assigned') {
            $trip->driver?->load(['currentLicense:id,driver_id,expires,is_current', 'medicalCertificate:id,driver_id,document_type_id,expiry_date']);
        }
        $documentsBrowser = $browser->tab($trip, $request->integer('folder') ?: null);
        $canEdit = $trip->isEditable() && $request->user()->can('trips.edit');
        $canAssign = $trip->isAssignable() && $request->user()->can('trips.assign');
        $inspections = $request->user()->can('inspections.view')
            ? $trip->inspections()->withCount(['results', 'documents'])->with(['template' => fn ($q) => $q->withCount('items'), 'performedBy:id,name'])->get()
            : null;
        $inspections?->first()?->load(['results' => fn ($q) => $q->where('result', 'fail')->orderBy('id')]);
        // trip-events.view is denied while the company lacks trip_events (PermissionGate step 4)
        $events = $request->user()->can('trip-events.view')
            ? $trip->events()->orderByDesc('occurred_at')->orderByDesc('id')->with([
                'recordedBy:id,name',
                'documents' => fn ($q) => $q->select(['id', 'trip_event_id', 'title'])->orderBy('id'),
                'fuelPurchase.fuelType:id,name',
                'fuelPurchase.documents' => fn ($q) => $q->select(['id', 'fuel_purchase_id', 'title'])->orderBy('id'),
            ])->get()
            : null;
        $canRecordEvents = $events !== null && TripEventService::isRecordable($trip) && $request->user()->can('trip-events.manage');
        // Times are shown and typed in the company's time zone (Z1)
        $timezone = $trip->driver_id !== null ? $this->trips->timezone() : null;

        return view('trips.show', [
            'trip' => $trip,
            'documentsBrowser' => $documentsBrowser,
            'canEdit' => $canEdit,
            'canAssign' => $canAssign,
            'shippers' => $canEdit ? $this->shipperOptions($trip) : [],
            'assignOptions' => $canAssign ? $assignment->options($trip) : null,
            'groupDefaults' => $canAssign ? $assignment->groupDefaults() : [],
            'warnings' => $trip->status === 'assigned' ? $assignment->warnings($trip) : [],
            'inspections' => $inspections,
            'events' => $events,
            'canRecordEvents' => $canRecordEvents,
            'eventDefaultAt' => $canRecordEvents ? TripEventService::defaultOccurredAt($trip, (string) $timezone)->format('Y-m-d\TH:i') : null,
            'fuelTypes' => $canRecordEvents ? FuelType::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all() : [],
            'preTripBlocker' => $trip->status === 'assigned' ? $lifecycle->preTripBlocker($trip) : null,
            'timezone' => $timezone,
        ]);
    }

    public function update(TripRequest $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);
        $trip = $this->trips->updateDetails($trip, $request->validated(), $request->user());

        Toast::success("Trip {$trip->label()} saved.");

        return redirect()->to(route('trips.show', $trip).'#details');
    }

    public function updateStops(TripStopsRequest $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);
        $trip = $this->trips->saveStops($trip, $request->stops(), $request->user());

        Toast::success($trip->status === 'planned' ? "Stops saved. Trip {$trip->label()} is planned." : 'Stops saved.');

        return redirect()->to(route('trips.show', $trip).'#stops');
    }

    public function confirmNumber(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);
        $trip = $this->trips->confirmNumber($trip, $request->user());

        Toast::success($trip->status === 'planned'
            ? "Trip {$trip->label()} confirmed and planned."
            : "Trip {$trip->label()} confirmed. It becomes planned once it has a start and a drop-off.");

        return redirect()->route('trips.show', $trip);
    }

    public function cancel(CancelTripRequest $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);
        $this->trips->cancel($trip, $request->validated('cancel_reason'), $request->user());

        Toast::success("Trip {$trip->label()} cancelled.");

        return redirect()->route('trips.show', $trip);
    }

    public function destroy(Trip $trip): RedirectResponse
    {
        Gate::authorize('delete', $trip);
        $this->trips->delete($trip);

        Toast::success("Trip {$trip->label()} deleted.");

        return redirect()->route('trips.index');
    }

    /**
     * Shipper select options: active shippers, plus the trip's own ones (inactive or deleted) so
     * a saved choice still shows. Names are not unique, so the city and state tell them apart.
     *
     * @return array<int, string>
     */
    private function shipperOptions(?Trip $trip): array
    {
        $kept = $trip === null ? [] : array_filter([$trip->shipper_id, ...$trip->stops->pluck('shipper_id')->all()]);

        return Shipper::query()->withTrashed()
            ->where(fn ($query) => $query->where(fn ($q) => $q->active()->whereNull('deleted_at'))->orWhereIn('id', $kept))
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'state_code', 'is_active', 'deleted_at'])
            ->mapWithKeys(fn (Shipper $shipper) => [$shipper->id => $shipper->name.' · '.$shipper->cityState()
                .($shipper->trashed() ? ' (deleted)' : (! $shipper->is_active ? ' (inactive)' : ''))])
            ->all();
    }
}
