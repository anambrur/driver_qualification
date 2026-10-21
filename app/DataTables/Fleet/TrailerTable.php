<?php

namespace App\DataTables\Fleet;

use App\Models\Trailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side trailer list (admin/trailer). The Trailer company scope keeps it tenant-bound.
 * Status works as on the vehicle list (PO-09).
 */
final class TrailerTable
{
    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $user = $this->request->user();
        $canEdit = (bool) $user?->can('trailers.edit');
        $canDelete = (bool) $user?->can('trailers.delete');

        $query = Trailer::query()
            ->select(['trailers.id', 'trailers.company_id', 'unit_no', 'vin', 'plate_number', 'plate_state_code', 'year', 'make', 'model', 'gvw', 'owned_by', 'notes',
                'equipment_type_id', 'vehicle_group_id', 'trailers.created_at', 'trailers.deleted_at'])
            ->with(['equipmentType:id,name', 'vehicleGroup:id,name']);

        match ((string) $this->request->input('status', '')) {
            'deleted' => $query->onlyTrashed(),
            'active' => null,
            default => $query->withTrashed(),
        };

        if ($this->request->filled('equipment_type_id')) {
            $query->where('equipment_type_id', (int) $this->request->input('equipment_type_id'));
        }

        // The table's search box, or `search_text` from older clients (FLT-05)
        $term = trim((string) ($this->request->input('search_text') ?: data_get($this->request->input('search'), 'value', '')));
        if ($term !== '') {
            $query->search($term);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('trailer_info', fn (Trailer $trailer) => view('admin.vehicle.partials.unit-cell', [
                'icon' => 'container', 'unit' => $trailer->unit_no, 'title' => trim($trailer->year.' '.$trailer->make.' '.$trailer->model),
                'vin' => $trailer->vin, 'notes' => filled($trailer->notes),
                'href' => $trailer->deleted_at ? null : route('admin.trailer.show', $trailer->id),
            ])->render())
            ->addColumn('equipment_group', fn (Trailer $trailer) => view('admin.vehicle.partials.pair-cell', [
                'rows' => ['Equipment' => $trailer->equipmentType?->name, 'Group' => $trailer->vehicleGroup?->name],
            ])->render())
            ->editColumn('gvw', fn (Trailer $trailer) => $trailer->gvw ? number_format((int) $trailer->gvw).' lbs' : '—')
            ->editColumn('owned_by', fn (Trailer $trailer) => $trailer->owned_by ?: '—')
            ->addColumn('plate', fn (Trailer $trailer) => view('admin.vehicle.partials.plate-cell', [
                'number' => $trailer->plate_number, 'state' => $trailer->plate_state_code,
            ])->render())
            ->addColumn('status', fn (Trailer $trailer) => view('partials.ui.status-badge', ['status' => $trailer->deleted_at ? 'deleted' : 'active'])->render())
            ->addColumn('created_at_formatted', fn (Trailer $trailer) => $trailer->created_at?->format('M d, Y'))
            ->addColumn('action', fn (Trailer $trailer) => view('admin.vehicle.partials.row-actions', [
                'id' => $trailer->id, 'label' => $trailer->unit_no, 'deleted' => $trailer->deleted_at !== null,
                'canEdit' => $canEdit, 'canDelete' => $canDelete, 'viewUrl' => route('admin.trailer.show', $trailer->id),
            ])->render())
            ->orderColumn('trailer_info', 'unit_no $1')
            ->orderColumn('plate', 'plate_number $1')
            ->orderColumn('created_at_formatted', 'trailers.created_at $1')
            ->filter(fn () => null) // searched above
            ->rawColumns(['trailer_info', 'plate', 'equipment_group', 'status', 'action'])
            ->make(true);
    }
}
