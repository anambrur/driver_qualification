<?php

namespace App\Http\Controllers;

use App\Models\VehicleType;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

use function Flasher\Toastr\Prime\toastr;

class VehicleTypeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = VehicleType::query();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    return '
                    <div class="flex justify-center space-x-1">
                        <a href="' . route('admin.vehicle.type.edit', $row->id) . '" 
                           class="inline-flex items-center px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </a>
                        <button onclick="deleteVehicleType(' . $row->id . ', \'' . addslashes($row->name) . '\')" 
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

        return view('admin.vehicle-type.index');
    }

    public function create()
    {
        return view('admin.vehicle-type.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:vehicle_types,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $vehicleType = VehicleType::create([
                'name' => $request->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle type created successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create vehicle type. Please try again.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $vehicleType = VehicleType::findOrFail($id);
        return view('admin.vehicle-type.edit', compact('vehicleType'));
    }

    public function update(Request $request, $id)
    {
        $vehicleType = VehicleType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:vehicle_types,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $vehicleType->update([
                'name' => $request->name,
            ]);

            DB::commit();

            toastr('Vehicle type updated successfully!', 'success');
           return redirect()->route('admin.vehicle.type.index');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle type. Please try again.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $vehicleType = VehicleType::findOrFail($id);

        DB::beginTransaction();

        try {
            $vehicleType->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle type deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vehicle type. Please try again.'
            ], 500);
        }
    }
}
