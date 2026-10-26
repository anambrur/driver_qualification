<?php

namespace App\Http\Controllers;

use App\DataTables\Fleet\VehicleTable;
use App\Exceptions\LimitReached;
use App\Features\Feature;
use App\Http\Requests\Fleet\VehicleRequest;
use App\Models\AssetGroup;
use App\Models\FuelType;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleGroup;
use App\Models\VehicleType;
use App\Services\Documents\FolderBrowser;
use App\Services\Entitlements\Limits;
use App\Services\Files\StoredFileService;
use App\Services\Fleet\FleetAssetService;
use App\Support\Fleet\PlateStates;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class VehicleController extends Controller
{
    use CompanyFilterTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return (new VehicleTable($request))->json();
        }

        // Get dropdown data for filters
        $vehicleTypes = VehicleType::orderBy('name')->get(['id', 'name']);
        $vehicleGroups = VehicleGroup::orderBy('name')->get(['id', 'name']);
        $fuelTypes = FuelType::orderBy('name')->get(['id', 'name']);

        $plateStates = PlateStates::options();

        return view('admin.vehicle.index', compact('vehicleTypes', 'vehicleGroups', 'fuelTypes', 'plateStates'));
    }

    public function store(VehicleRequest $request, FleetAssetService $assets)
    {
        try {
            $vehicle = $assets->create(Vehicle::class, $request->safe()->except(['photo', 'remove_photo']), $request->file('photo'));

            return response()->json([
                'success' => true,
                'message' => 'Vehicle created successfully!',
                'data' => $vehicle,
            ]);
        } catch (LimitReached $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Vehicle store error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create vehicle. Please try again.',
            ], 500);
        }
    }

    public function show(Request $request, $id, FolderBrowser $browser)
    {
        $vehicle = Vehicle::with(['vehicleType', 'vehicleGroup', 'fuelType'])->findOrFail($id);

        // Check if user has access to this vehicle
        Gate::authorize('view', $vehicle);

        // The Documents tab; the typed compliance documents show read-only in the vehicle's folder.
        $documentsBrowser = $browser->tab($vehicle, $request->integer('folder') ?: null);
        $virtualDocuments = $documentsBrowser === null ? [] : $browser->virtualDocuments($vehicle);

        // Recent trips need the trips feature and trips.view (checked by the gate)
        $recentTrips = $request->user()->can('trips.view')
            ? Trip::query()->where('vehicle_id', $vehicle->id)->with('driver:id,first_name,last_name')
                ->orderByDesc('trip_date')->orderByDesc('trip_number')->limit(10)
                ->get(['id', 'trip_number', 'trip_date', 'status', 'driver_id'])
            : null;

        return view('admin.vehicle.show', compact('vehicle', 'documentsBrowser', 'virtualDocuments', 'recentTrips'));
    }

    /**
     * The vehicle photo, inline, only through this authorized route (never a public URL).
     */
    public function photo($id, StoredFileService $files)
    {
        $vehicle = Vehicle::with('photoFile')->findOrFail($id);
        Gate::authorize('view', $vehicle);

        return $files->response($vehicle->photoFile);
    }

    public function edit($id)
    {
        $vehicle = Vehicle::with(['vehicleType', 'vehicleGroup', 'fuelType', 'company'])->findOrFail($id);

        // Check if user has access to this vehicle
        Gate::authorize('update', $vehicle);

        return response()->json([
            'success' => true,
            'data' => $vehicle->toArray() + [
                'photo_url' => $vehicle->photo_file_id ? route('admin.vehicle.photo', $vehicle->id) : null,
            ],
        ]);
    }

    public function update(VehicleRequest $request, FleetAssetService $assets)
    {
        $vehicle = $request->vehicle() ?? abort(404);

        try {
            $assets->update($vehicle, $request->safe()->except(['photo', 'remove_photo']), $request->file('photo'), $request->boolean('remove_photo'));

            return response()->json([
                'success' => true,
                'message' => 'Vehicle updated successfully!',
                'data' => $vehicle,
            ]);
        } catch (LimitReached $e) {
            throw $e;
        } catch (\Exception $e) {
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
        if ($vehicle->transferred_out_at !== null) {
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
}
