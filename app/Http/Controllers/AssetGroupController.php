<?php

namespace App\Http\Controllers;

use App\DataTables\Fleet\AssetGroupTable;
use App\Models\AssetGroup;
use App\Models\Driver;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class AssetGroupController extends Controller
{
    use CompanyFilterTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return (new AssetGroupTable($request))->json();
        }

        // Get dropdown data for filters
        $vehicles = Vehicle::query()->orderBy('unit_no')->get(['id', 'unit_no', 'vin', 'year', 'make', 'model']);
        $trailers = Trailer::query()->orderBy('unit_no')->get(['id', 'unit_no', 'vin', 'year', 'make', 'model']);
        $drivers = Driver::where('employment_status', 'active')->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']);

        return view('admin.asset-group.index', compact('vehicles', 'trailers', 'drivers'));
    }

    public function store(Request $request)
    {
        $companyId = $this->owningCompanyId();

        $validator = Validator::make($request->all(), [
            'group_name' => ['required', 'string', 'max:100', $this->uniqueGroupNameRule($companyId)],
            'driver_id' => ['required', Rule::exists('drivers', 'id')->where('company_id', $companyId)->withoutTrashed()],
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('company_id', $companyId)],
            'trailer_id' => ['nullable', Rule::exists('trailers', 'id')->where('company_id', $companyId)],
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $assetGroup = AssetGroup::create([
                'company_id' => $companyId,
                'group_name' => $request->group_name,
                'driver_id' => $request->driver_id,
                'vehicle_id' => $request->vehicle_id,
                'trailer_id' => $request->trailer_id,
                'status' => $request->status,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asset group created successfully!',
                'data' => $assetGroup,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AssetGroup store error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create asset group. Please try again.',
            ], 500);
        }
    }

    public function edit($id)
    {
        // Only the driver's id and name: the select needs them when the driver is no longer active
        $assetGroup = AssetGroup::query()
            ->with(['vehicle', 'trailer', 'driver:id,first_name,last_name,employment_status'])
            ->findOrFail($id);
        Gate::authorize('view', $assetGroup);

        return response()->json([
            'success' => true,
            'data' => $assetGroup,
        ]);
    }

    public function update(Request $request, $id)
    {
        $assetGroup = AssetGroup::query()->findOrFail($id);
        Gate::authorize('update', $assetGroup);
        $companyId = $this->owningCompanyId();

        $validator = Validator::make($request->all(), [
            'group_name' => ['required', 'string', 'max:100', $this->uniqueGroupNameRule($companyId, $assetGroup->id)],
            'driver_id' => ['required', Rule::exists('drivers', 'id')->where('company_id', $companyId)->withoutTrashed()],
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('company_id', $companyId)],
            'trailer_id' => ['nullable', Rule::exists('trailers', 'id')->where('company_id', $companyId)],
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $assetGroup->update(array_merge($validator->validated(), [
                'company_id' => $companyId,
            ]));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asset group updated successfully!',
                'data' => $assetGroup,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AssetGroup update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update asset group. Please try again.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        $assetGroup = AssetGroup::query()->findOrFail($id);
        Gate::authorize('delete', $assetGroup);

        DB::beginTransaction();

        try {
            $assetGroup->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asset group deleted successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AssetGroup delete error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete asset group. Please try again.',
            ], 500);
        }
    }

    public function restore($id)
    {
        $assetGroup = AssetGroup::query()->withTrashed()->findOrFail($id);
        Gate::authorize('restore', $assetGroup);

        DB::beginTransaction();

        try {
            $assetGroup->restore();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asset group restored successfully!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AssetGroup restore error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore asset group. Please try again.',
            ], 500);
        }
    }

    public function getDropdownData()
    {
        $vehicles = Vehicle::query()
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'vin', 'make', 'model', 'year']);

        $trailers = Trailer::query()
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'vin', 'make', 'model', 'year']);

        return response()->json([
            'success' => true,
            'vehicles' => $vehicles,
            'trailers' => $trailers,
            'statusOptions' => AssetGroup::getStatusOptions(),
        ]);
    }

    /**
     * The company the submitted driver, vehicle and trailer must all belong to: the tenant's own.
     * -1 matches nothing.
     */
    private function owningCompanyId(): int
    {
        return $this->getUserCompanyId() ?? -1;
    }

    /**
     * Group names are unique per company (soft-deleted groups included, so a restore can't clash).
     */
    private function uniqueGroupNameRule(int $companyId, ?int $ignoreId = null): Unique
    {
        return Rule::unique('asset_groups', 'group_name')->where('company_id', $companyId)->ignore($ignoreId);
    }
}
