<?php

namespace App\DataTables\Fleet;

use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side vehicle list (admin/vehicle). The Vehicle company scope keeps it tenant-bound.
 * Status "" lists deleted vehicles too (with Restore), "active" only live ones, "deleted" only
 * deleted ones (PO-09).
 */
final class VehicleTable
{
    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $user = $this->request->user();
        $canEdit = (bool) $user?->can('vehicles.edit');
        $canDelete = (bool) $user?->can('vehicles.delete');

        $query = Vehicle::query()
            ->select(['vehicles.id', 'vehicles.company_id', 'unit_no', 'vin', 'year', 'make', 'model', 'odometer', 'notes',
                'vehicle_type_id', 'vehicle_group_id', 'fuel_type_id', 'vehicles.created_at', 'vehicles.deleted_at'])
            ->with(['vehicleType:id,name', 'vehicleGroup:id,name', 'fuelType:id,name']);

        match ((string) $this->request->input('status', '')) {
            'deleted' => $query->onlyTrashed(),
            'active' => null,
            default => $query->withTrashed(),
        };

        if ($this->request->filled('vehicle_type_id')) {
            $query->where('vehicle_type_id', (int) $this->request->input('vehicle_type_id'));
        }

        // The table's search box, or `search_text` from older clients (FLT-05)
        $term = trim((string) ($this->request->input('search_text') ?: data_get($this->request->input('search'), 'value', '')));
        if ($term !== '') {
            $query->search($term);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('vehicle_info', fn (Vehicle $vehicle) => view('admin.vehicle.partials.unit-cell', [
                'icon' => 'truck', 'unit' => $vehicle->unit_no, 'title' => trim($vehicle->year.' '.$vehicle->make.' '.$vehicle->model),
                'vin' => $vehicle->vin, 'notes' => filled($vehicle->notes),
            ])->render())
            ->addColumn('type_group', fn (Vehicle $vehicle) => view('admin.vehicle.partials.pair-cell', [
                'rows' => ['Type' => $vehicle->vehicleType?->name, 'Group' => $vehicle->vehicleGroup?->name],
            ])->render())
            ->addColumn('fuel_odometer', fn (Vehicle $vehicle) => view('admin.vehicle.partials.pair-cell', [
                'rows' => ['Fuel' => $vehicle->fuelType?->name, 'Odometer' => number_format((int) $vehicle->odometer).' mi'],
            ])->render())
            ->addColumn('status', fn (Vehicle $vehicle) => view('partials.ui.status-badge', ['status' => $vehicle->deleted_at ? 'deleted' : 'active'])->render())
            ->addColumn('created_at_formatted', fn (Vehicle $vehicle) => $vehicle->created_at?->format('M d, Y'))
            ->addColumn('action', fn (Vehicle $vehicle) => view('admin.vehicle.partials.row-actions', [
                'id' => $vehicle->id, 'label' => $vehicle->unit_no, 'deleted' => $vehicle->deleted_at !== null,
                'canEdit' => $canEdit, 'canDelete' => $canDelete,
            ])->render())
            ->orderColumn('vehicle_info', 'unit_no $1')
            ->orderColumn('created_at_formatted', 'vehicles.created_at $1')
            ->filter(fn () => null) // searched above
            ->rawColumns(['vehicle_info', 'type_group', 'fuel_odometer', 'status', 'action'])
            ->make(true);
    }
}
