<?php
// app/Http/Controllers/MaintenanceScheduleController.php

namespace App\Http\Controllers;

use App\Models\MaintenanceSchedule;
use App\Models\ServiceLog;
use App\Models\Vehicle;
use App\Models\MaintenanceCategory;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Yajra\DataTables\Facades\DataTables;

class MaintenanceScheduleController extends Controller
{
    use CompanyFilterTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = MaintenanceSchedule::with(['vehicle', 'maintenanceCategory', 'company'])
                ->select(['maintenance_schedules.*']);

            // Apply company filter
            $query = $this->applyCompanyFilter($query);

            // Apply filters
            if ($request->filled('vehicle_id')) {
                $query->where('vehicle_id', $request->vehicle_id);
            }

            if ($request->filled('category_id')) {
                $query->where('maintenance_category_id', $request->category_id);
            }

            if ($request->filled('schedule_type')) {
                $query->where('schedule_type', $request->schedule_type);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('schedule_info', function ($row) {
                    $category = $row->maintenanceCategory ? $row->maintenanceCategory->name : 'N/A';

                    return '
                    <div class="text-sm">
                        <div class="font-medium text-gray-900 dark:text-white">' . e($row->title ?: $category) . '</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">' . $row->schedule_type_label . '</div>
                    </div>';
                })
                ->addColumn('vehicle_info', function ($row) {
                    if (!$row->vehicle) {
                        return '<span class="text-sm text-gray-500 dark:text-gray-400">All Vehicles</span>';
                    }

                    $vehicle = $row->vehicle;
                    return '
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-8 w-8">
                            <div class="h-8 w-8 rounded-full bg-brand-100 flex items-center justify-center text-brand-600">
                                <i class="fas fa-truck text-xs"></i>
                            </div>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                ' . e($vehicle->unit_no) . '
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                ' . e($vehicle->year . ' ' . $vehicle->make . ' ' . $vehicle->model) . '
                            </div>
                        </div>
                    </div>';
                })
                ->addColumn('interval', function ($row) {
                    return '
                    <div class="text-sm">
                        <div class="font-medium text-gray-900 dark:text-white">' . $row->interval_text . '</div>
                    </div>';
                })
                ->addColumn('next_due', function ($row) {
                    $isDue = $row->isDue();
                    $dueClass = $isDue ? 'text-red-600 font-bold' : 'text-gray-900';

                    return '
                    <div class="text-sm">
                        <div class="' . $dueClass . ' dark:text-white">' . $row->next_due_text . '</div>
                        ' . ($isDue ? '<span class="text-xs text-red-500">Due now!</span>' : '') . '
                    </div>';
                })
                ->addColumn('status', function ($row) {
                    return $row->status_badge;
                })
                ->addColumn('action', function ($row) {
                    $viewBtn = '<button onclick="viewSchedule(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors mr-1"
                                title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>';

                    $editBtn = '<button onclick="editSchedule(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-indigo-600 bg-indigo-100 rounded-md hover:bg-indigo-200 transition-colors mr-1"
                                title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>';

                    $deleteBtn = '<button onclick="deleteSchedule(' . $row->id . ')" 
                                class="inline-flex items-center px-3 py-1 text-sm text-red-600 bg-red-100 rounded-md hover:bg-red-200 transition-colors"
                                title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>';

                    return '<div class="flex justify-center space-x-1">' . $viewBtn . $editBtn . $deleteBtn . '</div>';
                })
                ->addColumn('created_at_formatted', function ($row) {
                    return $row->created_at->format('M d, Y');
                })
                ->rawColumns(['schedule_info', 'vehicle_info', 'interval', 'next_due', 'status', 'action'])
                ->make(true);
        }

        // Get dropdown data for filters
        $vehicles = $this->applyCompanyFilter(Vehicle::orderBy('unit_no'))->get();
        $maintenanceCategories = MaintenanceCategory::orderBy('name')->get();
        $companies = $this->getCompaniesForUser();

