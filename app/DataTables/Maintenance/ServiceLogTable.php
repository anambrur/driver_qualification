<?php

namespace App\DataTables\Maintenance;

use App\Models\ServiceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side service log list. The ServiceLog company scope keeps it tenant-bound.
 */
final class ServiceLogTable
{
    private const STATUSES = ['completed', 'pending', 'cancelled'];

    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $user = $this->request->user();
        $can = [
            'edit' => (bool) $user?->can('maintenance.edit'),
            'delete' => (bool) $user?->can('maintenance.delete'),
        ];

        $query = ServiceLog::query()
            ->select(['service_logs.*'])
            ->with(['vehicle:id,unit_no,vin,year,make,model', 'maintenanceCategories:id,name'])
            ->withCount('documents');

        if ($this->request->filled('vehicle_id')) {
            $query->where('vehicle_id', (int) $this->request->input('vehicle_id'));
        }
        if ($from = $this->date('date_from')) {
            $query->whereDate('service_date', '>=', $from);
        }
        if ($to = $this->date('date_to')) {
            $query->whereDate('service_date', '<=', $to);
        }
        if (in_array($status = (string) $this->request->input('status'), self::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($this->request->filled('category_id')) {
            // Categories are a pivot table (service_log_category), not a JSON column
            $categoryId = (int) $this->request->input('category_id');
            $query->whereHas('maintenanceCategories', fn ($q) => $q->where('maintenance_categories.id', $categoryId));
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('vehicle_info', fn (ServiceLog $log) => view('admin.service-log.partials.vehicle-cell', [
                'vehicle' => $log->vehicle, 'documents' => (int) $log->getAttribute('documents_count'),
            ])->render())
            ->addColumn('service_info', fn (ServiceLog $log) => view('admin.service-log.partials.service-cell', [
                'date' => $log->service_date?->format('M d, Y'), 'categories' => $log->maintenanceCategories->pluck('name'),
            ])->render())
            ->addColumn('metrics', fn (ServiceLog $log) => view('admin.vehicle.partials.pair-cell', ['rows' => array_filter([
                'At service' => number_format((int) $log->odometer_at_service).' mi',
                'Engine hrs' => $log->engine_hours_at_service ? number_format((int) $log->engine_hours_at_service) : null,
                'Current' => number_format((int) $log->current_odometer).' mi',
            ], fn ($value) => $value !== null)])->render())
            ->addColumn('cost', fn (ServiceLog $log) => $log->formatted_total_cost)
            ->addColumn('status', fn (ServiceLog $log) => view('partials.ui.status-badge', ['status' => $log->status])->render())
            ->addColumn('created_at_formatted', fn (ServiceLog $log) => $log->created_at?->format('M d, Y'))
            ->addColumn('action', fn (ServiceLog $log) => view('admin.maintenance-schedule.partials.row-actions', [
                'id' => $log->id, 'name' => 'the service on '.$log->service_date?->format('M d, Y'), 'can' => $can,
            ])->render())
            ->orderColumn('service_info', 'service_date $1')
            ->orderColumn('metrics', 'odometer_at_service $1')
            ->orderColumn('cost', 'total_cost $1')
            ->orderColumn('created_at_formatted', 'service_logs.created_at $1')
            ->filter(fn () => null)
            ->rawColumns(['vehicle_info', 'service_info', 'metrics', 'status', 'action'])
            ->make(true);
    }

    private function date(string $key): ?string
    {
        $value = (string) $this->request->input($key, '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? Carbon::parse($value)->toDateString() : null;
    }
}
