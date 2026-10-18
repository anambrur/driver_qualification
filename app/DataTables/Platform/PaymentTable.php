<?php

namespace App\DataTables\Platform;

use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side payment list (platform/subscriptions/payments, 07 §7). Search matches the company name.
 */
final class PaymentTable
{
    public const STATUSES = ['paid', 'failed', 'refunded', 'pending'];

    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $query = Payment::query()
            ->select(['payments.id', 'company_id', 'plan_id', 'amount', 'currency', 'status', 'billing_reason', 'paid_at'])
            ->with(['company:id,company_name', 'plan:id,name']);

        $status = $this->request->input('status');
        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        $term = trim((string) data_get($this->request->input('search'), 'value', ''));
        if ($term !== '') {
            $query->whereHas('company', fn ($q) => $q->where('company_name', 'like', '%'.addcslashes($term, '%_\\').'%'));
        }

        return DataTables::eloquent($query)
            ->addColumn('paid_at_formatted', fn (Payment $payment) => $payment->paid_at?->format('M j, Y') ?? '—')
            ->addColumn('company', fn (Payment $payment) => view('platform.partials.cell', [
                'title' => $payment->company->company_name, 'href' => route('platform.companies.show', $payment->company),
            ])->render())
            ->addColumn('plan', fn (Payment $payment) => $payment->plan_id ? $payment->plan->name : '—')
            ->editColumn('amount', fn (Payment $payment) => $payment->amount.' '.$payment->currency)
            ->addColumn('status', fn (Payment $payment) => view('partials.ui.status-badge', ['status' => $payment->status])->render())
            ->editColumn('billing_reason', fn (Payment $payment) => str_replace('_', ' ', (string) $payment->billing_reason) ?: '—')
            ->orderColumn('paid_at_formatted', 'paid_at $1')
            ->filter(fn () => null) // searched above
            ->rawColumns(['company', 'status'])
            ->make(true);
    }
}
