<?php

namespace App\Http\Controllers;

use App\DataTables\Lookups\LookupTable;
use App\Http\Controllers\Concerns\AuditsLookups;
use App\Models\FuelType;
use App\Tenancy\CompanyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FuelTypeController extends Controller
{
    use AuditsLookups;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return (new LookupTable(FuelType::query()))->json();
        }

        return view('platform.lookups.fuel-types.index');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:fuel_types,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $fuelType = FuelType::create([
                'name' => $request->name,
            ]);

            $this->auditLookup('created', $fuelType);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Fuel type created successfully!',
                'data' => $fuelType,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create fuel type. Please try again.',
            ], 500);
        }
    }

    public function edit($id)
    {
        $fuelType = FuelType::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $fuelType,
        ]);
    }

    public function update(Request $request, $id)
    {
        $fuelType = FuelType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:fuel_types,name,'.$id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $fuelType->update([
                'name' => $request->name,
            ]);

            $this->auditLookup('updated', $fuelType);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Fuel type updated successfully!',
                'data' => $fuelType,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update fuel type. Please try again.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        $fuelType = FuelType::findOrFail($id);

        if ($fuelType->vehicles()->withoutGlobalScope(CompanyScope::class)->withTrashed()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete fuel type because it is assigned to vehicles.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $fuelType->delete();
            $this->auditLookup('deleted', $fuelType);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Fuel type deleted successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete fuel type. Please try again.',
            ], 500);
        }
    }
}
