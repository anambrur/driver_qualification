<?php

namespace App\Http\Controllers;

use App\DataTables\Fleet\TrailerTable;
use App\Exceptions\LimitReached;
use App\Features\Feature;
use App\Models\AssetTransfer;
use App\Models\EquipmentType;
use App\Models\Trailer;
use App\Models\VehicleGroup;
use App\Services\Entitlements\Limits;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class TrailerController extends Controller
{
    use CompanyFilterTrait;

    private const VALIDATION_MESSAGES = [
        'vin.not_regex' => 'A VIN never contains the letters I, O or Q.',
    ];

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return (new TrailerTable($request))->json();
        }

        // Get dropdown data for filters
        $equipmentTypes = EquipmentType::orderBy('name')->get(['id', 'name']);
        $vehicleGroups = VehicleGroup::orderBy('name')->get(['id', 'name']);

        return view('admin.trailer.index', compact('equipmentTypes', 'vehicleGroups'));
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
            'equipment_type_id' => 'required|exists:equipment_types,id',
            'owned_by' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'title_no' => 'nullable|string|max:100',
            'tire_size' => 'nullable|string|max:50',
            'gvw' => 'nullable|integer|min:0',
            'vehicle_group_id' => 'nullable|exists:vehicle_groups,id',
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
            Limits::assertCanCreate((int) $request->company_id, Feature::MaxTrailers);

            $trailer = Trailer::create($validator->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Trailer created successfully!',
                'data' => $trailer,
            ]);
        } catch (LimitReached $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Trailer store error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create trailer. Please try again.',
            ], 500);
        }
    }

    public function edit($id)
    {
        $trailer = Trailer::with(['equipmentType', 'vehicleGroup', 'company'])->findOrFail($id);

        // Check if user has access to this trailer
        Gate::authorize('update', $trailer);

        return response()->json([
            'success' => true,
            'data' => $trailer,
        ]);
    }

    public function update(Request $request, $id)
    {
        $trailer = Trailer::findOrFail($id);

        // Check if user has access to this trailer
        Gate::authorize('update', $trailer);

        // A trailer never changes company here; the platform's Asset Transfer moves it (PO-08)
        $request->merge(['company_id' => $trailer->company_id]);

        $validator = Validator::make($request->all(), [
            'company_id' => [
                'required',
                'exists:companies,id',
            ],
            'unit_no' => ['required', 'string', 'max:50', $this->uniqueInCompany('unit_no', $request->input('company_id'), $trailer->id)],
            'vin' => ['required', 'string', 'size:17', 'not_regex:/[IOQ]/i', $this->uniqueInCompany('vin', $request->input('company_id'), $trailer->id)],
            'year' => 'required|integer|min:1900|max:'.(date('Y') + 1),
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'equipment_type_id' => 'required|exists:equipment_types,id',
            'owned_by' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'title_no' => 'nullable|string|max:100',
            'tire_size' => 'nullable|string|max:50',
            'gvw' => 'nullable|integer|min:0',
            'vehicle_group_id' => 'nullable|exists:vehicle_groups,id',
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
            $trailer->update($validator->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Trailer updated successfully!',
                'data' => $trailer,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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

    /**
     * Unit no and VIN are unique within a company (deleted trailers included, as in the DB index).
     */
    private function uniqueInCompany(string $column, mixed $companyId, ?int $ignoreId = null): Unique
    {
        return Rule::unique('trailers', $column)
            ->where('company_id', is_scalar($companyId) ? $companyId : null)
            ->ignore($ignoreId);
    }
}
