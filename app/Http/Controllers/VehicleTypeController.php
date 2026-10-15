<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuditsLookups;
use App\Models\VehicleType;
use App\Tenancy\CompanyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

use function Flasher\Toastr\Prime\toastr;

class VehicleTypeController extends Controller
{
    use AuditsLookups;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = VehicleType::query();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    return '
                    <div class="flex justify-center space-x-1">
                        <a href="'.route('platform.lookups.vehicle-types.edit', $row->id).'" 
                           class="inline-flex items-center px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </a>
                        <button data-action="delete" data-id="'.$row->id.'" data-name="'.e($row->name).'" 
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

        return view('platform.lookups.vehicle-types.index');
    }

    public function create()
    {
        return view('platform.lookups.vehicle-types.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:vehicle_types,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $vehicleType = VehicleType::create([
                'name' => $request->name,
            ]);

            $this->auditLookup('created', $vehicleType);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle type created successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create vehicle type. Please try again.',
            ], 500);
        }
    }

    public function edit($id)
    {
        $vehicleType = VehicleType::findOrFail($id);

        return view('platform.lookups.vehicle-types.edit', compact('vehicleType'));
    }

    public function update(Request $request, $id)
    {
        $vehicleType = VehicleType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:vehicle_types,name,'.$id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $vehicleType->update([
                'name' => $request->name,
            ]);

            $this->auditLookup('updated', $vehicleType);
            DB::commit();

            toastr('Vehicle type updated successfully!', 'success');

            return redirect()->route('platform.lookups.vehicle-types.index');
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle type. Please try again.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        $vehicleType = VehicleType::findOrFail($id);

        if ($vehicleType->vehicles()->withoutGlobalScope(CompanyScope::class)->withTrashed()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete vehicle type because it is assigned to vehicles.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $vehicleType->delete();
            $this->auditLookup('deleted', $vehicleType);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle type deleted successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vehicle type. Please try again.',
            ], 500);
        }
    }
}
