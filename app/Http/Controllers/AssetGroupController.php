<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Models\AssetGroup;
use App\Traits\CompanyFilterTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class AssetGroupController extends Controller
{
    use CompanyFilterTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = $this->scopedGroups()->with(['vehicle', 'trailer'])
                ->select(['asset_groups.*']);

            // Status filter: active / inactive, or the soft-deleted groups (the only place Restore shows)
            $status = $request->input('status');
            if ($status === 'deleted') {
                $query->onlyTrashed();
            } elseif (in_array($status, ['active', 'inactive'], true)) {
                $query->where('status', $status);
            }

            $searchText = $request->input('search_text');
            if (is_string($searchText) && trim($searchText) !== '') {
                $query->search(trim($searchText));
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('group_info', function ($row) {
                    $statusBadge = $row->status === 'active'
                        ? '<span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>'
                        : '<span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>';

                    return '
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-600">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white flex items-center">
                                ' . e($row->group_name) . '
                                ' . $statusBadge . '
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Primary Driver: ' . e($row->primary_driver_name ?: 'N/A') . '
                            </div>
                        </div>
                    </div>';
                })
                ->addColumn('drivers_info', function ($row) {
                    $primary = $row->primary_driver_name ?
                        '<div class="text-sm">
                            <div><span class="font-medium">Primary:</span> ' . e($row->primary_driver_name) . '</div>
                            <div><span class="font-medium">Phone:</span> ' . e($row->primary_driver_phone ?: 'N/A') . '</div>
                        </div>' :
                        '<div class="text-sm text-gray-400">No primary driver</div>';

                    $secondary = $row->second_driver_name ?
                        '<div class="text-sm mt-2">
                            <div><span class="font-medium">Secondary:</span> ' . e($row->second_driver_name) . '</div>
                            <div><span class="font-medium">Phone:</span> ' . e($row->second_driver_phone ?: 'N/A') . '</div>
                        </div>' : '';

                    return $primary . $secondary;
                })
                ->addColumn('assets_info', function ($row) {
                    $vehicle = $row->vehicle ?
                        '<div class="text-sm">
                            <div><span class="font-medium">Vehicle:</span> ' . e($row->vehicle->unit_no) . '</div>
                            <div><span class="font-medium">VIN:</span> ' . e($row->vehicle->vin) . '</div>
                        </div>' :
                        '<div class="text-sm text-gray-400">No vehicle assigned</div>';

                    $trailer = $row->trailer ?
                        '<div class="text-sm mt-2">
                            <div><span class="font-medium">Trailer:</span> ' . e($row->trailer->unit_no) . '</div>
                            <div><span class="font-medium">VIN:</span> ' . e($row->trailer->vin) . '</div>
                        </div>' :
                        '<div class="text-sm text-gray-400 mt-2">No trailer assigned</div>';

                    return $vehicle . $trailer;
                })
                ->addColumn('status', function ($row) {
                    $status = $row->deleted_at ? 'deleted' : $row->status;
                    if ($status === 'deleted') {
                        $badgeClass = 'bg-red-100 text-red-800';
                        $statusText = 'Deleted';
                    } elseif ($status === 'active') {
                        $badgeClass = 'bg-green-100 text-green-800';
                        $statusText = 'Active';
                    } else {
                        $badgeClass = 'bg-gray-100 text-gray-800';
                        $statusText = 'Inactive';
                    }

                    return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . $badgeClass . '">
                        ' . $statusText . '
                    </span>';
                })
                ->addColumn('action', function ($row) {
                    // A deleted group can only be restored (edit/update look up non-deleted groups only)
                    if ($row->deleted_at) {
                        return '<div class="flex justify-center space-x-1"><button data-action="restore" data-id="' . $row->id . '" data-name="' . e($row->group_name) . '"
                                    class="inline-flex items-center px-3 py-1 text-sm text-green-600 bg-green-100 rounded-md hover:bg-green-200 transition-colors">
                                    <i class="fas fa-trash-restore mr-1"></i> Restore
                                </button></div>';
                    }

                    $editBtn = '<button onclick="editAssetGroup(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors mr-2">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>';

                    $deleteBtn = '<button data-action="delete" data-id="' . $row->id . '" data-name="' . e($row->group_name) . '"
                                class="inline-flex items-center px-3 py-1 text-sm text-red-600 bg-red-100 rounded-md hover:bg-red-200 transition-colors">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>';

                    return '<div class="flex justify-center space-x-1">' . $editBtn . $deleteBtn . '</div>';
                })
                ->addColumn('created_at_formatted', function ($row) {
                    return $row->created_at->format('M d, Y');
                })
                ->rawColumns(['group_info', 'drivers_info', 'assets_info', 'status', 'action'])
                ->make(true);
        }

        // Get dropdown data for filters
        $vehicles = $this->applyCompanyFilter(Vehicle::query())->orderBy('unit_no')->get();
        $trailers = $this->applyCompanyFilter(Trailer::query())->orderBy('unit_no')->get();
        $drivers = $this->applyCompanyFilter(Driver::where('status', 'active'))->get();

        return view('admin.asset-group.index', compact('vehicles', 'trailers', 'drivers'));
    }

    public function store(Request $request)
    {
        $companyId = $this->owningCompanyId($request);

        $validator = Validator::make($request->all(), [
            'group_name' => ['required', 'string', 'max:100', $this->uniqueGroupNameRule($companyId)],
            'driver_id' => ['required', Rule::exists('drivers', 'id')->where('company_id', $companyId)],
            'primary_driver_phone' => 'nullable|string|max:20',
            'primary_driver_email' => 'nullable|email|max:100',
            'second_driver_name' => 'nullable|string|max:100',
            'second_driver_phone' => 'nullable|string|max:20',
            'second_driver_email' => 'nullable|email|max:100',
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('company_id', $companyId)],
            'trailer_id' => ['nullable', Rule::exists('trailers', 'id')->where('company_id', $companyId)],
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $assetGroup = AssetGroup::create([
                'company_id' => $companyId,
                'group_name' => $request->group_name,
                'driver_id' => $request->driver_id,
                'primary_driver_name' => $this->driverName((int) $request->driver_id),
                'primary_driver_phone' => $request->primary_driver_phone,
                'primary_driver_email' => $request->primary_driver_email,
                'second_driver_name' => $request->second_driver_name,
                'second_driver_phone' => $request->second_driver_phone,
                'second_driver_email' => $request->second_driver_email,
                'vehicle_id' => $request->vehicle_id,
                'trailer_id' => $request->trailer_id,
                'status' => $request->status,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asset group created successfully!',
                'data' => $assetGroup
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AssetGroup store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create asset group. Please try again.'
            ], 500);
        }
    }

    public function edit($id)
    {
        // Only the driver's id and name: the select needs them when the driver is no longer active
        $assetGroup = $this->scopedGroups()
            ->with(['vehicle', 'trailer', 'driver:id,first_name,last_name,status'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $assetGroup
        ]);
    }

    public function update(Request $request, $id)
    {
        $assetGroup = $this->scopedGroups()->findOrFail($id);
        $companyId = $this->owningCompanyId($request);

        $validator = Validator::make($request->all(), [
            'group_name' => ['required', 'string', 'max:100', $this->uniqueGroupNameRule($companyId, $assetGroup->id)],
            'driver_id' => ['required', Rule::exists('drivers', 'id')->where('company_id', $companyId)],
            'primary_driver_phone' => 'nullable|string|max:20',
            'primary_driver_email' => 'nullable|email|max:100',
            'second_driver_name' => 'nullable|string|max:100',
            'second_driver_phone' => 'nullable|string|max:20',
            'second_driver_email' => 'nullable|email|max:100',
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('company_id', $companyId)],
            'trailer_id' => ['nullable', Rule::exists('trailers', 'id')->where('company_id', $companyId)],
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $assetGroup->update(array_merge($validator->validated(), [
                'company_id' => $companyId,
                'primary_driver_name' => $this->driverName((int) $request->driver_id),
            ]));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asset group updated successfully!',
                'data' => $assetGroup
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AssetGroup update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update asset group. Please try again.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $assetGroup = $this->scopedGroups()->findOrFail($id);

        DB::beginTransaction();

        try {
            $assetGroup->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asset group deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AssetGroup delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete asset group. Please try again.'
            ], 500);
        }
    }

    public function restore($id)
    {
        $assetGroup = $this->scopedGroups()->withTrashed()->findOrFail($id);

        DB::beginTransaction();

        try {
            $assetGroup->restore();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asset group restored successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AssetGroup restore error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore asset group. Please try again.'
            ], 500);
        }
    }

    public function getDropdownData()
    {
        $vehicles = $this->applyCompanyFilter(Vehicle::query())
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'vin', 'make', 'model', 'year']);

        $trailers = $this->applyCompanyFilter(Trailer::query())
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'vin', 'make', 'model', 'year']);

        return response()->json([
            'success' => true,
            'vehicles' => $vehicles,
            'trailers' => $trailers,
            'statusOptions' => AssetGroup::getStatusOptions()
        ]);
    }

    /**
     * @return Builder<AssetGroup>
     */
    private function scopedGroups(): Builder
    {
        return $this->applyCompanyFilter(AssetGroup::query());
    }

    /**
     * The group stores the driver's name for display; it always comes from the driver record.
     */
    private function driverName(int $driverId): ?string
    {
        $driver = Driver::query()->find($driverId, ['first_name', 'last_name']);

        return $driver ? trim($driver->first_name . ' ' . $driver->last_name) : null;
    }

    /**
     * The company the submitted driver, vehicle and trailer must all belong to: the tenant's own,
     * or for a super-admin the company of the chosen vehicle. -1 matches nothing.
     */
    private function owningCompanyId(Request $request): int
    {
        $companyId = $this->getUserCompanyId();

        if ($companyId !== null) {
            return $companyId;
        }

        $vehicleId = $request->input('vehicle_id');

        if (! $this->resolvedSuperAdmin || ! is_scalar($vehicleId)) {
            return -1;
        }

        return (int) (Vehicle::withTrashed()->whereKey($vehicleId)->value('company_id') ?? -1);
    }

    /**
     * Group names are unique per company (soft-deleted groups included, so a restore can't clash).
     */
    private function uniqueGroupNameRule(int $companyId, ?int $ignoreId = null): Unique
    {
        return Rule::unique('asset_groups', 'group_name')->where('company_id', $companyId)->ignore($ignoreId);
    }
}
