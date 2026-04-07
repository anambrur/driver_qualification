<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionAdminController extends Controller
{
    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(): View
    {
        $stats = [
            'total_active'    => Subscription::query()->active()->count(),
            'total_expired'   => Subscription::query()->canceled()->count(),
            'total_cancelled' => Subscription::query()->canceled()->count(),
            'total_revenue'   => 0, // Stripe API required for accurate invoices
            'monthly_revenue' => 0,
            'expiring_soon'   => Subscription::query()->whereNotNull('ends_at')->whereBetween('ends_at', [now(), now()->addDays(7)])->count(),
            'new_this_month'  => Subscription::whereMonth('created_at', now()->month)->count(),
        ];

        $recentSubscriptions = Subscription::with(['user', 'plan'])
            ->latest()
            ->limit(10)
            ->get();

        $plans = Plan::withCount(['subscriptions' => function ($q) {
            $q->where('stripe_status', 'active');
        }])->get();

        return view('admin.subscriptions.dashboard', compact('stats', 'recentSubscriptions', 'plans'));
    }

    // ─── All Subscriptions ────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $query = Subscription::with(['user', 'plan']);

        if ($request->filled('status')) {
            // map old status logic to stripe_status
            $query->where('stripe_status', $request->status);
        }

        if ($request->filled('plan_id')) {
            $plan = Plan::find($request->plan_id);
            if ($plan) {
                $query->where('stripe_price', $plan->stripe_price_id);
            }
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
        
        try {
            $invoices = $user->hasStripeId() ? $user->invoices() : [];
        } catch (\Exception $e) {
            $invoices = [];
        }

        $plans = Plan::active()->get();

        return view('admin.subscriptions.show', [
            'user' => $user,
            'subscriptions' => $subscriptions,
            'payments' => [], // Removed local payments array, we now rely on $invoices or you can map $invoices
            'invoices' => $invoices,
            'plans' => $plans
        ]);
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

        $plan = Plan::findOrFail($request->plan_id);

        if (!$user->hasStripeId()) {
            $user->createAsStripeCustomer();
        }

        // Cashier allows manual subscription creation
        $builder = $user->newSubscription('default', $plan->stripe_price_id);
        
        if ($request->filled('custom_end_date')) {
            $builder->trialUntil(Carbon::parse($request->custom_end_date));
        }

        $builder->create();

        return redirect()->route('admin.subscriptions.show', $user)
            ->with('success', "Subscription granted to {$user->name}.");
    }

    /**
     * Manually expire a subscription immediately.
     */
    public function expire(Subscription $subscription): RedirectResponse
    {
        if ($subscription->active()) {
            $subscription->cancelNow();
            toastr()->success('Subscription cancelled and expired immediately!');
        } else {
            toastr()->warning('Subscription is not active.');
        }

        return back();
    }

    /**
     * Suspend / Cancel a subscription (at period end).
     */
    public function suspend(Subscription $subscription): RedirectResponse
    {
        if ($subscription->active()) {
            $subscription->cancel();
            toastr()->success('Subscription cancelled at period end!');
        }
        return back();
    }

    /**
     * Reactivate a cancelled subscription.
     */
    public function reactivate(Subscription $subscription): RedirectResponse
    {
        if ($subscription->canceled() && $subscription->onGracePeriod()) {
            $subscription->resume();
            toastr()->success('Subscription resumed successfully!');
        } else {
            toastr()->error('Cannot resume an expired subscription.');
        }
        return back();
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

        toastr()->success('Plan created successfully!');
        return redirect()->route('admin.plans.index');
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

        toastr()->success('Plan updated successfully!');
        return redirect()->route('admin.plans.index');
    }
}
