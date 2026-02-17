<?php
// app/Http/Controllers/ServiceLogController.php

namespace App\Http\Controllers;

use App\Models\ServiceLog;
use App\Models\ServiceDocument;
use App\Models\Vehicle;
use App\Models\MaintenanceCategory;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class ServiceLogController extends Controller
{
    use CompanyFilterTrait;

    /**
     * Display a listing of service logs.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = ServiceLog::with(['vehicle', 'company', 'documents'])
                ->select(['service_logs.*']);

            // Apply company filter
            $query = $this->applyCompanyFilter($query);

            // Apply filters
            if ($request->filled('vehicle_id')) {
                $query->where('vehicle_id', $request->vehicle_id);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('service_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('service_date', '<=', $request->date_to);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('category_id')) {
                $query->whereJsonContains('maintenance_categories', (int)$request->category_id);
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('vehicle_info', function ($row) {
                    $vehicle = $row->vehicle;
                    if (!$vehicle) {
                        return '<span class="text-gray-400">Deleted Vehicle</span>';
                    }

                    $documentIcon = $row->documents->count() > 0 ?
                        '<i class="fas fa-paperclip ml-2 text-blue-500 text-xs" title="Has documents"></i>' : '';

                    return '
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-600">
                                <i class="fas fa-truck"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white flex items-center">
                                ' . $vehicle->unit_no . ' - ' . $vehicle->year . ' ' . $vehicle->make . ' ' . $vehicle->model . '
                                ' . $documentIcon . '
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                VIN: ' . $vehicle->vin . '
                            </div>
                        </div>
                    </div>';
                })
                ->addColumn('service_info', function ($row) {
                    $categories = $row->maintenanceCategories;
                    $categoriesHtml = '';

                    if ($categories->count() > 0) {
                        $categoriesHtml = '<div class="mt-1 flex flex-wrap gap-1">';
                        foreach ($categories->take(2) as $category) {
                            $categoriesHtml .= '<span class="px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded-full">' . $category->name . '</span>';
                        }
                        if ($categories->count() > 2) {
                            $categoriesHtml .= '<span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-800 rounded-full">+' . ($categories->count() - 2) . '</span>';
                        }
                        $categoriesHtml .= '</div>';
                    }

                    return '
                    <div class="text-sm">
                        <div><span class="font-medium">Date:</span> ' . $row->service_date->format('M d, Y') . '</div>
                        <div><span class="font-medium">Categories:</span> </div>
                        ' . $categoriesHtml . '
                    </div>';
                })
                ->addColumn('metrics', function ($row) {
                    $engineHours = $row->engine_hours_at_service ?
                        '<div><span class="font-medium">Engine Hrs:</span> ' . number_format($row->engine_hours_at_service) . '</div>' : '';

                    return '
                    <div class="text-sm">
                        <div><span class="font-medium">Odometer:</span> ' . number_format($row->odometer_at_service) . ' mi</div>
                        ' . $engineHours . '
                        <div><span class="font-medium">Current:</span> ' . number_format($row->current_odometer) . ' mi</div>
                    </div>';
                })
                ->addColumn('cost', function ($row) {
                    return '
                    <div class="text-sm">
                        <div class="font-medium text-gray-900 dark:text-white">' . $row->formatted_total_cost . '</div>
                    </div>';
                })
                ->addColumn('status', function ($row) {
                    $badgeClass = [
                        'completed' => 'bg-green-100 text-green-800',
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'cancelled' => 'bg-red-100 text-red-800'
                    ][$row->status] ?? 'bg-gray-100 text-gray-800';

                    $statusText = ucfirst($row->status);

                    return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' . $badgeClass . '">
                        ' . $statusText . '
                    </span>';
                })
                ->addColumn('action', function ($row) {
                    $viewBtn = '<button onclick="viewServiceLog(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors mr-1"
                                title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>';

                    $editBtn = '<button onclick="editServiceLog(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-indigo-600 bg-indigo-100 rounded-md hover:bg-indigo-200 transition-colors mr-1"
                                title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>';

                    $deleteBtn = '<button onclick="deleteServiceLog(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-red-600 bg-red-100 rounded-md hover:bg-red-200 transition-colors"
                                title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>';

                    return '<div class="flex justify-center space-x-1">' . $viewBtn . $editBtn . $deleteBtn . '</div>';
                })
                ->addColumn('created_at_formatted', function ($row) {
                    return $row->created_at->format('M d, Y');
                })
                ->rawColumns(['vehicle_info', 'service_info', 'metrics', 'cost', 'status', 'action'])
                ->make(true);
        }

        // Get dropdown data for filters
        $vehicles = Vehicle::orderBy('unit_no')->get();
        $maintenanceCategories = MaintenanceCategory::orderBy('name')->get();
        $companies = $this->getCompaniesForUser();

        return view('admin.service-log.index', compact('vehicles', 'maintenanceCategories', 'companies'));
    }

    /**
     * Get service log details for editing.
     */
    public function edit($id)
    {
        $serviceLog = ServiceLog::with(['vehicle', 'documents'])->findOrFail($id);

        // Check if user has access to this service log
        $this->authorizeCompanyAccess($serviceLog, 'You do not have permission to edit this service log.');

        return response()->json([
            'success' => true,
            'data' => $serviceLog
        ]);
    }

    /**
     * Get service log details for viewing.
     */
    public function show($id)
    {
        $serviceLog = ServiceLog::with(['vehicle', 'documents', 'maintenanceCategories', 'company'])
            ->findOrFail($id);

        // Check if user has access to this service log
        $this->authorizeCompanyAccess($serviceLog, 'You do not have permission to view this service log.');

        return response()->json([
            'success' => true,
            'data' => $serviceLog,
            'vehicle_info' => $serviceLog->vehicle ? [
                'unit_no' => $serviceLog->vehicle->unit_no,
                'full_name' => $serviceLog->vehicle->full_name,
                'current_odometer' => $serviceLog->vehicle->odometer,
            ] : null
        ]);
    }

    /**
     * Store a newly created service log.
     */
    public function store(Request $request)
    {
        // Add company validation based on user role
        $companyId = $this->getAllUserCompanyId();

        if (!Auth::user()->hasRole('super-admin')) {
            $request->merge(['company_id' => $companyId]);
        }


        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'required|exists:vehicles,id',
            'service_date' => 'required|date',
            'maintenance_categories' => 'required|array|min:1',
            'maintenance_categories.*' => 'exists:maintenance_categories,id',
            'maintenance_notes' => 'nullable|string',
            'odometer_at_service' => 'required|integer|min:0',
            'current_odometer' => 'required|integer|min:0|gte:odometer_at_service',
            'engine_hours_at_service' => 'nullable|integer|min:0',
            'current_engine_hours' => 'nullable|integer|min:0|gte:engine_hours_at_service',
            'total_cost' => 'required|numeric|min:0',
            'status' => 'required|in:completed,pending,cancelled',
            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240'
        ], [
            'current_odometer.gte' => 'Current odometer must be greater than or equal to odometer at service',
            'current_engine_hours.gte' => 'Current engine hours must be greater than or equal to engine hours at service',
            'maintenance_categories.required' => 'Please select at least one maintenance category',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Create service log
            $serviceLog = ServiceLog::create([
                'company_id' => $companyId,
                'vehicle_id' => $request->vehicle_id,
                'service_date' => $request->service_date,
                'maintenance_notes' => $request->maintenance_notes,
                'odometer_at_service' => $request->odometer_at_service,
                'current_odometer' => $request->current_odometer,
                'engine_hours_at_service' => $request->engine_hours_at_service,
                'current_engine_hours' => $request->current_engine_hours,
                'total_cost' => $request->total_cost,
                'status' => $request->status
            ]);

            // Attach maintenance categories (many-to-many)
            $serviceLog->maintenanceCategories()->attach($request->maintenance_categories);

            // Handle document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $filePath = $file->storeAs('service-documents/' . $serviceLog->id, $fileName, 'public');

                    ServiceDocument::create([
                        'service_log_id' => $serviceLog->id,
                        'original_name' => $originalName,
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize()
                    ]);
                }
            }

            // Update vehicle metrics
            $serviceLog->updateVehicleMetrics();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Service log created successfully!',
                'data' => $serviceLog->load(['documents', 'maintenanceCategories'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service log store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create service log. Please try again.'
            ], 500);
        }
    }

    /**
     * Update the specified service log.
     */
    public function update(Request $request, $id)
    {
        $serviceLog = ServiceLog::findOrFail($id);

        // Check if user has access to this service log
        $this->authorizeCompanyAccess($serviceLog, 'You do not have permission to update this service log.');

        // Ensure user can't change company if not super-admin
        if (!Auth::user()->hasRole('super-admin')) {
            $request->merge(['company_id' => $serviceLog->company_id]);
        }

        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'required|exists:vehicles,id',
            'service_date' => 'required|date',
            'maintenance_categories' => 'required|array|min:1',
            'maintenance_categories.*' => 'exists:maintenance_categories,id',
            'maintenance_notes' => 'nullable|string',
            'odometer_at_service' => 'required|integer|min:0',
            'current_odometer' => 'required|integer|min:0|gte:odometer_at_service',
            'engine_hours_at_service' => 'nullable|integer|min:0',
            'current_engine_hours' => 'nullable|integer|min:0|gte:engine_hours_at_service',
            'total_cost' => 'required|numeric|min:0',
            'status' => 'required|in:completed,pending,cancelled',
            'new_documents' => 'nullable|array',
            'new_documents.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'delete_documents' => 'nullable|array',
            'delete_documents.*' => 'exists:service_documents,id'
        ], [
            'current_odometer.gte' => 'Current odometer must be greater than or equal to odometer at service',
            'current_engine_hours.gte' => 'Current engine hours must be greater than or equal to engine hours at service',
            'maintenance_categories.required' => 'Please select at least one maintenance category',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Delete requested documents
            if ($request->has('delete_documents')) {
                $documentsToDelete = ServiceDocument::whereIn('id', $request->delete_documents)
                    ->where('service_log_id', $serviceLog->id)
                    ->get();

                foreach ($documentsToDelete as $doc) {
                    Storage::disk('public')->delete($doc->file_path);
                    $doc->delete();
                }
            }

            // Upload new documents
            if ($request->hasFile('new_documents')) {
                foreach ($request->file('new_documents') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $filePath = $file->storeAs('service-documents/' . $serviceLog->id, $fileName, 'public');

                    ServiceDocument::create([
                        'service_log_id' => $serviceLog->id,
                        'original_name' => $originalName,
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize()
                    ]);
                }
            }

            // Update service log
            $serviceLog->update([
                'vehicle_id' => $request->vehicle_id,
                'service_date' => $request->service_date,
                'maintenance_notes' => $request->maintenance_notes,
                'odometer_at_service' => $request->odometer_at_service,
                'current_odometer' => $request->current_odometer,
                'engine_hours_at_service' => $request->engine_hours_at_service,
                'current_engine_hours' => $request->current_engine_hours,
                'total_cost' => $request->total_cost,
                'status' => $request->status
            ]);

            // Sync maintenance categories (many-to-many)
            $serviceLog->maintenanceCategories()->sync($request->maintenance_categories);

            // Update vehicle metrics
            $serviceLog->updateVehicleMetrics();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Service log updated successfully!',
                'data' => $serviceLog->load(['documents', 'maintenanceCategories'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service log update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update service log. Please try again.'
            ], 500);
        }
    }




    /**
     * Remove the specified service log.
     */
    public function destroy($id)
    {
        $serviceLog = ServiceLog::findOrFail($id);

        // Check if user has access to this service log
        $this->authorizeCompanyAccess($serviceLog, 'You do not have permission to delete this service log.');

        DB::beginTransaction();

        try {
            // Delete associated documents from storage
            foreach ($serviceLog->documents as $doc) {
                Storage::disk('public')->delete($doc->file_path);
            }

            $serviceLog->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Service log deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service log delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete service log. Please try again.'
            ], 500);
        }
    }

    /**
     * Download service document.
     */
    public function downloadDocument($id)
    {
        $document = ServiceDocument::findOrFail($id);
        $serviceLog = $document->serviceLog;

        // Check if user has access to this service log
        $this->authorizeCompanyAccess($serviceLog, 'You do not have permission to download this document.');

        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found.'
            ], 404);
        }

        return response()->download(Storage::disk('public')->path($document->file_path), $document->original_name);
    }

    /**
     * Delete a specific document.
     */
    public function deleteDocument($id)
    {
        $document = ServiceDocument::findOrFail($id);
        $serviceLog = $document->serviceLog;

        // Check if user has access to this service log
        $this->authorizeCompanyAccess($serviceLog, 'You do not have permission to delete this document.');

        DB::beginTransaction();

        try {
            Storage::disk('public')->delete($document->file_path);
            $document->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Document delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document.'
            ], 500);
        }
    }

    /**
     * Get dropdown data for forms.
     */
    public function getDropdownData()
    {
        $vehiclesQuery = Vehicle::orderBy('unit_no');
        $vehiclesQuery = $this->applyCompanyFilter($vehiclesQuery);
        $vehicles = $vehiclesQuery->get(['id', 'unit_no', 'make', 'model', 'year', 'odometer']);

        $maintenanceCategories = MaintenanceCategory::orderBy('name')->get(['id', 'name']);
        $companies = $this->getCompaniesForUser();

        return response()->json([
            'success' => true,
            'vehicles' => $vehicles,
            'maintenanceCategories' => $maintenanceCategories,
            'companies' => $companies,
            'statusOptions' => [
                'completed' => 'Completed',
                'pending' => 'Pending',
                'cancelled' => 'Cancelled'
            ]
        ]);
    }

    /**
     * Get vehicle details including current odometer.
     */
    public function getVehicleDetails($id)
    {
        try {
            $vehicle = Vehicle::findOrFail($id);

            // Check if user has access to this vehicle
            $this->authorizeCompanyAccess($vehicle, 'You do not have permission to view this vehicle.');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $vehicle->id,
                    'unit_no' => $vehicle->unit_no,
                    'full_name' => $vehicle->full_name,
                    'current_odometer' => $vehicle->odometer,
                    'vin' => $vehicle->vin,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found'
            ], 404);
        }
    }
}
