<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Models\AssetGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class AssetGroupController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = AssetGroup::with(['vehicle', 'trailer'])
                ->select(['asset_groups.*']);

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
                                ' . $row->group_name . '
                                ' . $statusBadge . '
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                Primary Driver: ' . ($row->primary_driver_name ?: 'N/A') . '
                            </div>
                        </div>
                    </div>';
                })
                ->addColumn('drivers_info', function ($row) {
                    $primary = $row->primary_driver_name ?
                        '<div class="text-sm">
                            <div><span class="font-medium">Primary:</span> ' . $row->primary_driver_name . '</div>
                            <div><span class="font-medium">Phone:</span> ' . ($row->primary_driver_phone ?: 'N/A') . '</div>
                        </div>' :
                        '<div class="text-sm text-gray-400">No primary driver</div>';

                    $secondary = $row->second_driver_name ?
                        '<div class="text-sm mt-2">
                            <div><span class="font-medium">Secondary:</span> ' . $row->second_driver_name . '</div>
                            <div><span class="font-medium">Phone:</span> ' . ($row->second_driver_phone ?: 'N/A') . '</div>
                        </div>' : '';

                    return $primary . $secondary;
                })
                ->addColumn('assets_info', function ($row) {
                    $vehicle = $row->vehicle ?
                        '<div class="text-sm">
                            <div><span class="font-medium">Vehicle:</span> ' . $row->vehicle->unit_no . '</div>
                            <div><span class="font-medium">VIN:</span> ' . $row->vehicle->vin . '</div>
                        </div>' :
                        '<div class="text-sm text-gray-400">No vehicle assigned</div>';

                    $trailer = $row->trailer ?
                        '<div class="text-sm mt-2">
                            <div><span class="font-medium">Trailer:</span> ' . $row->trailer->unit_no . '</div>
                            <div><span class="font-medium">VIN:</span> ' . $row->trailer->vin . '</div>
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
                    $editBtn = '<button onclick="editAssetGroup(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors mr-2">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>';

                    if ($row->deleted_at) {
                        $deleteBtn = '<button onclick="restoreAssetGroup(' . $row->id . ', \'' . addslashes($row->group_name) . '\')" 
                                    class="inline-flex items-center px-3 py-1 text-sm text-green-600 bg-green-100 rounded-md hover:bg-green-200 transition-colors">
                                    <i class="fas fa-trash-restore mr-1"></i> Restore
                                </button>';
                    } else {
                        $deleteBtn = '<button onclick="deleteAssetGroup(' . $row->id . ', \'' . addslashes($row->group_name) . '\')" 
                                    class="inline-flex items-center px-3 py-1 text-sm text-red-600 bg-red-100 rounded-md hover:bg-red-200 transition-colors">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </button>';
                    }

                    return '<div class="flex justify-center space-x-1">' . $editBtn . $deleteBtn . '</div>';
                })
                ->addColumn('created_at_formatted', function ($row) {
                    return $row->created_at->format('M d, Y');
                })
                ->rawColumns(['group_info', 'drivers_info', 'assets_info', 'status', 'action'])
                ->make(true);
        }

        // Get dropdown data for filters
        $vehicles = Vehicle::whereNull('deleted_at')->orderBy('unit_no')->get();
        $trailers = Trailer::whereNull('deleted_at')->orderBy('unit_no')->get();
        $drivers = Driver::where('status', 'active')->get();

        return view('admin.asset-group.index', compact('vehicles', 'trailers', 'drivers'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_name' => 'required|string|max:100|unique:asset_groups,group_name',
            'primary_driver_name' => 'nullable|string|max:100',
            'primary_driver_phone' => 'nullable|string|max:20',
            'primary_driver_email' => 'nullable|email|max:100',
            'second_driver_name' => 'nullable|string|max:100',
            'second_driver_phone' => 'nullable|string|max:20',
            'second_driver_email' => 'nullable|email|max:100',
            'vehicle_id' => 'required|exists:vehicles,id',
            'trailer_id' => 'nullable|exists:trailers,id',
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
            $assetGroup = AssetGroup::create($request->all());

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
        $assetGroup = AssetGroup::with(['vehicle', 'trailer'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $assetGroup
        ]);
    }

    public function update(Request $request, $id)
    {
        $assetGroup = AssetGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'group_name' => 'required|string|max:100|unique:asset_groups,group_name,' . $id,
            'primary_driver_name' => 'nullable|string|max:100',
            'primary_driver_phone' => 'nullable|string|max:20',
            'primary_driver_email' => 'nullable|email|max:100',
            'second_driver_name' => 'nullable|string|max:100',
            'second_driver_phone' => 'nullable|string|max:20',
            'second_driver_email' => 'nullable|email|max:100',
            'vehicle_id' => 'required|exists:vehicles,id',
            'trailer_id' => 'nullable|exists:trailers,id',
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
            $assetGroup->update($request->all());

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
        $assetGroup = AssetGroup::findOrFail($id);

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
        $assetGroup = AssetGroup::withTrashed()->findOrFail($id);

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
        $vehicles = Vehicle::whereNull('deleted_at')
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'vin', 'make', 'model', 'year']);

        $trailers = Trailer::whereNull('deleted_at')
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'vin', 'make', 'model', 'year']);

        return response()->json([
            'success' => true,
            'vehicles' => $vehicles,
            'trailers' => $trailers,
            'statusOptions' => AssetGroup::getStatusOptions()
        ]);
    }
}
