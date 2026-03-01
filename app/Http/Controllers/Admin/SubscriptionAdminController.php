<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Payment;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionAdminController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService)
    {
    }

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(): View
    {
        $stats = [
            'total_active'    => Subscription::whereIn('status', ['active', 'trial'])->count(),
            'total_expired'   => Subscription::where('status', 'expired')->count(),
            'total_cancelled' => Subscription::where('status', 'cancelled')->count(),
            'total_revenue'   => Payment::where('status', 'paid')->sum('amount'),
            'monthly_revenue' => Payment::where('status', 'paid')
                                     ->whereMonth('paid_at', now()->month)
                                     ->whereYear('paid_at', now()->year)
                                     ->sum('amount'),
            'expiring_soon'   => Subscription::expiringSoon(7)->count(),
            'new_this_month'  => Subscription::whereMonth('created_at', now()->month)->count(),
        ];

        $recentSubscriptions = Subscription::with(['user', 'plan'])
            ->latest()
            ->limit(10)
            ->get();

        $plans = Plan::withCount(['subscriptions' => function ($q) {
            $q->whereIn('status', ['active', 'trial']);
        }])->get();

        return view('admin.subscriptions.dashboard', compact('stats', 'recentSubscriptions', 'plans'));
    }

    // ─── All Subscriptions ────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $query = Subscription::with(['user', 'plan']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $subscriptions = $query->latest()->paginate(20);
        $plans = Plan::active()->get();

        return view('admin.subscriptions.index', compact('subscriptions', 'plans'));
    }

    /**
     * Show subscription details for a user.
     */
    public function show(User $user): View
    {
        $subscriptions = $user->subscriptions()->with('plan')->latest()->get();
        $payments      = $user->payments()->with('plan')->latest()->get();
        $plans         = Plan::active()->get();

        return view('admin.subscriptions.show', compact('user', 'subscriptions', 'payments', 'plans'));
    }

    /**
     * Grant a subscription manually.
     */
    public function grant(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'plan_id'         => 'required|exists:plans,id',
            'notes'           => 'nullable|string|max:500',
            'custom_end_date' => 'nullable|date|after:today',
        ]);

        $plan       = Plan::findOrFail($request->plan_id);
        $customDate = $request->custom_end_date ? Carbon::parse($request->custom_end_date) : null;

        $this->subscriptionService->grantManually($user, $plan, $request->notes ?? '', $customDate);

        return redirect()->route('admin.subscriptions.show', $user)
            ->with('success', "Subscription granted to {$user->name}.");
    }

    /**
     * Manually expire a subscription.
     */
    public function expire(Subscription $subscription): RedirectResponse
    {
        $subscription->update(['status' => 'expired', 'ends_at' => now()]);

        return back()->with('success', 'Subscription expired.');
    }

    /**
     * Suspend a user's subscription.
     */
    public function suspend(Subscription $subscription): RedirectResponse
    {
        $this->subscriptionService->suspend($subscription);

        return back()->with('success', 'Subscription suspended.');
    }

    /**
     * Reactivate a suspended/expired subscription.
     */
    public function reactivate(Subscription $subscription): RedirectResponse
    {
        $this->subscriptionService->reactivate($subscription);

        return back()->with('success', 'Subscription reactivated.');
    }

    // ─── Plan Management ──────────────────────────────────────────────────────

    public function plansIndex(): View
    {
        $plans = Plan::withCount('subscriptions')->ordered()->get();
        return view('admin.subscriptions.plans', compact('plans'));
    }

    public function createPlan(): View
    {
        return view('admin.plans.create');
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'slug'          => 'required|string|max:100|unique:plans',
            'description'   => 'nullable|string',
            'price'         => 'required|numeric|min:0',
            'currency'      => 'required|string|size:3',
            'billing_cycle' => 'required|in:monthly,yearly,lifetime,trial',
            'duration_days' => 'required|integer|min:1',
            'trial_days'    => 'nullable|integer|min:0',
            'max_users'     => 'nullable|integer|min:1',
            'features'      => 'nullable|array',
            'is_active'     => 'boolean',
            'is_featured'   => 'boolean',
            'sort_order'    => 'integer|min:0',
        ]);

        Plan::create($validated);

        return redirect()->route('admin.plans.index')->with('success', 'Plan created successfully.');
    }

    public function editPlan(Plan $plan): View
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function updatePlan(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'description'   => 'nullable|string',
            'price'         => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly,lifetime,trial',
            'duration_days' => 'required|integer|min:1',
            'max_users'     => 'nullable|integer|min:1',
            'features'      => 'nullable|array',
            'is_active'     => 'boolean',
            'is_featured'   => 'boolean',
            'sort_order'    => 'integer|min:0',
        ]);

        $plan->update($validated);

        return redirect()->route('admin.plans.index')->with('success', 'Plan updated.');
    }

    // ─── Payments ─────────────────────────────────────────────────────────────

    public function payments(Request $request): View
    {
        $payments = Payment::with(['user', 'plan'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, function ($q) use ($request) {
                $q->where('invoice_number', 'like', "%{$request->search}%")
                  ->orWhereHas('user', fn($u) => $u->where('email', 'like', "%{$request->search}%"));
            })
            ->latest()
            ->paginate(20);

        return view('admin.subscriptions.payments', compact('payments'));
    }

    /**
     * Mark a pending payment as paid.
     */
    public function markPaid(Request $request, Payment $payment): RedirectResponse
    {
        $request->validate(['transaction_id' => 'nullable|string']);

        $payment->update([
            'status'         => 'paid',
            'paid_at'        => now(),
            'transaction_id' => $request->transaction_id ?? $payment->transaction_id,
        ]);

        // Activate subscription if pending
        $sub = $payment->subscription;
        if ($sub && $sub->status !== 'active') {
            $sub->update(['status' => 'active']);
        }

        return back()->with('success', 'Payment marked as paid and subscription activated.');
    }
}
