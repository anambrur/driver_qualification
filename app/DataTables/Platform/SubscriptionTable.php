<?php

namespace App\DataTables\Platform;

use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Server-side subscription list (platform/subscriptions/all, 07 §7). Search matches the company name.
 */
final class SubscriptionTable
{
    public const STATUSES = ['trialing', 'active', 'past_due', 'unpaid', 'canceled', 'incomplete', 'incomplete_expired'];

    public function __construct(private readonly Request $request) {}

    public function json(): JsonResponse
    {
        $query = Subscription::query()
            ->select(['subscriptions.id', 'company_id', 'plan_price_id', 'interval', 'amount', 'currency', 'status', 'source', 'ends_at', 'ended_at', 'subscriptions.created_at'])
            ->with(['company:id,company_name', 'planPrice.plan:id,name']);

        $status = $this->request->input('status');
        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($this->request->filled('plan_id')) {
            $query->whereHas('planPrice', fn ($q) => $q->where('plan_id', $this->request->integer('plan_id')));
        }
        if ($this->request->boolean('open')) {
            $query->open();
        }

        $term = trim((string) data_get($this->request->input('search'), 'value', ''));
        if ($term !== '') {
            $query->whereHas('company', fn ($q) => $q->where('company_name', 'like', '%'.addcslashes($term, '%_\\').'%'));
        }

        return DataTables::eloquent($query)
            ->addColumn('company', fn (Subscription $subscription) => view('platform.partials.cell', [
                'title' => $subscription->company?->company_name,
                'href' => $subscription->company ? route('platform.companies.show', $subscription->company) : null,
            ])->render())
            ->addColumn('plan', fn (Subscription $subscription) => view('platform.partials.cell', [
                'title' => $subscription->planPrice?->plan?->name, 'sub' => $subscription->interval,
            ])->render())
            ->addColumn('status', fn (Subscription $subscription) => view('platform.subscriptions.partials.status-cell', ['subscription' => $subscription])->render())
            ->editColumn('source', fn (Subscription $subscription) => str_replace('_', ' ', (string) $subscription->source))
            ->editColumn('amount', fn (Subscription $subscription) => $subscription->amount.' '.$subscription->currency)
            ->addColumn('created_at_formatted', fn (Subscription $subscription) => $subscription->created_at?->format('M j, Y'))
            ->addColumn('ends_at_formatted', fn (Subscription $subscription) => $subscription->ends_at?->format('M j, Y') ?? '—')
            ->orderColumn('created_at_formatted', 'subscriptions.created_at $1')
            ->orderColumn('ends_at_formatted', 'ends_at $1')
            ->filter(fn () => null) // searched above
            ->rawColumns(['company', 'plan', 'status'])
            ->make(true);
    }
}
