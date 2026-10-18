<?php

// app/Http/Controllers/MaintenanceScheduleController.php

namespace App\Http\Controllers;

use App\DataTables\Maintenance\MaintenanceScheduleTable;
use App\Models\MaintenanceCategory;
use App\Models\MaintenanceSchedule;
use App\Models\ServiceLog;
use App\Models\Vehicle;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class MaintenanceScheduleController extends Controller
{
    use CompanyFilterTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return (new MaintenanceScheduleTable($request))->json();
        }

        // Get dropdown data for filters
        $vehicles = Vehicle::orderBy('unit_no')->get(['id', 'unit_no', 'year', 'make', 'model']);
        $maintenanceCategories = MaintenanceCategory::orderBy('name')->get(['id', 'name']);

        return view('admin.maintenance-schedule.index', compact('vehicles', 'maintenanceCategories'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id' => $this->vehicleRules($request),
            'maintenance_category_id' => 'required|exists:maintenance_categories,id',
            'title' => 'nullable|string|max:255',
            'schedule_type' => 'required|in:date,mileage,engine_hours',
            'interval_days' => 'required_if:schedule_type,date|nullable|integer|min:1|max:9999',
            'interval_miles' => 'required_if:schedule_type,mileage|nullable|integer|min:1|max:999999',
            'interval_hours' => 'required_if:schedule_type,engine_hours|nullable|integer|min:1|max:999999',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,paused,completed',
        ], $this->validationMessages() + [
            'interval_days.required_if' => 'Please enter the number of days between maintenance',
            'interval_miles.required_if' => 'Please enter the mileage interval',
            'interval_hours.required_if' => 'Please enter the engine hours interval',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Always the tenant's own company; the platform files an all-vehicles schedule for a
        // company inside a read_write support session (PO-07, D11)
        $companyId = $this->tenantCompanyId();

        DB::beginTransaction();

        try {
            $schedule = MaintenanceSchedule::create([
                'company_id' => $companyId,
                'vehicle_id' => $request->vehicle_id,
                'maintenance_category_id' => $request->maintenance_category_id,
                'title' => $request->title,
                'schedule_type' => $request->schedule_type,
                'interval_days' => $request->schedule_type === 'date' ? $request->interval_days : null,
                'interval_miles' => $request->schedule_type === 'mileage' ? $request->interval_miles : null,
                'interval_hours' => $request->schedule_type === 'engine_hours' ? $request->interval_hours : null,
                'description' => $request->description,
                'notes' => $request->notes,
                'status' => $request->status,
            ]);

            // Calculate next due date based on current vehicle state
            $schedule->calculateNextDue()->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Maintenance schedule created successfully!',
                'data' => $schedule->load(['vehicle', 'maintenanceCategory']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule store error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create schedule. Please try again.',
            ], 500);
        }
    }

    public function edit($id)
    {
        $schedule = MaintenanceSchedule::with(['vehicle', 'maintenanceCategory'])->findOrFail($id);

        Gate::authorize('update', $schedule);

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }

    public function show($id)
    {
        $schedule = MaintenanceSchedule::with(['vehicle', 'maintenanceCategory', 'company'])
            ->findOrFail($id);

        Gate::authorize('view', $schedule);

        // The details modal shows these computed values
        $schedule->append(['schedule_type_label', 'interval_text', 'next_due_text', 'status_badge']);
        $schedule->vehicle?->append('full_name');

        return response()->json([
            'success' => true,
            'data' => array_merge($schedule->toArray(), ['is_due' => $schedule->isDue()]),
        ]);
    }

    public function update(Request $request, $id)
    {
        $schedule = MaintenanceSchedule::findOrFail($id);

        Gate::authorize('update', $schedule);

        $validator = Validator::make($request->all(), [
            'vehicle_id' => $this->vehicleRules($request),
            'maintenance_category_id' => 'required|exists:maintenance_categories,id',
            'title' => 'nullable|string|max:255',
            'schedule_type' => 'required|in:date,mileage,engine_hours',
            'interval_days' => 'required_if:schedule_type,date|nullable|integer|min:1|max:9999',
            'interval_miles' => 'required_if:schedule_type,mileage|nullable|integer|min:1|max:999999',
            'interval_hours' => 'required_if:schedule_type,engine_hours|nullable|integer|min:1|max:999999',
            'last_service_date' => 'nullable|date',
            'last_service_mileage' => 'nullable|integer|min:0',
            'last_service_hours' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,paused,completed',
        ], $this->validationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Only the validated fields; next_due_* is never taken from the request. A schedule
        // never changes company.
        $data = $validator->validated();
        $data['interval_days'] = $request->schedule_type === 'date' ? $request->interval_days : null;
        $data['interval_miles'] = $request->schedule_type === 'mileage' ? $request->interval_miles : null;
        $data['interval_hours'] = $request->schedule_type === 'engine_hours' ? $request->interval_hours : null;

        DB::beginTransaction();

        try {
            $schedule->fill($data);

            // Recalculate next due only when what it depends on changed, so editing the
            // notes doesn't move the due date
            if ($schedule->isDirty(['vehicle_id', 'schedule_type', 'interval_days', 'interval_miles', 'interval_hours', 'last_service_date', 'last_service_mileage', 'last_service_hours'])) {
                $schedule->calculateNextDue();
            }

            $schedule->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Maintenance schedule updated successfully!',
                'data' => $schedule->load(['vehicle', 'maintenanceCategory']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update schedule. Please try again.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        $schedule = MaintenanceSchedule::findOrFail($id);

        Gate::authorize('delete', $schedule);

        DB::beginTransaction();

        try {
            $schedule->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Maintenance schedule deleted successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule delete error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete schedule. Please try again.',
            ], 500);
        }
    }

    public function getDropdownData()
    {
        $vehiclesQuery = Vehicle::orderBy('unit_no');
        $vehicles = $vehiclesQuery->get(['id', 'unit_no', 'make', 'model', 'year']);

        $maintenanceCategories = MaintenanceCategory::orderBy('name')->get(['id', 'name']);
        $companies = $this->getCompaniesForUser();

        return response()->json([
            'success' => true,
            'vehicles' => $vehicles,
            'maintenanceCategories' => $maintenanceCategories,
            'companies' => $companies,
            'scheduleTypes' => [
                'date' => 'By Date',
                'mileage' => 'By Mileage',
                'engine_hours' => 'By Engine Hours',
            ],
            'statusOptions' => [
                'active' => 'Active',
                'paused' => 'Paused',
                'completed' => 'Completed',
            ],
        ]);
    }

    public function getVehicleDetails($id)
    {
        try {
            $vehicle = Vehicle::findOrFail($id);

            Gate::authorize('view', $vehicle);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $vehicle->id,
                    'unit_no' => $vehicle->unit_no,
                    'full_name' => $vehicle->full_name,
                    'current_odometer' => $vehicle->odometer,
                    'engine_hours' => ServiceLog::currentEngineHoursFor($vehicle->id),
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

    public function markAsCompleted($id)
    {
        $schedule = MaintenanceSchedule::findOrFail($id);

        Gate::authorize('update', $schedule);

        DB::beginTransaction();

        try {
            // Recording a service keeps a recurring schedule running: it stays in its status and
            // only its last / next due values move forward. "completed" means retired, set by
            // editing the schedule (PO-04).
            switch ($schedule->schedule_type) {
                case 'date':
                    $schedule->last_service_date = now();
                    break;
                case 'mileage':
                    if ($schedule->vehicle) {
                        $schedule->last_service_mileage = $schedule->vehicle->odometer;
                    }
                    break;
                case 'engine_hours':
                    if ($schedule->vehicle) {
                        $schedule->last_service_hours = $schedule->currentEngineHours();
                    }
                    break;
            }

            $schedule->calculateNextDue()->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Schedule marked as completed!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule complete error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark schedule as completed.',
            ], 500);
        }
    }

    /**
     * vehicle_id is optional for date schedules ("All vehicles"), but mileage and engine-hour
     * schedules need a vehicle to read the odometer / engine hours from (PO-06).
     *
     * @return array<int, mixed>
     */
    private function vehicleRules(Request $request): array
    {
        return [
            Rule::requiredIf(in_array($request->input('schedule_type'), ['mileage', 'engine_hours'], true)),
            'nullable',
            $this->vehicleRule(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(): array
    {
        return [
            'vehicle_id.required' => 'Choose a vehicle: mileage and engine-hour schedules are tracked per vehicle.',
        ];
    }

    /**
     * A vehicle the user may attach records to: not deleted, and in the tenant's own company.
     */
    private function vehicleRule(): Exists
    {
        return Rule::exists('vehicles', 'id')->whereNull('deleted_at')->where('company_id', $this->getUserCompanyId() ?? -1);
    }
}
