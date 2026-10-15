<?php

namespace App\Http\Controllers;

use App\Exceptions\LimitReached;
use App\Features\Feature;
use App\Models\AssetGroup;
use App\Models\AssetTransfer;
use App\Models\FuelType;
use App\Models\Vehicle;
use App\Models\VehicleGroup;
use App\Models\VehicleType;
use App\Services\Entitlements\Limits;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Yajra\DataTables\Facades\DataTables;

class VehicleController extends Controller
{
    use CompanyFilterTrait;

    private const VALIDATION_MESSAGES = [
        'vin.not_regex' => 'A VIN never contains the letters I, O or Q.',
    ];

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Vehicle::with(['vehicleType', 'vehicleGroup', 'fuelType', 'company'])
                ->select(['vehicles.*']);

            // "All Status" lists deleted vehicles too (with Restore), "Active" only live ones and
            // "Deleted" only deleted ones (PO-09)
            $status = $request->input('status');
            if ($status === 'deleted') {
                $query->onlyTrashed();
            } elseif ($status !== 'active') {
                $query->withTrashed();
            }

            if ($request->filled('vehicle_type_id')) {
                $query->where('vehicle_type_id', $request->input('vehicle_type_id'));
            }

            $searchText = $request->input('search_text');
            if (is_string($searchText) && trim($searchText) !== '') {
                $query->search(trim($searchText));
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('company_name', function ($row) {
                    return $row->company ? $row->company->company_name : 'N/A';
                })
                ->addColumn('vehicle_info', function ($row) {
                    $notesIcon = $row->notes ? '<i class="fas fa-sticky-note ml-2 text-blue-500 text-xs" title="Has notes"></i>' : '';

                    return '
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10">
                            <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-600">
                                <i class="fas fa-truck"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white flex items-center">
                                '.e($row->unit_no).' - '.e($row->year).' '.e($row->make).' '.e($row->model).'
                                '.$notesIcon.'
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                VIN: '.e($row->vin).'
                            </div>
                        </div>
                    </div>';
                })
                ->addColumn('type_group', function ($row) {
                    $type = $row->vehicleType ? e($row->vehicleType->name) : '<span class="text-gray-400">N/A</span>';
                    $group = $row->vehicleGroup ? e($row->vehicleGroup->name) : '<span class="text-gray-400">N/A</span>';

                    return '
                    <div class="text-sm">
                        <div><span class="font-medium">Type:</span> '.$type.'</div>
                        <div><span class="font-medium">Group:</span> '.$group.'</div>
                    </div>';
                })
                ->addColumn('fuel_odometer', function ($row) {
                    $fuel = $row->fuelType ? e($row->fuelType->name) : '<span class="text-gray-400">N/A</span>';

                    return '
                    <div class="text-sm">
                        <div><span class="font-medium">Fuel:</span> '.$fuel.'</div>
                        <div><span class="font-medium">Odometer:</span> '.number_format($row->odometer).' mi</div>
                    </div>';
                })
                ->addColumn('status', function ($row) {
                    $status = $row->deleted_at ? 'deleted' : 'active';
                    $badgeClass = $status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                    $statusText = $status === 'active' ? 'Active' : 'Deleted';

                    return '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full '.$badgeClass.'">
                        '.$statusText.'
                    </span>';
                })
                ->addColumn('action', function ($row) {
                    // The page binds these with a delegated listener on .js-vehicle-action
                    $data = 'data-id="'.(int) $row->id.'" data-unit-no="'.e($row->unit_no).'"';

                    if ($row->deleted_at) {
                        // A deleted vehicle can't be edited (edit() 404s), only restored
                        return '<div class="flex justify-center space-x-1"><button type="button" data-action="restore" '.$data.'
                                    class="js-vehicle-action inline-flex items-center px-3 py-1 text-sm text-green-600 bg-green-100 rounded-md hover:bg-green-200 transition-colors">
                                    <i class="fas fa-trash-restore mr-1"></i> Restore
                                </button></div>';
                    }

                    $editBtn = '<button type="button" data-action="edit" '.$data.'
                                class="js-vehicle-action inline-flex items-center px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors mr-2">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>';

                    $deleteBtn = '<button type="button" data-action="delete" '.$data.'
                                class="js-vehicle-action inline-flex items-center px-3 py-1 text-sm text-red-600 bg-red-100 rounded-md hover:bg-red-200 transition-colors">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>';

                    return '<div class="flex justify-center space-x-1">'.$editBtn.$deleteBtn.'</div>';
                })
                ->addColumn('created_at_formatted', function ($row) {
                    return $row->created_at->format('M d, Y');
                })
                ->rawColumns(['vehicle_info', 'type_group', 'fuel_odometer', 'status', 'action'])
                ->make(true);
        }

