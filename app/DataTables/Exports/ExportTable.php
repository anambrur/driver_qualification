<?php

namespace App\DataTables\Exports;

use App\Models\Driver;
use App\Models\Export;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * The company's exports, newest first (req 2.5). A driver export shows the driver's name.
 */
final class ExportTable
{
    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $canDownload = (bool) $this->request->user()?->can('exports.download');
        $driverName = fn (string $column) => Driver::withTrashed()->select($column)
            ->whereRaw('drivers.id = json_extract(exports.parameters, ?)', ['$.driver_id']);

        $query = Export::query()
            ->select(['id', 'company_id', 'requested_by_user_id', 'type', 'parameters', 'status', 'file_id', 'error', 'expires_at', 'created_at'])
            ->addSelect(['driver_first_name' => $driverName('first_name'), 'driver_last_name' => $driverName('last_name')])
            ->with(['file:id,size_bytes', 'requestedBy:id,name']);

        return DataTables::eloquent($query)
            ->addColumn('type_label', fn (Export $export) => e($export->typeLabel()))
            ->addColumn('scope', fn (Export $export) => e($export->type === 'zip_driver'
                ? trim("{$export->getAttribute('driver_last_name')}, {$export->getAttribute('driver_first_name')}", ' ,').' (#'.$export->driverId().')'
                : 'Whole company'))
            ->addColumn('requested', fn (Export $export) => e(trim($export->created_at?->format('M d, Y g:i A').($export->requestedBy ? ' · '.$export->requestedBy->name : ''))))
            ->addColumn('status_badge', fn (Export $export) => view('partials.ui.status-badge', ['status' => $export->status])->render()
                .($export->status === 'failed' && $export->error ? '<p class="mt-1 text-xs text-muted-foreground">'.e($export->error).'</p>' : ''))
            ->addColumn('size', fn (Export $export) => $export->file ? self::size($export->file->size_bytes) : '—')
            ->addColumn('expires', fn (Export $export) => $export->status === 'ready' ? e($export->expires_at?->format('M d, Y')) : '—')
            ->addColumn('action', fn (Export $export) => $canDownload && $export->isDownloadable()
                ? '<a href="'.e(route('exports.download', $export)).'" class="btn" data-variant="outline" data-size="sm">'.svg('lucide-download', 'size-4', ['aria-hidden' => 'true'])->toHtml().' Download</a>'
                : '')
            ->orderColumn('requested', 'created_at $1')
            ->filter(fn () => null)
            ->rawColumns(['status_badge', 'action'])
            ->make(true);
    }

    /**
     * 1.2 MB, as in DocumentTable (Number::fileSize needs intl, PO-20).
     */
    private static function size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return number_format($bytes / 1024 ** $power, $power === 0 ? 0 : 1).' '.$units[$power];
    }
}
