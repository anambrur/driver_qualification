<?php

namespace App\Http\Controllers;

use App\Models\EquipmentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class EquipmentTypeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = EquipmentType::query();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    return '
                    <div class="flex justify-center space-x-1">
                        <button onclick="editEquipmentType(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </button>
                        <button onclick="deleteEquipmentType(' . $row->id . ', \'' . addslashes($row->name) . '\')" 
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
                ->addColumn('equipment_count', function ($row) {
                    // If you have an equipment relationship, uncomment this:
                    // return $row->equipment->count() . ' equipment';
                    return '<span class="text-gray-400">-</span>';
                })
                ->rawColumns(['action', 'equipment_count'])
                ->make(true);
        }

        return view('admin.equipment-type.index');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:equipment_types,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $equipmentType = EquipmentType::create([
                'name' => $request->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Equipment type created successfully!',
                'data' => $equipmentType
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('EquipmentType store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create equipment type. Please try again.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $equipmentType = EquipmentType::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $equipmentType
        ]);
    }

    public function update(Request $request, $id)
    {
        $equipmentType = EquipmentType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:equipment_types,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $equipmentType->update([
                'name' => $request->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Equipment type updated successfully!',
                'data' => $equipmentType
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('EquipmentType update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update equipment type. Please try again.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $equipmentType = EquipmentType::findOrFail($id);

        DB::beginTransaction();

        try {
            // Check if equipment type has related equipment
            // if ($equipmentType->equipment()->count() > 0) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Cannot delete equipment type because it has associated equipment.'
            //     ], 400);
            // }

            $equipmentType->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Equipment type deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('EquipmentType delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete equipment type. Please try again.'
            ], 500);
        }
    }
}
