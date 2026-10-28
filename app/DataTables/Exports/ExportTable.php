<?php

namespace App\DataTables\Exports;

use App\Models\Driver;
use App\Models\Export;
use App\Models\Trip;
use App\Services\Exports\ExportService;
use App\Support\Ui\FileSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * The company's exports, newest first (req 2.5). A driver export shows the driver's name, a trip
 * export the trip number (to users with trips.view).
 */
final class ExportTable
{
    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $user = $this->request->user();
        $exports = app(ExportService::class);
        $driverName = fn (string $column) => Driver::withTrashed()->select($column)
            ->whereRaw('drivers.id = json_extract(exports.parameters, ?)', ['$.driver_id']);

        $query = Export::query()
            ->select(['id', 'company_id', 'requested_by_user_id', 'type', 'parameters', 'status', 'file_id', 'error', 'expires_at', 'created_at'])
            ->addSelect(['driver_first_name' => $driverName('first_name'), 'driver_last_name' => $driverName('last_name')])
            ->addSelect(['trip_number' => Trip::withTrashed()->select('trip_number')->whereRaw('trips.id = CAST(json_extract(exports.parameters, ?) AS UNSIGNED)', ['$.trip_id'])])
            ->with(['file:id,size_bytes', 'requestedBy:id,name']);

        return DataTables::eloquent($query)
            ->addColumn('type_label', fn (Export $export) => e($export->typeLabel()))
            ->addColumn('scope', fn (Export $export) => e(match ($export->type) {
                'zip_driver' => trim("{$export->getAttribute('driver_last_name')}, {$export->getAttribute('driver_first_name')}", ' ,').' (#'.$export->driverId().')',
                // The trip number only for users who may see trips
                'zip_trip' => $user?->can('trips.view') ? 'Trip #'.$export->getAttribute('trip_number') : 'Trip',
                default => $export->withTrips() ? 'Whole company, with trips' : 'Whole company',
            }))
            ->addColumn('requested', fn (Export $export) => e(trim($export->created_at?->format('M d, Y g:i A').($export->requestedBy ? ' · '.$export->requestedBy->name : ''))))
            ->addColumn('status_badge', fn (Export $export) => view('partials.ui.status-badge', ['status' => $export->status])->render()
                .($export->status === 'failed' && $export->error ? '<p class="mt-1 text-xs text-muted-foreground">'.e($export->error).'</p>' : ''))
            ->addColumn('size', fn (Export $export) => $export->file ? FileSize::format($export->file->size_bytes) : '—')
            ->addColumn('expires', fn (Export $export) => $export->status === 'ready' ? e($export->expires_at?->format('M d, Y')) : '—')
            ->addColumn('action', fn (Export $export) => $user !== null && $exports->canDownload($user, $export)
                ? '<a href="'.e(route('exports.download', $export)).'" class="btn" data-variant="outline" data-size="sm">'.svg('lucide-download', 'size-4', ['aria-hidden' => 'true'])->toHtml().' Download</a>'
                : '')
            ->orderColumn('requested', 'created_at $1')
            ->filter(fn () => null)
            ->rawColumns(['status_badge', 'action'])
            ->make(true);
    }
}
