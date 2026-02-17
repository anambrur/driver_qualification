<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class MaintenanceCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = MaintenanceCategory::query();

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    return '
                    <div class="flex justify-center space-x-1">
                        <button onclick="editMaintenanceCategory(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </button>
                        <button onclick="deleteMaintenanceCategory(' . $row->id . ', \'' . addslashes($row->name) . '\')" 
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

        return view('admin.maintenance-category.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:maintenance_categories,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $maintenanceCategory = MaintenanceCategory::create([
                'name' => $request->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Maintenance category created successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create maintenance category. Please try again.'
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $maintenanceCategory = MaintenanceCategory::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $maintenanceCategory
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Maintenance category not found'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $maintenanceCategory = MaintenanceCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:maintenance_categories,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $maintenanceCategory->update([
                'name' => $request->name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Maintenance category updated successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update maintenance category. Please try again.'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $maintenanceCategory = MaintenanceCategory::findOrFail($id);

        DB::beginTransaction();

        try {
            // Check if category has related maintenance records
            if ($maintenanceCategory->maintenanceRecords()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category because it has associated maintenance records.'
                ], 400);
            }

            $maintenanceCategory->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Maintenance category deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete maintenance category. Please try again.'
            ], 500);
        }
    }
}
