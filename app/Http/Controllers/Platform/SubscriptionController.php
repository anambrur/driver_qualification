<?php

namespace App\Http\Controllers\Platform;

use App\DataTables\Platform\PaymentTable;
use App\DataTables\Platform\SubscriptionTable;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Subscription and payment read screens (07 §7). Actions on one company's subscription are in
 * CompanySubscriptionController.
 */
class SubscriptionController extends Controller
{
    public function dashboard(): View
    {
        $paid = Payment::query()->where('status', 'paid');

        return view('platform.subscriptions.dashboard', [
            'byStatus' => Subscription::query()->open()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'bySource' => Subscription::query()->open()->selectRaw('source, COUNT(*) AS total')->groupBy('source')->pluck('total', 'source'),
            'endingSoon' => Subscription::query()->open()->whereBetween('ends_at', [now(), now()->addDays(7)])->count(),
            'revenueTotal' => (float) (clone $paid)->sum('amount'),
            'revenueMonth' => (float) (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'recent' => Subscription::query()->with(['company:id,company_name', 'planPrice.plan:id,name'])->latest()->limit(10)->get(),
        ]);
    }

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return (new SubscriptionTable($request))->json();
        }

        return view('platform.subscriptions.index', [
            'plans' => Plan::query()->ordered()->get(['id', 'name']),
        ]);
    }

    public function payments(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return (new PaymentTable($request))->json();
        }

        return view('platform.subscriptions.payments');
    }
}
