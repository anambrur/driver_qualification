<?php

namespace App\DataTables\Fleet;

use App\Models\AssetGroup;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side asset group list (admin/asset-group). Status "active"/"inactive" filters live groups,
 * "deleted" lists only soft-deleted ones (the only place Restore shows); "" lists live groups.
 */
final class AssetGroupTable
{
    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $user = $this->request->user();
        $canEdit = (bool) $user?->can('asset-groups.edit');
        $canDelete = (bool) $user?->can('asset-groups.delete');

        $query = AssetGroup::query()
            ->select(['asset_groups.id', 'asset_groups.company_id', 'group_name', 'status', 'driver_id', 'co_driver_id', 'vehicle_id', 'trailer_id',
                'asset_groups.created_at', 'asset_groups.deleted_at'])
            ->with(['vehicle:id,unit_no,vin', 'trailer:id,unit_no,vin', 'driver:id,first_name,last_name,main_phone', 'coDriver:id,first_name,last_name,main_phone']);

        $status = (string) $this->request->input('status', '');
        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif (in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }

        $name = fn (?Driver $driver) => $driver ? trim($driver->first_name.' '.$driver->last_name) : null;

        // The table's search box, or `search_text` from older clients (FLT-05)
        $term = trim((string) ($this->request->input('search_text') ?: data_get($this->request->input('search'), 'value', '')));
        if ($term !== '') {
            $query->search($term);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('group_info', fn (AssetGroup $group) => view('admin.asset-group.partials.group-cell', [
                'name' => $group->group_name, 'driver' => $name($group->driver),
            ])->render())
            ->addColumn('drivers_info', fn (AssetGroup $group) => view('admin.vehicle.partials.pair-cell', ['rows' => array_filter([
                'Primary' => trim(($name($group->driver) ?? '—').($group->driver?->main_phone ? ' · '.$group->driver->main_phone : '')),
                'Secondary' => $group->coDriver ? trim($name($group->coDriver).($group->coDriver->main_phone ? ' · '.$group->coDriver->main_phone : '')) : null,
            ], fn ($value) => $value !== null)])->render())
            ->addColumn('assets_info', fn (AssetGroup $group) => view('admin.vehicle.partials.pair-cell', ['rows' => [
                'Vehicle' => $group->vehicle ? $group->vehicle->unit_no.' · VIN '.$group->vehicle->vin : null,
                'Trailer' => $group->trailer ? $group->trailer->unit_no.' · VIN '.$group->trailer->vin : null,
            ]])->render())
            ->addColumn('status', fn (AssetGroup $group) => view('partials.ui.status-badge', ['status' => $group->deleted_at ? 'deleted' : $group->status])->render())
            ->addColumn('created_at_formatted', fn (AssetGroup $group) => $group->created_at?->format('M d, Y'))
            ->addColumn('action', fn (AssetGroup $group) => view('admin.vehicle.partials.row-actions', [
                'id' => $group->id, 'label' => $group->group_name, 'deleted' => $group->deleted_at !== null,
                'canEdit' => $canEdit, 'canDelete' => $canDelete,
            ])->render())
            ->orderColumn('group_info', 'group_name $1')
            ->orderColumn('created_at_formatted', 'asset_groups.created_at $1')
            ->filter(fn () => null) // searched above
            ->rawColumns(['group_info', 'drivers_info', 'assets_info', 'status', 'action'])
            ->make(true);
    }
}
