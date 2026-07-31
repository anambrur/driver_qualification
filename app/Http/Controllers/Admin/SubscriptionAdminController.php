<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\SubscriptionNotificationService;
use App\Services\Stripe\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SubscriptionAdminController extends Controller
{
    public function dashboard(): View
    {
        $totalRevenue = (float) Payment::query()->where('status', 'paid')->sum('amount');
        $monthlyRevenue = (float) Payment::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('amount');

        $stats = [
            'total_active' => Subscription::query()->get()->filter->isAccessible()->count(),
            'total_expired' => Subscription::query()
                ->where(function ($q) {
                    $q->whereNotNull('ends_at')->where('ends_at', '<', now());
                })
                ->orWhere(function ($q) {
                    $q->where('billing_cycle', 'trial')
                        ->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '<', now());
                })
                ->count(),
            'total_cancelled' => Subscription::query()->canceled()->count(),
            'total_revenue' => $totalRevenue,
            'monthly_revenue' => $monthlyRevenue,
            'expiring_soon' => Subscription::query()
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [now(), now()->addDays(7)])
                ->count(),
            'new_this_month' => Subscription::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        $recentSubscriptions = Subscription::with(['user', 'plan'])
            ->latest()
            ->limit(10)
            ->get();

        $plans = Plan::withCount(['subscriptions' => function ($q) {
            $q->whereIn('stripe_status', ['active', 'trialing']);
        }])->ordered()->get();

        return view('admin.subscriptions.dashboard', compact('stats', 'recentSubscriptions', 'plans'));
    }

    public function index(Request $request): View
    {
        $query = Subscription::with(['user', 'plan']);

        if ($request->filled('status')) {
            $query->where('stripe_status', $request->status);
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $subscriptions = $query->latest()->paginate(20)->withQueryString();
        $plans = Plan::ordered()->get();

        return view('admin.subscriptions.index', compact('subscriptions', 'plans'));
    }

    public function payments(Request $request): View
    {
        $query = Payment::with(['user', 'plan', 'subscription'])->latest('paid_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->paginate(25)->withQueryString();
        $totalPaid = (float) Payment::where('status', 'paid')->sum('amount');

        return view('admin.subscriptions.payments', compact('payments', 'totalPaid'));
    }

    public function show(User $user): View
    {
        $subscriptions = $user->subscriptions()->with('plan')->get();
        $payments = $user->payments()->with('plan')->limit(50)->get();
        $plans = Plan::active()->ordered()->get();
        $current = $user->activeSubscription();

        return view('admin.subscriptions.show', compact('user', 'subscriptions', 'payments', 'plans', 'current'));
    }

    public function grant(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'notes' => 'nullable|string|max:500',
            'custom_end_date' => 'nullable|date|after:today',
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        if ($user->hasActiveSubscription()) {
            return redirect()->route('admin.subscriptions.show', $user)
                ->with('error', 'User already has an active subscription. Expire it first.');
        }

        $endsAt = $request->filled('custom_end_date')
            ? Carbon::parse($request->custom_end_date)->endOfDay()
            : now()->addDays((int) ($plan->duration_days ?: 30));

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => null,
            'stripe_status' => $plan->isTrial() ? 'trialing' : 'active',
            'billing_cycle' => $plan->billing_cycle,
            'amount' => $plan->price,
            'currency' => strtoupper($plan->currency ?: 'USD'),
            'trial_ends_at' => $plan->isTrial() ? $endsAt : null,
            'current_period_start' => now(),
            'current_period_end' => $endsAt,
            'cancel_at_period_end' => true,
            'ends_at' => $endsAt,
            'source' => 'admin',
        ]);

        app(SubscriptionNotificationService::class)->sendActivated($subscription);

        return redirect()->route('admin.subscriptions.show', $user)
            ->with('success', "Complimentary subscription granted to {$user->name}.");
    }

    public function expire(Subscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        try {
            $subscriptions->cancelNow($subscription);
            toastr()->success('Subscription cancelled and expired immediately!');
        } catch (\Throwable $e) {
            Log::warning('Admin expire failed', ['error' => $e->getMessage()]);
            toastr()->error('Unable to expire subscription.');
        }

        return back();
    }

    public function suspend(Subscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        try {
            $subscriptions->cancelAtPeriodEnd($subscription);
            toastr()->success('Subscription will end at period end.');
        } catch (\Throwable $e) {
            Log::warning('Admin suspend failed', ['error' => $e->getMessage()]);
            toastr()->error('Unable to suspend subscription.');
        }

        return back();
    }

    public function reactivate(Subscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        try {
            $subscriptions->resume($subscription);
            toastr()->success('Subscription resumed successfully!');
        } catch (\Throwable $e) {
            toastr()->error($e->getMessage() ?: 'Cannot resume this subscription.');
        }

        return back();
    }

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
        $validated = $this->validatePlan($request);
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
        $validated = $this->validatePlan($request, $plan);
        $plan->update($validated);
        toastr()->success('Plan updated successfully!');

        return redirect()->route('admin.plans.index');
    }

    public function deletePlan(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            toastr()->error('Cannot delete a plan that has subscriptions. Deactivate it instead.');

            return back();
        }

        $plan->delete();
        toastr()->success('Plan deleted.');

        return redirect()->route('admin.plans.index');
    }

    public function togglePlan(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);
        toastr()->success($plan->is_active ? 'Plan activated.' : 'Plan deactivated.');

        return back();
    }

    private function validatePlan(Request $request, ?Plan $plan = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => [
                'required',
                'string',
                'max:100',
                \Illuminate\Validation\Rule::unique('plans', 'slug')->ignore($plan?->id),
            ],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'billing_cycle' => 'required|in:monthly,yearly,trial',
            'duration_days' => 'required|integer|min:1',
            'trial_days' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_featured'] = $request->boolean('is_featured', false);
        $validated['trial_days'] = (int) ($validated['trial_days'] ?? 0);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['currency'] = strtoupper($validated['currency']);

        if ($plan) {
            $validated['stripe_plan_id'] = $plan->stripe_plan_id;
            $validated['stripe_price_id'] = $plan->stripe_price_id;
        } else {
            $validated['stripe_plan_id'] = null;
            $validated['stripe_price_id'] = null;
        }

        if ($validated['billing_cycle'] === 'trial') {
            if ((float) $validated['price'] > 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'price' => 'Trial plans must have price 0.',
                ]);
            }
            if ($validated['trial_days'] < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'trial_days' => 'Trial plans require trial_days of at least 1.',
                ]);
            }
        }

        return $validated;
    }
}
