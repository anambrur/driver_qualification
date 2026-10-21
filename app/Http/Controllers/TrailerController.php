<?php

namespace App\Http\Controllers;

use App\DataTables\Fleet\TrailerTable;
use App\Exceptions\LimitReached;
use App\Features\Feature;
use App\Http\Requests\Fleet\TrailerRequest;
use App\Models\AssetTransfer;
use App\Models\EquipmentType;
use App\Models\Trailer;
use App\Models\VehicleGroup;
use App\Services\Entitlements\Limits;
use App\Services\Files\StoredFileService;
use App\Services\Fleet\FleetAssetService;
use App\Support\Fleet\PlateStates;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class TrailerController extends Controller
{
    use CompanyFilterTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return (new TrailerTable($request))->json();
        }

        // Get dropdown data for filters
        $equipmentTypes = EquipmentType::orderBy('name')->get(['id', 'name']);
        $vehicleGroups = VehicleGroup::orderBy('name')->get(['id', 'name']);

        $plateStates = PlateStates::options();

        return view('admin.trailer.index', compact('equipmentTypes', 'vehicleGroups', 'plateStates'));
    }

    public function store(TrailerRequest $request, FleetAssetService $assets)
    {
        try {
            $trailer = $assets->create(Trailer::class, $request->safe()->except(['photo', 'remove_photo']), $request->file('photo'));

            return response()->json([
                'success' => true,
                'message' => 'Trailer created successfully!',
                'data' => $trailer,
            ]);
        } catch (LimitReached $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Trailer store error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create trailer. Please try again.',
            ], 500);
        }
    }

    public function show($id)
    {
        $trailer = Trailer::with(['equipmentType', 'vehicleGroup'])->findOrFail($id);

        // Check if user has access to this trailer
        Gate::authorize('view', $trailer);

        return view('admin.trailer.show', compact('trailer'));
    }

    /**
     * The trailer photo, inline, only through this authorized route (never a public URL).
     */
    public function photo($id, StoredFileService $files)
    {
        $trailer = Trailer::with('photoFile')->findOrFail($id);
        Gate::authorize('view', $trailer);

        return $files->response($trailer->photoFile);
    }

    public function edit($id)
    {
        $trailer = Trailer::with(['equipmentType', 'vehicleGroup', 'company'])->findOrFail($id);

        // Check if user has access to this trailer
        Gate::authorize('update', $trailer);

        return response()->json([
            'success' => true,
            'data' => $trailer->toArray() + [
                'photo_url' => $trailer->photo_file_id ? route('admin.trailer.photo', $trailer->id) : null,
            ],
        ]);
    }

    public function update(TrailerRequest $request, FleetAssetService $assets, $id)
    {
        $trailer = Trailer::findOrFail($id);

        // Check if user has access to this trailer
        Gate::authorize('update', $trailer);

        try {
            $assets->update($trailer, $request->safe()->except(['photo', 'remove_photo']), $request->file('photo'), $request->boolean('remove_photo'));

            return response()->json([
                'success' => true,
                'message' => 'Trailer updated successfully!',
                'data' => $trailer,
            ]);
        } catch (LimitReached $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Trailer update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update trailer. Please try again.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        $trailer = Trailer::findOrFail($id);

        // Check if user has access to this trailer
        Gate::authorize('delete', $trailer);

        DB::beginTransaction();

        try {
            $trailer->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Trailer deleted successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Trailer delete error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete trailer. Please try again.',
            ], 500);
        }
    }

    public function restore($id)
    {
        $trailer = Trailer::withTrashed()->findOrFail($id);

        // Check if user has access to this trailer
        Gate::authorize('restore', $trailer);

        // Transferred to another company: its history here stays read-only (04 §7)
        if (AssetTransfer::retired('trailer', $trailer->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This trailer was transferred to another company and can\'t be restored.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($trailer->trashed()) {
                Limits::assertCanCreate($trailer->company_id, Feature::MaxTrailers);
            }

            $trailer->restore();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Trailer restored successfully!',
            ]);
        } catch (LimitReached $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Trailer restore error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore trailer. Please try again.',
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
            'ownedByOptions' => ['company' => 'Company Owned', 'lease' => 'Leased', 'rental' => 'Rental'],
        ]);
    }

    public function getTrailerDetails($id)
    {
        try {
            $trailer = Trailer::findOrFail($id);

            // Check if user has access to this trailer
            Gate::authorize('view', $trailer);

            return response()->json([
                'success' => true,
                'data' => [
                    'unit_no' => $trailer->unit_no,
                    'year' => $trailer->year,
                    'make' => $trailer->make,
                    'model' => $trailer->model,
                    'vin' => $trailer->vin,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Trailer not found',
            ], 404);
        }
    }
}
