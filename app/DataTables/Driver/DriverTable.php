<?php

namespace App\DataTables\Driver;

use App\Models\Driver;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side driver list (admin/driver). The Driver model's company scope keeps it tenant-bound.
 */
final class DriverTable
{
    /**
     * DataTables column index → sort expression. Anything else sorts by id (DRV-07 whitelist).
     * 0 photo, 1 full_name, 2 email, 3 status, 4 state, 5 license, 6 medical, 7 hired_at, 8 action
     */
    private const ORDERABLE = [
        1 => ['first_name', 'last_name'],
        2 => ['email'],
        3 => [Driver::STATUS_SQL],
        4 => ['state_code'],
        6 => 'medical',
        7 => ['hired_at'],
    ];

    private const SEARCHABLE = ['first_name', 'last_name', 'middle_name', 'email', 'main_phone', 'state_code'];

    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $query = Driver::query()
            ->select([
                'drivers.id', 'drivers.company_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'email',
                'state_code', 'hired_at', 'photo_file_id', 'employment_status', 'application_status',
            ])
            ->with(['currentLicense:id,driver_id,expires', 'medicalCertificate:id,driver_id,document_type_id,expiry_date'])
            ->listed();

        $status = (string) $this->request->input('status', '');
        if ($status !== '' && $status !== 'all') {
            $query->withStatus($status);
        }

        return DataTables::eloquent($query)
            ->addColumn('photo', fn (Driver $driver) => view('partials.ui.avatar', [
                'name' => trim($driver->first_name.' '.$driver->last_name),
                'src' => $driver->photo_file_id ? route('admin.driver.file', [$driver->id, 'photo']) : null,
                'size' => 'lg',
            ])->render())
            ->addColumn('full_name', fn (Driver $driver) => trim(implode(' ', array_filter([
                $driver->first_name, $driver->middle_name, $driver->last_name, $driver->suffix,
            ]))))
            ->addColumn('state', fn (Driver $driver) => $driver->state_code ?? '—')
            ->editColumn('hired_at', fn (Driver $driver) => $driver->hired_at instanceof DateTimeInterface ? $driver->hired_at->format('Y-m-d') : 'Not hired')
            ->addColumn('license_expiration_date', fn (Driver $driver) => $driver->currentLicense?->expires?->toDateString() ?? '—')
            ->addColumn('medical_certificate_expiration_date', fn (Driver $driver) => $driver->medicalCertificate?->expiry_date?->toDateString() ?? '—')
            ->addColumn('status', fn (Driver $driver) => view('partials.ui.status-badge', ['status' => $driver->status])->render())
            ->addColumn('action', fn (Driver $driver) => view('admin.driver.partials.row-actions', ['driver' => $driver])->render())
            ->rawColumns(['photo', 'status', 'action'])
            ->filter(fn (Builder $query) => $this->search($query))
            ->order(fn (Builder $query) => $this->order($query))
            ->make(true);
    }

    private function search(Builder $query): void
    {
        $term = trim((string) data_get($this->request->input('search'), 'value', ''));

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            foreach (self::SEARCHABLE as $column) {
                $q->orWhere($column, 'like', '%'.$term.'%');
            }
        });
    }

    private function order(Builder $query): void
    {
        $order = $this->request->input('order.0');
        $column = is_array($order) ? (int) ($order['column'] ?? -1) : -1;
        $direction = is_array($order) && strtolower((string) ($order['dir'] ?? '')) === 'desc' ? 'desc' : 'asc';
        $expression = self::ORDERABLE[$column] ?? null;

        if ($expression === null) {
            $query->orderBy('drivers.id', is_array($order) ? $direction : 'desc');

            return;
        }

        if ($expression === 'medical') {
            $query->orderBy(Driver::medicalCertificateExpirySubquery(), $direction);

            return;
        }

        foreach ($expression as $sql) {
            str_contains($sql, ' ') ? $query->orderByRaw($sql.' '.$direction) : $query->orderBy($sql, $direction);
        }
    }
}
