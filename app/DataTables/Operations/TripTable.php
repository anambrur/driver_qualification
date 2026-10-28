<?php

namespace App\DataTables\Operations;

use App\Models\Shipper;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * The company's trips (req 4.1), newest trip date first, filterable by status, date, driver,
 * vehicle (the driver and vehicle pages link here) and pending pickup (the dashboard links here). Search: a trip number, or the product or
 * shipper name.
 */
final class TripTable
{
    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $query = Trip::query()
            ->select(['id', 'company_id', 'trip_number', 'trip_date', 'number_confirmed_at', 'status', 'shipper_id', 'driver_id', 'vehicle_id', 'product'])
            ->with([
                'shipper:id,name,deleted_at',
                'stops' => fn ($q) => $q->select(['id', 'trip_id', 'sequence', 'stop_type', 'city', 'state_code'])->whereIn('stop_type', ['start', 'drop_off']),
                'driver:id,first_name,last_name',
                'vehicle:id,unit_no',
            ]);

        if (in_array($status = (string) $this->request->input('status'), Trip::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($this->request->input('pickup') === 'pending') {
            $query->pendingPickup();
        }
        foreach (['driver_id', 'vehicle_id'] as $column) {
            if (ctype_digit($id = (string) $this->request->input($column))) {
                $query->where($column, (int) $id);
            }
        }
        if ($from = $this->date('date_from')) {
            $query->where('trip_date', '>=', $from);
        }
        if ($to = $this->date('date_to')) {
            $query->where('trip_date', '<=', $to);
        }

        $term = trim((string) data_get($this->request->input('search'), 'value', ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $number = ltrim($term, '#');
            $query->where(fn (Builder $q) => $q->where('product', 'like', $like)
                ->orWhereIn('shipper_id', Shipper::withTrashed()->select('id')->where('name', 'like', $like))
                ->when(ctype_digit($number), fn ($q) => $q->orWhere('trip_number', (int) $number)));
        }

        return DataTables::eloquent($query)
            ->addColumn('number', fn (Trip $trip) => view('trips.partials.number-cell', ['trip' => $trip])->render())
            ->addColumn('date', fn (Trip $trip) => e($trip->trip_date->format('M d, Y')))
            ->addColumn('status', fn (Trip $trip) => view('partials.ui.status-badge', ['status' => $trip->status])->render())
            ->addColumn('shipper', fn (Trip $trip) => e($trip->shipper->name ?? '—'))
            ->addColumn('route', fn (Trip $trip) => e($this->route($trip)))
            ->addColumn('driver', fn (Trip $trip) => e($trip->driver !== null ? trim($trip->driver->first_name.' '.$trip->driver->last_name) : '—'))
            ->addColumn('vehicle', fn (Trip $trip) => e($trip->vehicle->unit_no ?? '—'))
            ->orderColumn('number', 'trip_number $1')
            ->orderColumn('date', 'trip_date $1, trip_number $1')
            ->filter(fn () => null) // searched above
            ->rawColumns(['number', 'status'])
            ->make(true);
    }

    private function route(Trip $trip): string
    {
        $start = $trip->stop('start')?->cityState();
        $dropOff = $trip->stop('drop_off')?->cityState();

        return $start === null && $dropOff === null ? '—' : ($start ?? '?').' → '.($dropOff ?? '?');
    }

    private function date(string $key): ?string
    {
        $value = (string) $this->request->input($key, '');

        // A real calendar date only: 2026-13-01 or 2026-02-31 are ignored, not parsed
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) === 1 && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
    }
}
