<?php

namespace App\DataTables\Maintenance;

use App\Models\MaintenanceSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side maintenance schedule list. The MaintenanceSchedule company scope keeps it tenant-bound.
 */
final class MaintenanceScheduleTable
{
    private const TYPES = ['date', 'mileage', 'engine_hours'];

    private const STATUSES = ['active', 'paused', 'completed'];

    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $user = $this->request->user();
        $can = [
            'edit' => (bool) $user?->can('scheduled.edit'),
            'delete' => (bool) $user?->can('scheduled.delete'),
        ];

        $query = MaintenanceSchedule::query()
            ->select(['maintenance_schedules.*'])
            ->with(['vehicle:id,unit_no,year,make,model,odometer,engine_hours', 'maintenanceCategory:id,name']);

        if ($this->request->filled('vehicle_id')) {
            $query->where('vehicle_id', (int) $this->request->input('vehicle_id'));
        }
        if ($this->request->filled('category_id')) {
            $query->where('maintenance_category_id', (int) $this->request->input('category_id'));
        }
        if (in_array($type = (string) $this->request->input('schedule_type'), self::TYPES, true)) {
            $query->where('schedule_type', $type);
        }
        if (in_array($status = (string) $this->request->input('status'), self::STATUSES, true)) {
            $query->where('status', $status);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('schedule_info', fn (MaintenanceSchedule $schedule) => view('admin.maintenance-schedule.partials.schedule-cell', [
                'title' => $schedule->title ?: ($schedule->maintenanceCategory->name ?? 'Maintenance'),
                'type' => $schedule->schedule_type_label,
            ])->render())
            ->addColumn('vehicle_info', fn (MaintenanceSchedule $schedule) => $schedule->vehicle
                ? view('admin.vehicle.partials.pair-cell', ['rows' => [
                    'Unit' => $schedule->vehicle->unit_no,
                    'Model' => trim($schedule->vehicle->year.' '.$schedule->vehicle->make.' '.$schedule->vehicle->model),
                ]])->render()
                : '<span class="badge" data-tone="info">All vehicles</span>')
            ->addColumn('interval', fn (MaintenanceSchedule $schedule) => $schedule->interval_text)
            ->addColumn('next_due', fn (MaintenanceSchedule $schedule) => view('admin.maintenance-schedule.partials.due-cell', [
                'text' => $schedule->next_due_text, 'due' => (bool) $schedule->isDue(),
            ])->render())
            ->addColumn('status', fn (MaintenanceSchedule $schedule) => view('partials.ui.status-badge', ['status' => $schedule->status])->render())
            ->addColumn('created_at_formatted', fn (MaintenanceSchedule $schedule) => $schedule->created_at?->format('M d, Y'))
            ->addColumn('action', fn (MaintenanceSchedule $schedule) => view('admin.maintenance-schedule.partials.row-actions', [
                'id' => $schedule->id, 'name' => $schedule->title ?: ($schedule->maintenanceCategory->name ?? 'this schedule'), 'can' => $can,
            ])->render())
            ->orderColumn('schedule_info', 'title $1')
            ->orderColumn('interval', 'interval_days $1, interval_miles $1, interval_hours $1')
            ->orderColumn('next_due', 'next_due_date $1, next_due_mileage $1, next_due_hours $1')
            ->orderColumn('created_at_formatted', 'maintenance_schedules.created_at $1')
            ->filter(fn () => null)
            ->rawColumns(['schedule_info', 'vehicle_info', 'next_due', 'status', 'action'])
            ->make(true);
    }
}
