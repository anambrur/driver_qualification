<?php

namespace App\DataTables\Operations;

use App\Models\Shipper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * The company's shippers (req 4.4). Names are not unique, so city and state tell them apart.
 * Status "" lists active and inactive ones; deleted shippers are not listed.
 */
final class ShipperTable
{
    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $canManage = (bool) $this->request->user()?->can('shippers.manage');

        $query = Shipper::query()->select(['id', 'company_id', 'name', 'contact_name', 'phone', 'email', 'address_line1', 'city', 'state_code', 'zip', 'is_active']);

        match ((string) $this->request->input('status', '')) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        $term = trim((string) data_get($this->request->input('search'), 'value', ''));
        if ($term !== '') {
            $query->search($term);
        }

        return DataTables::eloquent($query)
            ->addColumn('shipper', fn (Shipper $shipper) => view('shippers.partials.name-cell', [
                'shipper' => $shipper, 'href' => $canManage ? route('shippers.edit', $shipper) : null,
            ])->render())
            ->addColumn('place', fn (Shipper $shipper) => e(trim($shipper->cityState().' '.$shipper->zip)))
            ->addColumn('contact', fn (Shipper $shipper) => view('shippers.partials.contact-cell', ['shipper' => $shipper])->render())
            ->addColumn('status', fn (Shipper $shipper) => view('partials.ui.status-badge', ['status' => $shipper->is_active ? 'active' : 'inactive'])->render())
            ->addColumn('action', fn (Shipper $shipper) => $canManage ? view('shippers.partials.row-actions', ['shipper' => $shipper])->render() : '')
            ->orderColumn('shipper', 'name $1')
            ->orderColumn('place', 'city $1')
            ->filter(fn () => null) // searched above
            ->rawColumns(['shipper', 'contact', 'status', 'action'])
            ->make(true);
    }
}
