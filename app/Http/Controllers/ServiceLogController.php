<?php

// app/Http/Controllers/ServiceLogController.php

namespace App\Http\Controllers;

use App\DataTables\Maintenance\ServiceLogTable;
use App\Exceptions\LimitReached;
use App\Models\MaintenanceCategory;
use App\Models\ServiceDocument;
use App\Models\ServiceLog;
use App\Models\Vehicle;
use App\Services\Files\StoredFileService;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class ServiceLogController extends Controller
{
    use CompanyFilterTrait;

    /**
     * Display a listing of service logs.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            return (new ServiceLogTable($request))->json();
        }

        // Get dropdown data for filters
        $vehicles = Vehicle::orderBy('unit_no')->get(['id', 'unit_no', 'year', 'make', 'model']);
        $maintenanceCategories = MaintenanceCategory::orderBy('name')->get(['id', 'name']);

        return view('admin.service-log.index', compact('vehicles', 'maintenanceCategories'));
    }

    /**
     * Get service log details for editing.
     */
    public function edit($id)
    {
        $serviceLog = ServiceLog::with(['vehicle', 'documents.file', 'maintenanceCategories'])->findOrFail($id);

        // Check if user has access to this service log
        Gate::authorize('update', $serviceLog);

        return response()->json([
            'success' => true,
            'data' => $serviceLog,
        ]);
    }

    /**
     * Get service log details for viewing.
     */
    public function show($id)
    {
        $serviceLog = ServiceLog::with(['vehicle', 'documents.file', 'maintenanceCategories', 'company'])
            ->findOrFail($id);

        // Check if user has access to this service log
        Gate::authorize('view', $serviceLog);

        return response()->json([
            'success' => true,
            'data' => $serviceLog,
            'vehicle_info' => $serviceLog->vehicle ? [
                'unit_no' => $serviceLog->vehicle->unit_no,
                'full_name' => $serviceLog->vehicle->full_name,
                'current_odometer' => $serviceLog->vehicle->odometer,
            ] : null,
        ]);
    }

    /**
     * Store a newly created service log.
     */
    public function store(Request $request, StoredFileService $files)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id' => ['required', $this->vehicleRule()],
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
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
        ], [
            'current_odometer.gte' => 'Current odometer must be greater than or equal to odometer at service',
            'current_engine_hours.gte' => 'Current engine hours must be greater than or equal to engine hours at service',
            'maintenance_categories.required' => 'Please select at least one maintenance category',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Always the tenant's own company
        $companyId = $this->tenantCompanyId();

        try {
            $serviceLog = $files->transaction(function () use ($request, $companyId, $files) {
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
                    'status' => $request->status,
                ]);

                // Attach maintenance categories (many-to-many)
                $serviceLog->maintenanceCategories()->attach($request->maintenance_categories);

                foreach ($request->file('documents', []) as $file) {
                    $this->storeDocument($files, $serviceLog, $file);
                }

                // Update vehicle metrics
                $serviceLog->updateVehicleMetrics();

                return $serviceLog;
            });

            return response()->json([
                'success' => true,
                'message' => 'Service log created successfully!',
                'data' => $serviceLog->load(['documents.file', 'maintenanceCategories']),
            ]);
        } catch (LimitReached $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Service log store error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create service log. Please try again.',
            ], 500);
        }
    }

    /**
     * Update the specified service log.
     */
    public function update(Request $request, $id, StoredFileService $files)
    {
        $serviceLog = ServiceLog::findOrFail($id);

        // Check if user has access to this service log
        Gate::authorize('update', $serviceLog);

        $validator = Validator::make($request->all(), [
            'vehicle_id' => ['required', $this->vehicleRule()],
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
            'delete_documents.*' => 'exists:service_documents,id',
        ], [
            'current_odometer.gte' => 'Current odometer must be greater than or equal to odometer at service',
            'current_engine_hours.gte' => 'Current engine hours must be greater than or equal to engine hours at service',
            'maintenance_categories.required' => 'Please select at least one maintenance category',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $files->transaction(function () use ($request, $serviceLog, $files) {
                // Delete requested documents; their files go after commit if nothing else uses them
                if ($request->has('delete_documents')) {
                    $documentsToDelete = ServiceDocument::whereIn('id', $request->delete_documents)
                        ->where('service_log_id', $serviceLog->id)
                        ->get();

                    foreach ($documentsToDelete as $doc) {
                        $doc->delete();
                        $files->purgeWhenUnreferenced($doc->file_id);
                    }
                }

                foreach ($request->file('new_documents', []) as $file) {
                    $this->storeDocument($files, $serviceLog, $file);
                }

                // The values the vehicle may have taken from this log, to correct it downwards (PO-22)
                $previousOdometer = $serviceLog->current_odometer;
                $previousEngineHours = $serviceLog->current_engine_hours;

                // Update service log; it never changes company
                $serviceLog->update([
                    'vehicle_id' => $request->vehicle_id,
                    'service_date' => $request->service_date,
                    'maintenance_notes' => $request->maintenance_notes,
                    'odometer_at_service' => $request->odometer_at_service,
                    'current_odometer' => $request->current_odometer,
                    'engine_hours_at_service' => $request->engine_hours_at_service,
                    'current_engine_hours' => $request->current_engine_hours,
                    'total_cost' => $request->total_cost,
                    'status' => $request->status,
                ]);

                // Sync maintenance categories (many-to-many)
                $serviceLog->maintenanceCategories()->sync($request->maintenance_categories);

                // Update vehicle metrics
                $serviceLog->updateVehicleMetrics($previousOdometer, $previousEngineHours);
            });

            return response()->json([
                'success' => true,
                'message' => 'Service log updated successfully!',
                'data' => $serviceLog->load(['documents.file', 'maintenanceCategories']),
            ]);
        } catch (LimitReached $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Service log update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update service log. Please try again.',
            ], 500);
        }
    }

    /**
     * Remove the specified service log.
     */
    public function destroy($id, StoredFileService $files)
    {
        $serviceLog = ServiceLog::findOrFail($id);

        // Check if user has access to this service log
        Gate::authorize('delete', $serviceLog);

        DB::beginTransaction();

        try {
            // The log's documents go with it: rows now, files once committed (PO-22)
            $fileIds = $serviceLog->documents()->pluck('file_id')->all();
            $serviceLog->documents()->delete();
            $files->purgeWhenUnreferenced(...$fileIds);

            $serviceLog->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Service log deleted successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service log delete error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete service log. Please try again.',
            ], 500);
        }
    }

    /**
     * Download service document.
     */
    public function downloadDocument($id, StoredFileService $files)
    {
        $document = $this->findAuthorizedDocument($id, 'view');

        return $files->response($document->file, $document->file?->original_name);
    }

    /**
     * Delete a specific document.
     */
    public function deleteDocument($id, StoredFileService $files)
    {
        $document = $this->findAuthorizedDocument($id, 'update');

        DB::beginTransaction();

        try {
            $document->delete();
            $files->purgeWhenUnreferenced($document->file_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Document delete error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document.',
            ], 500);
        }
    }

    /**
     * Get dropdown data for forms.
     */
    public function getDropdownData()
    {
        $vehiclesQuery = Vehicle::orderBy('unit_no');
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
                'cancelled' => 'Cancelled',
            ],
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
            Gate::authorize('view', $vehicle);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $vehicle->id,
                    'unit_no' => $vehicle->unit_no,
                    'full_name' => $vehicle->full_name,
                    'current_odometer' => $vehicle->odometer,
                    'vin' => $vehicle->vin,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found',
            ], 404);
        }
    }

    private function storeDocument(StoredFileService $files, ServiceLog $serviceLog, UploadedFile $file): void
    {
        ServiceDocument::create([
            'service_log_id' => $serviceLog->id,
            'file_id' => $files->store($file, 'service-documents')->id,
        ]);
    }

    /**
     * A document whose service log the user may access: 404 when the document or its (soft-deleted)
     * log is gone, or another company's.
     */
    private function findAuthorizedDocument($id, string $ability): ServiceDocument
    {
        $document = ServiceDocument::with(['serviceLog', 'file'])->findOrFail($id);

        if (! $document->serviceLog) {
            abort(404);
        }

        Gate::authorize($ability, $document->serviceLog);

        return $document;
    }

    /**
     * A vehicle the user may attach records to: not deleted, and in the tenant's own company.
     */
    private function vehicleRule(): Exists
    {
        return Rule::exists('vehicles', 'id')->whereNull('deleted_at')->where('company_id', $this->getUserCompanyId() ?? -1);
    }
}
