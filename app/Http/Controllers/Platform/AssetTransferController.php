<?php

namespace App\Http\Controllers\Platform;

use App\DataTables\Platform\AssetTransferTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\AssetTransferRequest;
use App\Models\Company;
use App\Models\PlatformUser;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Services\Platform\AssetTransferService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Asset Transfer (PO-08, 04 §7), a platform operation with a reason and a confirmation step.
 */
class AssetTransferController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return (new AssetTransferTable($request))->json();
        }

        return view('platform.asset-transfers.index');
    }

    public function create(): View
    {
        return view('platform.asset-transfers.create', [
            'companies' => Company::query()->orderBy('company_name')->get(['id', 'company_name', 'status']),
        ]);
    }

    /**
     * The picker: live assets of one company, by unit no and VIN only (no other tenant data).
     */
    public function assets(Request $request, TenantContext $tenant): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'asset_type' => ['required', 'in:vehicle,trailer'],
        ]);

        $assets = $tenant->run((int) $validated['company_id'], fn () => ($validated['asset_type'] === 'vehicle' ? Vehicle::query() : Trailer::query())
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'vin']));

        return response()->json(['data' => $assets]);
    }

    public function store(AssetTransferRequest $request, AssetTransferService $transfers): RedirectResponse
    {
        /** @var PlatformUser $actor */
        $actor = $request->user('platform');

        $transfer = $transfers->transfer(
            $actor,
            $request->validated('asset_type'),
            (int) $request->validated('asset_id'),
            Company::query()->findOrFail($request->validated('source_company_id')),
            Company::query()->findOrFail($request->validated('target_company_id')),
            $request->validated('reason'),
        );

        return redirect()->route('platform.asset-transfers.index')
            ->with('success', "Transfer #{$transfer->id} completed. Both company owners were notified.");
    }
}
