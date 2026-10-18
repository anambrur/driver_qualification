<?php

namespace App\Http\Controllers;

use App\DataTables\Lookups\LookupTable;
use App\Http\Controllers\Concerns\AuditsLookups;
use App\Models\EquipmentType;
use App\Tenancy\CompanyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EquipmentTypeController extends Controller
{
    use AuditsLookups;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return (new LookupTable(EquipmentType::query()))->json();
        }

        return view('platform.lookups.index', ['lookup' => [
            'title' => 'Equipment types',
            'singular' => 'equipment type',
            'route' => 'platform.lookups.equipment-types',
            'description' => 'The kinds of trailer or equipment companies can register.',
            'hint' => 'For example Dry van, Reefer or Flatbed.',
            'icon' => 'container',
        ]]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:equipment_types,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $equipmentType = EquipmentType::create([
                'name' => $request->name,
            ]);

            $this->auditLookup('created', $equipmentType);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Equipment type created successfully!',
                'data' => $equipmentType,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('EquipmentType store error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create equipment type. Please try again.',
            ], 500);
        }
    }

    public function edit($id)
    {
        $equipmentType = EquipmentType::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $equipmentType,
        ]);
    }

    public function update(Request $request, $id)
    {
        $equipmentType = EquipmentType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:equipment_types,name,'.$id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $equipmentType->update([
                'name' => $request->name,
            ]);

            $this->auditLookup('updated', $equipmentType);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Equipment type updated successfully!',
                'data' => $equipmentType,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('EquipmentType update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update equipment type. Please try again.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        $equipmentType = EquipmentType::findOrFail($id);

        if ($equipmentType->trailers()->withoutGlobalScope(CompanyScope::class)->withTrashed()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete equipment type because it is assigned to trailers.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $equipmentType->delete();
            $this->auditLookup('deleted', $equipmentType);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Equipment type deleted successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('EquipmentType delete error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete equipment type. Please try again.',
            ], 500);
        }
    }
}