        // Get dropdown data for filters
        $vehicleTypes = VehicleType::orderBy('name')->get();
        $vehicleGroups = VehicleGroup::orderBy('name')->get();
        $fuelTypes = FuelType::orderBy('name')->get();
        $companies = $this->getCompaniesForUser();

        return view('admin.vehicle.index', compact('vehicleTypes', 'vehicleGroups', 'fuelTypes', 'companies'));
    }

    public function store(Request $request)
    {
        // Always the user's own company
        $request->merge(['company_id' => $this->getAllUserCompanyId()]);

        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'unit_no' => ['required', 'string', 'max:50', $this->uniqueInCompany('unit_no', $request->input('company_id'))],
            'vin' => ['required', 'string', 'size:17', 'not_regex:/[IOQ]/i', $this->uniqueInCompany('vin', $request->input('company_id'))],
            'year' => 'required|integer|min:1900|max:'.(date('Y') + 1),
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
            'owned_by' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'title_no' => 'nullable|string|max:100',
            'tire_size' => 'nullable|string|max:50',
            'odometer' => 'required|integer|min:0',
            'engine_hours' => 'nullable|integer|min:0',
            'gvw' => 'nullable|integer|min:0',
            'vehicle_group_id' => 'nullable|exists:vehicle_groups,id',
            'fuel_type_id' => 'nullable|exists:fuel_types,id',
            'engine_type' => 'nullable|string|max:100',
            'transmission' => 'nullable|string|max:100',
            'suspension' => 'nullable|string|max:100',
            'no_axles' => 'nullable|integer|min:1|max:10',
            'configuration' => 'nullable|in:conventional,cabover',
            'wheel_base' => 'nullable|integer|min:0',
            'size_dimension' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ], self::VALIDATION_MESSAGES);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            Limits::assertCanCreate((int) $request->company_id, Feature::MaxVehicles);

            $vehicle = Vehicle::create([
                'company_id' => $request->company_id,
                'unit_no' => $request->unit_no,
                'vin' => $request->vin,
                'year' => $request->year,
                'make' => $request->make,
                'model' => $request->model,
                'vehicle_type_id' => $request->vehicle_type_id,
                'owned_by' => $request->owned_by,
                'color' => $request->color,
                'title_no' => $request->title_no,
                'tire_size' => $request->tire_size,
                'odometer' => $request->odometer,
                'engine_hours' => $request->engine_hours,
                'gvw' => $request->gvw,
                'vehicle_group_id' => $request->vehicle_group_id,
                'fuel_type_id' => $request->fuel_type_id,
                'engine_type' => $request->engine_type,
                'transmission' => $request->transmission,
                'suspension' => $request->suspension,
                'no_axles' => $request->no_axles,
                'configuration' => $request->configuration,
                'wheel_base' => $request->wheel_base,
                'size_dimension' => $request->size_dimension,
                'notes' => $request->notes,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle created successfully!',
                'data' => $vehicle,
            ]);
        } catch (LimitReached $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vehicle store error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create vehicle. Please try again.',
            ], 500);
        }
    }

    public function edit($id)
    {
        $vehicle = Vehicle::with(['vehicleType', 'vehicleGroup', 'fuelType', 'company'])->findOrFail($id);

        // Check if user has access to this vehicle
        Gate::authorize('update', $vehicle);

        return response()->json([
            'success' => true,
            'data' => $vehicle,
        ]);
    }

    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::findOrFail($id);

        // Check if user has access to this vehicle
        Gate::authorize('update', $vehicle);

        // A vehicle never changes company here; the platform's Asset Transfer moves it (PO-08)
        $request->merge(['company_id' => $vehicle->company_id]);

        $validator = Validator::make($request->all(), [
            'company_id' => [
                'required',
                'exists:companies,id',
            ],
            'unit_no' => ['required', 'string', 'max:50', $this->uniqueInCompany('unit_no', $request->input('company_id'), $vehicle->id)],
            'vin' => ['required', 'string', 'size:17', 'not_regex:/[IOQ]/i', $this->uniqueInCompany('vin', $request->input('company_id'), $vehicle->id)],
            'year' => 'required|integer|min:1900|max:'.(date('Y') + 1),
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
            'owned_by' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'title_no' => 'nullable|string|max:100',
            'tire_size' => 'nullable|string|max:50',
            'odometer' => 'required|integer|min:0',
            'engine_hours' => 'nullable|integer|min:0',
            'gvw' => 'nullable|integer|min:0',
            'vehicle_group_id' => 'nullable|exists:vehicle_groups,id',
            'fuel_type_id' => 'nullable|exists:fuel_types,id',
            'engine_type' => 'nullable|string|max:100',
            'transmission' => 'nullable|string|max:100',
            'suspension' => 'nullable|string|max:100',
            'no_axles' => 'nullable|integer|min:1|max:10',
            'configuration' => 'nullable|in:conventional,cabover',
            'wheel_base' => 'nullable|integer|min:0',
            'size_dimension' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ], self::VALIDATION_MESSAGES);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $vehicle->update($validator->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle updated successfully!',
                'data' => $vehicle,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vehicle update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle. Please try again.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        $vehicle = Vehicle::findOrFail($id);

        // Check if user has access to this vehicle
        Gate::authorize('delete', $vehicle);

        DB::beginTransaction();

        try {
            $vehicle->delete();

            // Its asset group no longer has a vehicle: end it (PO-22)
            AssetGroup::where('vehicle_id', $vehicle->id)->where('status', 'active')->update(['status' => 'inactive']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle deleted successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vehicle delete error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vehicle. Please try again.',
            ], 500);
        }
    }

    public function restore($id)
    {
        $vehicle = Vehicle::withTrashed()->findOrFail($id);

        // Check if user has access to this vehicle
        Gate::authorize('restore', $vehicle);

        // Transferred to another company: its history here stays read-only (04 §7)
        if (AssetTransfer::retired('vehicle', $vehicle->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This vehicle was transferred to another company and can\'t be restored.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($vehicle->trashed()) {
                Limits::assertCanCreate($vehicle->company_id, Feature::MaxVehicles);
            }

            $vehicle->restore();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle restored successfully!',
            ]);
        } catch (LimitReached $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vehicle restore error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore vehicle. Please try again.',
            ], 500);
        }
    }

    public function getDropdownData()
    {
        $vehicleTypes = VehicleType::orderBy('name')->get(['id', 'name']);
        $vehicleGroups = VehicleGroup::orderBy('name')->get(['id', 'name']);
        $fuelTypes = FuelType::orderBy('name')->get(['id', 'name']);
        $companies = $this->getCompaniesForUser();

        return response()->json([
            'success' => true,
            'vehicleTypes' => $vehicleTypes,
            'vehicleGroups' => $vehicleGroups,
            'fuelTypes' => $fuelTypes,
            'companies' => $companies,
            'configurations' => Vehicle::getConfigurationOptions(),
            'ownedByOptions' => Vehicle::getOwnedByOptions(),
        ]);
    }

    public function getVehicleDetails($id)
    {
        try {
            $vehicle = Vehicle::findOrFail($id);

            // Check if user has access to this vehicle
            Gate::authorize('view', $vehicle);

            return response()->json([
                'success' => true,
                'data' => [
                    'unit_no' => $vehicle->unit_no,
                    'year' => $vehicle->year,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
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

    /**
     * Unit no and VIN are unique within a company (deleted vehicles included, as in the DB index).
     */
    private function uniqueInCompany(string $column, mixed $companyId, ?int $ignoreId = null): Unique
    {
        return Rule::unique('vehicles', $column)
            ->where('company_id', is_scalar($companyId) ? $companyId : null)
            ->ignore($ignoreId);
    }
}
