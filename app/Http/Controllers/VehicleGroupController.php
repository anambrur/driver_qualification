<?php

namespace App\Http\Controllers;

use App\Models\VehicleGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class VehicleGroupController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = VehicleGroup::query();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    return '
                    <div class="flex justify-center space-x-1">
                        <button onclick="editVehicleGroup(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </button>
                        <button onclick="deleteVehicleGroup(' . $row->id . ', \'' . addslashes($row->name) . '\')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-red-600 bg-red-100 rounded-md hover:bg-red-200 transition-colors">
                            <i class="fas fa-trash mr-1"></i> Delete
                        </button>
                    </div>';
                })
                ->addColumn('created_at_formatted', function ($row) {
                    return $row->created_at->format('M d, Y');
                })
                ->addColumn('updated_at_formatted', function ($row) {
                    return $row->updated_at->format('M d, Y');
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('admin.vehicle-group.index');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:vehicle_groups,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $vehicleGroup = VehicleGroup::create([
                'name' => $request->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle group created successfully!',
                'data' => $vehicleGroup
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create vehicle group. Please try again.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $vehicleGroup = VehicleGroup::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $vehicleGroup
        ]);
    }

    public function update(Request $request, $id)
    {
        $vehicleGroup = VehicleGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:vehicle_groups,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $vehicleGroup->update([
                'name' => $request->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle group updated successfully!',
                'data' => $vehicleGroup
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle group. Please try again.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $vehicleGroup = VehicleGroup::findOrFail($id);

        DB::beginTransaction();

        try {
            $vehicleGroup->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle group deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vehicle group. Please try again.'
            ], 500);
        }
    }
}
