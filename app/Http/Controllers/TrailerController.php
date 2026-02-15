<?php

namespace App\Http\Controllers;

use App\Models\EquipmentType;
use App\Models\Trailer;
use App\Models\VehicleGroup;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class TrailerController extends Controller
{
    use CompanyFilterTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Trailer::with(['equipmentType', 'vehicleGroup', 'company'])
                ->select(['trailers.*']);

            // Apply company filter
            $query = $this->applyCompanyFilter($query);

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('company_name', function ($row) {
                    return $row->company ? $row->company->company_name : 'N/A';
                })
                ->addColumn('trailer_info', function ($row) {
                    $notesIcon = $row->notes ? '<i class="fas fa-sticky-note ml-2 text-blue-500 text-xs" title="Has notes"></i>' : '';
                    return '
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-600">
                                <i class="fas fa-trailer"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white flex items-center">
                                ' . $row->unit_no . ' - ' . $row->year . ' ' . $row->make . ' ' . $row->model . '
                                ' . $notesIcon . '
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                VIN: ' . $row->vin . '
                            </div>
                        </div>
                    </div>';
                })
                ->addColumn('equipment_group', function ($row) {
                    $equipment = $row->equipmentType ? $row->equipmentType->name : '<span class="text-gray-400">N/A</span>';
                    $group = $row->vehicleGroup ? $row->vehicleGroup->name : '<span class="text-gray-400">N/A</span>';
                    return '
                    <div class="text-sm">
                        <div><span class="font-medium">Equipment:</span> ' . $equipment . '</div>
                        <div><span class="font-medium">Group:</span> ' . $group . '</div>
                    </div>';
                })
                ->addColumn('gvw', function ($row) {
                    return $row->gvw ? number_format($row->gvw) . ' lbs' : 'N/A';
                })
                ->addColumn('owned_by', function ($row) {
                    return $row->owned_by ?? 'N/A';
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
                    $editBtn = '<button onclick="editTrailer(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors mr-2">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>';

                    if ($row->deleted_at) {
                        $deleteBtn = '<button onclick="restoreTrailer(' . $row->id . ', \'' . addslashes($row->unit_no) . '\')" 
                                    class="inline-flex items-center px-3 py-1 text-sm text-green-600 bg-green-100 rounded-md hover:bg-green-200 transition-colors">
                                    <i class="fas fa-trash-restore mr-1"></i> Restore
                                </button>';
                    } else {
                        $deleteBtn = '<button onclick="deleteTrailer(' . $row->id . ', \'' . addslashes($row->unit_no) . '\')" 
                                    class="inline-flex items-center px-3 py-1 text-sm text-red-600 bg-red-100 rounded-md hover:bg-red-200 transition-colors">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </button>';
                    }

                    return '<div class="flex justify-center space-x-1">' . $editBtn . $deleteBtn . '</div>';
                })
                ->addColumn('created_at_formatted', function ($row) {
                    return $row->created_at->format('M d, Y');
                })
                ->rawColumns(['trailer_info', 'equipment_group', 'status', 'action'])
                ->make(true);
        }

        // Get dropdown data for filters
        $equipmentTypes = EquipmentType::orderBy('name')->get();
        $vehicleGroups = VehicleGroup::orderBy('name')->get();
        $companies = $this->getCompaniesForUser();

        return view('admin.trailer.index', compact('equipmentTypes', 'vehicleGroups', 'companies'));
    }

    public function store(Request $request)
    {
        // Add company validation based on user role
        $companyId = $this->getUserCompanyId();

        if (!Auth::user()->hasRole('super-admin')) {
            // For non-super-admin, force company_id to their company
            $request->merge(['company_id' => $companyId]);
        }

        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'unit_no' => 'required|string|max:50|unique:trailers,unit_no',
            'vin' => 'required|string|size:17|unique:trailers,vin',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'equipment_types_id' => 'required|exists:equipment_types,id',
            'owned_by' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'title_no' => 'nullable|string|max:100',
            'tire_size' => 'nullable|string|max:50',
            'gvw' => 'nullable|integer|min:0',
            'vehicle_group_id' => 'nullable|exists:vehicle_groups,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $trailer = Trailer::create($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Trailer created successfully!',
                'data' => $trailer
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Trailer store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create trailer. Please try again.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $trailer = Trailer::with(['equipmentType', 'vehicleGroup', 'company'])->findOrFail($id);

        // Check if user has access to this trailer
        $this->authorizeCompanyAccess($trailer, 'You do not have permission to edit this trailer.');

        return response()->json([
            'success' => true,
            'data' => $trailer
        ]);
    }

    public function update(Request $request, $id)
    {
        $trailer = Trailer::findOrFail($id);

        // Check if user has access to this trailer
        $this->authorizeCompanyAccess($trailer, 'You do not have permission to update this trailer.');

        // Ensure user can't change company if not super-admin
        if (!Auth::user()->hasRole('super-admin')) {
            $request->merge(['company_id' => $trailer->company_id]);
        }

        $validator = Validator::make($request->all(), [
            'unit_no' => 'required|string|max:50|unique:trailers,unit_no,' . $id,
            'vin' => 'required|string|size:17|unique:trailers,vin,' . $id,
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'equipment_types_id' => 'required|exists:equipment_types,id',
            'owned_by' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'title_no' => 'nullable|string|max:100',
            'tire_size' => 'nullable|string|max:50',
            'gvw' => 'nullable|integer|min:0',
            'vehicle_group_id' => 'nullable|exists:vehicle_groups,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $trailer->update($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Trailer updated successfully!',
                'data' => $trailer
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Trailer update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update trailer. Please try again.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $trailer = Trailer::findOrFail($id);

        // Check if user has access to this trailer
        $this->authorizeCompanyAccess($trailer, 'You do not have permission to delete this trailer.');

        DB::beginTransaction();

        try {
            $trailer->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Trailer deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Trailer delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete trailer. Please try again.'
            ], 500);
        }
    }

    public function restore($id)
    {
        $trailer = Trailer::withTrashed()->findOrFail($id);

        // Check if user has access to this trailer
        $this->authorizeCompanyAccess($trailer, 'You do not have permission to restore this trailer.');

        DB::beginTransaction();

        try {
            $trailer->restore();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Trailer restored successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Trailer restore error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore trailer. Please try again.'
            ], 500);
        }
    }

    public function getDropdownData()
    {
        $equipmentTypes = EquipmentType::orderBy('name')->get(['id', 'name']);
        $vehicleGroups = VehicleGroup::orderBy('name')->get(['id', 'name']);
        $companies = $this->getCompaniesForUser();

        return response()->json([
            'success' => true,
            'equipmentTypes' => $equipmentTypes,
            'vehicleGroups' => $vehicleGroups,
            'companies' => $companies,
            'ownedByOptions' => ['company' => 'Company Owned', 'lease' => 'Leased', 'rental' => 'Rental']
        ]);
    }

    public function getTrailerDetails($id)
    {
        try {
            $trailer = Trailer::findOrFail($id);

            // Check if user has access to this trailer
            $this->authorizeCompanyAccess($trailer, 'You do not have permission to view this trailer.');

            return response()->json([
                'success' => true,
                'data' => [
                    'unit_no' => $trailer->unit_no,
                    'year' => $trailer->year,
                    'make' => $trailer->make,
                    'model' => $trailer->model,
                    'vin' => $trailer->vin,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Trailer not found'
            ], 404);
        }
    }
}