        return view('admin.maintenance-schedule.index', compact('vehicles', 'maintenanceCategories', 'companies'));
    }

    public function store(Request $request)
    {
        $companyId = $this->getAllUserCompanyId();

        if (!Auth::user()->hasRole('super-admin')) {
            $request->merge(['company_id' => $companyId]);
        }

        $isSuperAdmin = Auth::user()->hasRole('super-admin');

        $validator = Validator::make($request->all(), [
            'vehicle_id' => $this->vehicleRules($request),
            // A super-admin's schedule for all vehicles names its company (PO-07); with a
            // vehicle, the company comes from the vehicle
            'company_id' => $isSuperAdmin
                ? [Rule::requiredIf(! $request->filled('vehicle_id')), 'nullable', 'integer', Rule::exists('companies', 'id')]
                : ['nullable'],
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
                'errors' => $validator->errors()
            ], 422);
        }

        // A super-admin's schedule belongs to the vehicle's company, or to the company they
        // picked for an all-vehicles schedule (PO-07); tenants always get their own
        $companyId = $this->getOwningCompanyId(
            $request->vehicle_id
                ? Vehicle::withTrashed()->whereKey($request->vehicle_id)->value('company_id')
                : ($isSuperAdmin ? $request->integer('company_id') : null)
        );

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
                'data' => $schedule->load(['vehicle', 'maintenanceCategory'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create schedule. Please try again.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $schedule = MaintenanceSchedule::with(['vehicle', 'maintenanceCategory'])->findOrFail($id);

        $this->authorizeCompanyAccess($schedule, 'You do not have permission to edit this schedule.');

        return response()->json([
            'success' => true,
            'data' => $schedule
        ]);
    }

    public function show($id)
    {
        $schedule = MaintenanceSchedule::with(['vehicle', 'maintenanceCategory', 'company'])
            ->findOrFail($id);

        $this->authorizeCompanyAccess($schedule, 'You do not have permission to view this schedule.');

        // The details modal shows these computed values
        $schedule->append(['schedule_type_label', 'interval_text', 'next_due_text', 'status_badge']);
        $schedule->vehicle?->append('full_name');

        return response()->json([
            'success' => true,
            'data' => array_merge($schedule->toArray(), ['is_due' => $schedule->isDue()])
        ]);
    }

    public function update(Request $request, $id)
    {
        $schedule = MaintenanceSchedule::findOrFail($id);

        $this->authorizeCompanyAccess($schedule, 'You do not have permission to update this schedule.');

        if (!Auth::user()->hasRole('super-admin')) {
            $request->merge(['company_id' => $schedule->company_id]);
        }

        $isSuperAdmin = Auth::user()->hasRole('super-admin');

        $validator = Validator::make($request->all(), [
            'vehicle_id' => $this->vehicleRules($request),
            // A super-admin may move an all-vehicles schedule to another company (PO-07)
            'company_id' => $isSuperAdmin
                ? ['nullable', 'integer', Rule::exists('companies', 'id')]
                : ['nullable'],
            'maintenance_category_id' => 'required|exists:maintenance_categories,id',
            'title' => 'nullable|string|max:255',
            'schedule_type' => 'required|in:date,mileage,engine_hours',
            'interval_days' => 'required_if:schedule_type,date|nullable|integer|min:1|max:9999',
            'interval_miles' => 'required_if:schedule_type,mileage|nullable|integer|min:1|max:999999',
            'interval_hours' => 'required_if:schedule_type,engine_hours|nullable|integer|min:1|max:999999',
            'last_due_date' => 'nullable|date',
            'last_due_mileage' => 'nullable|integer|min:0',
            'last_due_hours' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,paused,completed',
        ], $this->validationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Only the validated fields; next_due_* is never taken from the request.
        // A schedule follows its vehicle's company. When it applies to all vehicles it keeps its
        // company, unless a super-admin picked another one (PO-07).
        $data = $validator->validated();
        $data['company_id'] = $request->vehicle_id
            ? $this->getOwningCompanyId(Vehicle::whereKey($request->vehicle_id)->value('company_id'))
            : ($isSuperAdmin && $request->filled('company_id') ? $request->integer('company_id') : $schedule->company_id);
        $data['interval_days'] = $request->schedule_type === 'date' ? $request->interval_days : null;
        $data['interval_miles'] = $request->schedule_type === 'mileage' ? $request->interval_miles : null;
        $data['interval_hours'] = $request->schedule_type === 'engine_hours' ? $request->interval_hours : null;

        DB::beginTransaction();

        try {
            $schedule->fill($data);

            // Recalculate next due only when what it depends on changed, so editing the
            // notes doesn't move the due date
            if ($schedule->isDirty(['vehicle_id', 'schedule_type', 'interval_days', 'interval_miles', 'interval_hours', 'last_due_date', 'last_due_mileage', 'last_due_hours'])) {
                $schedule->calculateNextDue();
            }

            $schedule->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Maintenance schedule updated successfully!',
                'data' => $schedule->load(['vehicle', 'maintenanceCategory'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update schedule. Please try again.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $schedule = MaintenanceSchedule::findOrFail($id);

        $this->authorizeCompanyAccess($schedule, 'You do not have permission to delete this schedule.');

        DB::beginTransaction();

        try {
            $schedule->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Maintenance schedule deleted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule delete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete schedule. Please try again.'
            ], 500);
        }
    }

    public function getDropdownData()
    {
        $vehiclesQuery = Vehicle::orderBy('unit_no');
        $vehiclesQuery = $this->applyCompanyFilter($vehiclesQuery);
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
                'engine_hours' => 'By Engine Hours'
            ],
            'statusOptions' => [
                'active' => 'Active',
                'paused' => 'Paused',
                'completed' => 'Completed'
            ]
        ]);
    }

    public function getVehicleDetails($id)
    {
        try {
            $vehicle = Vehicle::findOrFail($id);

            $this->authorizeCompanyAccess($vehicle, 'You do not have permission to view this vehicle.');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $vehicle->id,
                    'unit_no' => $vehicle->unit_no,
                    'full_name' => $vehicle->full_name,
                    'current_odometer' => $vehicle->odometer,
                    'engine_hours' => ServiceLog::currentEngineHoursFor($vehicle->id),
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

    public function markAsCompleted($id)
    {
        $schedule = MaintenanceSchedule::findOrFail($id);

        $this->authorizeCompanyAccess($schedule, 'You do not have permission to update this schedule.');

        DB::beginTransaction();

        try {
            // Recording a service keeps a recurring schedule running: it stays in its status and
            // only its last / next due values move forward. "completed" means retired, set by
            // editing the schedule (PO-04).
            switch ($schedule->schedule_type) {
                case 'date':
                    $schedule->last_due_date = now();
                    break;
                case 'mileage':
                    if ($schedule->vehicle) {
                        $schedule->last_due_mileage = $schedule->vehicle->odometer;
                    }
                    break;
                case 'engine_hours':
                    if ($schedule->vehicle) {
                        $schedule->last_due_hours = $schedule->currentEngineHours();
                    }
                    break;
            }

            $schedule->calculateNextDue()->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Schedule marked as completed!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule complete error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark schedule as completed.'
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
            'company_id.required' => 'Choose the company this schedule belongs to.',
        ];
    }

    /**
     * A vehicle the user may attach records to: not deleted, and in the tenant's own company.
     */
    private function vehicleRule(): Exists
    {
        $rule = Rule::exists('vehicles', 'id')->whereNull('deleted_at');

        if (!Auth::user()->hasRole('super-admin')) {
            $rule->where('company_id', $this->getUserCompanyId() ?? -1);
        }

        return $rule;
    }
}
