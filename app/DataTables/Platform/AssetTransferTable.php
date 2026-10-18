<?php

namespace App\DataTables\Platform;

use App\Models\AssetTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side asset transfer history (platform/asset-transfers, PO-08). Search matches the
 * source or target company name.
 */
final class AssetTransferTable
{
    public const ASSET_TYPES = ['vehicle', 'trailer'];

    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $query = AssetTransfer::query()
            ->select(['asset_transfers.id', 'asset_type', 'source_company_id', 'target_company_id', 'reason', 'status', 'copied_counts', 'performed_by_platform_user_id', 'performed_at'])
            ->with(['sourceCompany:id,company_name', 'targetCompany:id,company_name', 'performedBy:id,name']);

        $type = $this->request->input('asset_type');
        if (in_array($type, self::ASSET_TYPES, true)) {
            $query->where('asset_type', $type);
        }

        $term = trim((string) data_get($this->request->input('search'), 'value', ''));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q
                ->whereHas('sourceCompany', fn ($q) => $q->where('company_name', 'like', $like))
                ->orWhereHas('targetCompany', fn ($q) => $q->where('company_name', 'like', $like)));
        }

        return DataTables::eloquent($query)
            ->addColumn('performed_at_formatted', fn (AssetTransfer $transfer) => $transfer->performed_at->format('M j, Y g:i A'))
            ->editColumn('asset_type', fn (AssetTransfer $transfer) => ucfirst($transfer->asset_type))
            ->addColumn('route', fn (AssetTransfer $transfer) => view('platform.partials.cell', [
                'title' => $transfer->sourceCompany->company_name, 'sub' => '→ '.$transfer->targetCompany->company_name,
            ])->render())
            ->addColumn('copied', fn (AssetTransfer $transfer) => view('admin.vehicle.partials.pair-cell', [
                'rows' => collect($transfer->copied_counts ?? [])->mapWithKeys(fn ($count, $what) => [ucfirst(str_replace('_', ' ', (string) $what)) => $count])->all(),
            ])->render())
            ->addColumn('status', fn (AssetTransfer $transfer) => view('partials.ui.status-badge', ['status' => $transfer->status])->render())
            ->addColumn('performed_by', fn (AssetTransfer $transfer) => $transfer->performedBy->name)
            ->orderColumn('performed_at_formatted', 'performed_at $1')
            ->filter(fn () => null) // searched above
            ->rawColumns(['route', 'copied', 'status'])
            ->make(true);
    }
}
