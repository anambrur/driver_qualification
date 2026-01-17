<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\FuelType;
use App\Models\VehicleType;
use App\Models\VehicleGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Vehicle::with(['vehicleType', 'vehicleGroup', 'fuelType'])
                ->select(['vehicles.*']);

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('vehicle_info', function ($row) {
                    return '
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-600">
                                <i class="fas fa-truck"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                ' . $row->unit_no . ' - ' . $row->year . ' ' . $row->make . ' ' . $row->model . '
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                VIN: ' . $row->vin . '
                            </div>
                        </div>
                    </div>';
                })
                ->addColumn('type_group', function ($row) {
                    $type = $row->vehicleType ? $row->vehicleType->name : '<span class="text-gray-400">N/A</span>';
                    $group = $row->vehicleGroup ? $row->vehicleGroup->name : '<span class="text-gray-400">N/A</span>';
                    return '
                    <div class="text-sm">
                        <div><span class="font-medium">Type:</span> ' . $type . '</div>
                        <div><span class="font-medium">Group:</span> ' . $group . '</div>
                    </div>';
                })
                ->addColumn('fuel_odometer', function ($row) {
                    $fuel = $row->fuelType ? $row->fuelType->name : '<span class="text-gray-400">N/A</span>';
                    return '
                    <div class="text-sm">
                        <div><span class="font-medium">Fuel:</span> ' . $fuel . '</div>
                        <div><span class="font-medium">Odometer:</span> ' . number_format($row->odometer) . ' mi</div>
                    </div>';
                })
                ->addColumn('status', function ($row) {
                    $status = $row->deleted_at ? 'deleted' : 'active';
                    $badgeClass = $status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                    $statusText = $status === 'active' ? 'Active' : 'Deleted';

                    return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . $badgeClass . '">
                        ' . $statusText . '
                    </span>';
                })
                ->addColumn('action', function ($row) {
                    $editBtn = '<button onclick="editVehicle(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors mr-2">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>';

                    if ($row->deleted_at) {
                        $deleteBtn = '<button onclick="restoreVehicle(' . $row->id . ', \'' . addslashes($row->unit_no) . '\')" 
                                    class="inline-flex items-center px-3 py-1 text-sm text-green-600 bg-green-100 rounded-md hover:bg-green-200 transition-colors">
                                    <i class="fas fa-trash-restore mr-1"></i> Restore
                                </button>';
                    } else {
                        $deleteBtn = '<button onclick="deleteVehicle(' . $row->id . ', \'' . addslashes($row->unit_no) . '\')" 
                                    class="inline-flex items-center px-3 py-1 text-sm text-red-600 bg-red-100 rounded-md hover:bg-red-200 transition-colors">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </button>';
                    }

                    return '<div class="flex justify-center space-x-1">' . $editBtn . $deleteBtn . '</div>';
                })
                ->addColumn('created_at_formatted', function ($row) {
                    return $row->created_at->format('M d, Y');
                })
                ->rawColumns(['vehicle_info', 'type_group', 'fuel_odometer', 'status', 'action'])
                ->make(true);
        }

        // Get dropdown data for filters
        $vehicleTypes = VehicleType::orderBy('name')->get();
        $vehicleGroups = VehicleGroup::orderBy('name')->get();
        $fuelTypes = FuelType::orderBy('name')->get();

        return view('admin.vehicle.index', compact('vehicleTypes', 'vehicleGroups', 'fuelTypes'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'unit_no' => 'required|string|max:50|unique:vehicles,unit_no',
            'vin' => 'required|string|size:17|unique:vehicles,vin',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
            'owned_by' => 'required|in:company,lease,rental',
            'color' => 'nullable|string|max:50',
            'title_no' => 'nullable|string|max:100',
            'tire_size' => 'nullable|string|max:50',
            'odometer' => 'required|integer|min:0',
            'gvw' => 'nullable|integer|min:0',
            'vehicle_group_id' => 'nullable|exists:vehicle_groups,id',
            'fuel_type_id' => 'nullable|exists:fuel_types,id',
            'engine_type' => 'nullable|string|max:100',
            'transmission' => 'nullable|string|max:100',
            'suspension' => 'nullable|string|max:100',
            'no_axles' => 'nullable|integer|min:1|max:10',
            'configuration' => 'nullable|in:conventional,cabover',
            'wheel_base' => 'nullable|integer|min:0',
            'size_dimension' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $vehicle = Vehicle::create($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle created successfully!',
                'data' => $vehicle
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vehicle store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create vehicle. Please try again.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $vehicle = Vehicle::with(['vehicleType', 'vehicleGroup', 'fuelType'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $vehicle
        ]);
    }

    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'unit_no' => 'required|string|max:50|unique:vehicles,unit_no,' . $id,
            'vin' => 'required|string|size:17|unique:vehicles,vin,' . $id,
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
            'owned_by' => 'required|in:company,lease,rental',
            'color' => 'nullable|string|max:50',
            'title_no' => 'nullable|string|max:100',
            'tire_size' => 'nullable|string|max:50',
            'odometer' => 'required|integer|min:0',
            'gvw' => 'nullable|integer|min:0',
            'vehicle_group_id' => 'nullable|exists:vehicle_groups,id',
            'fuel_type_id' => 'nullable|exists:fuel_types,id',
            'engine_type' => 'nullable|string|max:100',
            'transmission' => 'nullable|string|max:100',
            'suspension' => 'nullable|string|max:100',
            'no_axles' => 'nullable|integer|min:1|max:10',
            'configuration' => 'nullable|in:conventional,cabover',
            'wheel_base' => 'nullable|integer|min:0',
            'size_dimension' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $vehicle->update($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle updated successfully!',
                'data' => $vehicle
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vehicle update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle. Please try again.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $vehicle = Vehicle::findOrFail($id);

        DB::beginTransaction();

        try {
            $vehicle->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vehicle delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vehicle. Please try again.'
            ], 500);
        }
    }

    public function restore($id)
    {
        $vehicle = Vehicle::withTrashed()->findOrFail($id);

        DB::beginTransaction();

        try {
            $vehicle->restore();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle restored successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vehicle restore error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore vehicle. Please try again.'
            ], 500);
        }
    }

    public function getDropdownData()
    {
        $vehicleTypes = VehicleType::orderBy('name')->get(['id', 'name']);
        $vehicleGroups = VehicleGroup::orderBy('name')->get(['id', 'name']);
        $fuelTypes = FuelType::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'vehicleTypes' => $vehicleTypes,
            'vehicleGroups' => $vehicleGroups,
            'fuelTypes' => $fuelTypes,
            'configurations' => Vehicle::getConfigurationOptions(),
            'ownedByOptions' => Vehicle::getOwnedByOptions()
        ]);
    }
}
