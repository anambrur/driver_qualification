<?php

namespace App\Http\Controllers;

use App\DataTables\Lookups\LookupTable;
use App\Http\Controllers\Concerns\AuditsLookups;
use App\Models\VehicleType;
use App\Tenancy\CompanyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VehicleTypeController extends Controller
{
    use AuditsLookups;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return (new LookupTable(VehicleType::query()))->json();
        }

        return view('platform.lookups.index', ['lookup' => [
            'title' => 'Vehicle types',
            'singular' => 'vehicle type',
            'route' => 'platform.lookups.vehicle-types',
            'description' => 'The kinds of vehicle companies can register, such as tractor or straight truck.',
            'hint' => 'For example Tractor, Straight truck or Pickup.',
            'icon' => 'truck',
        ]]);
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

        return response()->json([
            'success' => true,
            'data' => $vehicleType,
        ]);
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

            return response()->json([
                'success' => true,
                'message' => 'Vehicle type updated successfully!',
                'data' => $vehicleType,
            ]);
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
