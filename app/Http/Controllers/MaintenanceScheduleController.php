<?php
// app/Http/Controllers/MaintenanceScheduleController.php

namespace App\Http\Controllers;

use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use App\Models\MaintenanceCategory;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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
                        <div class="font-medium text-gray-900 dark:text-white">' . ($row->title ?: $category) . '</div>
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
                                ' . $vehicle->unit_no . '
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                ' . $vehicle->year . ' ' . $vehicle->make . ' ' . $vehicle->model . '
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
        $vehicles = Vehicle::orderBy('unit_no')->get();
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

        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'maintenance_category_id' => 'required|exists:maintenance_categories,id',
            'title' => 'nullable|string|max:255',
            'schedule_type' => 'required|in:date,mileage,engine_hours',
            'interval_days' => 'required_if:schedule_type,date|nullable|integer|min:1|max:9999',
            'interval_miles' => 'required_if:schedule_type,mileage|nullable|integer|min:1|max:999999',
            'interval_hours' => 'required_if:schedule_type,engine_hours|nullable|integer|min:1|max:999999',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,paused,completed',
        ], [
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

        return response()->json([
            'success' => true,
            'data' => $schedule
        ]);
    }

    public function update(Request $request, $id)
    {
        $schedule = MaintenanceSchedule::findOrFail($id);

        $this->authorizeCompanyAccess($schedule, 'You do not have permission to update this schedule.');

        if (!Auth::user()->hasRole('super-admin')) {
            $request->merge(['company_id' => $schedule->company_id]);
        }

        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'nullable|exists:vehicles,id',
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $schedule->update($request->all());

            // Recalculate next due
            $schedule->calculateNextDue()->save();

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
                    'engine_hours' => $vehicle->engine_hours ?? 0,
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
            $schedule->status = 'completed';

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
                        $schedule->last_due_hours = $schedule->vehicle->engine_hours ?? 0;
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
}
