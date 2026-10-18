<?php

namespace App\Http\Controllers;

use App\DataTables\Lookups\LookupTable;
use App\Http\Controllers\Concerns\AuditsLookups;
use App\Models\VehicleGroup;
use App\Tenancy\CompanyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VehicleGroupController extends Controller
{
    use AuditsLookups;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return (new LookupTable(VehicleGroup::query()))->json();
        }

        return view('platform.lookups.index', ['lookup' => [
            'title' => 'Vehicle groups',
            'singular' => 'vehicle group',
            'route' => 'platform.lookups.vehicle-groups',
            'description' => 'Groups companies can sort their vehicles and trailers into.',
            'hint' => 'For example Long haul, Local or Spare.',
            'icon' => 'layers',
        ]]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:vehicle_groups,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $vehicleGroup = VehicleGroup::create([
                'name' => $request->name,
            ]);

            $this->auditLookup('created', $vehicleGroup);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle group created successfully!',
                'data' => $vehicleGroup,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create vehicle group. Please try again.',
            ], 500);
        }
    }

    public function edit($id)
    {
        $vehicleGroup = VehicleGroup::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $vehicleGroup,
        ]);
    }

    public function update(Request $request, $id)
    {
        $vehicleGroup = VehicleGroup::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:vehicle_groups,name,'.$id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $vehicleGroup->update([
                'name' => $request->name,
            ]);

            $this->auditLookup('updated', $vehicleGroup);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle group updated successfully!',
                'data' => $vehicleGroup,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle group. Please try again.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        $vehicleGroup = VehicleGroup::findOrFail($id);

        if ($vehicleGroup->vehicles()->withoutGlobalScope(CompanyScope::class)->withTrashed()->exists() || $vehicleGroup->trailers()->withoutGlobalScope(CompanyScope::class)->withTrashed()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete vehicle group because it is assigned to vehicles or trailers.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $vehicleGroup->delete();
            $this->auditLookup('deleted', $vehicleGroup);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle group deleted successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vehicle group. Please try again.',
            ], 500);
        }
    }
}
