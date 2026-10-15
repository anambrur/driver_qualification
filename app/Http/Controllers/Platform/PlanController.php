<?php

namespace App\Http\Controllers\Platform;

use App\Features\Feature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlanFeaturesRequest;
use App\Http\Requests\Platform\PlanRequest;
use App\Models\Plan;
use App\Models\PlatformUser;
use App\Services\Platform\PlanCatalogueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Plans, their prices and their feature matrix (05, 07 §3).
 */
class PlanController extends Controller
{
    public function __construct(private PlanCatalogueService $catalogue) {}

    public function index(): View
    {
        $plans = Plan::query()->ordered()->with(['prices' => fn ($q) => $q->orderBy('archived_at')->orderBy('interval')])->get();

        return view('platform.plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('platform.plans.form', ['plan' => new Plan(['is_public' => true, 'is_active' => true])]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        $plan = $this->catalogue->createPlan($this->actor($request), $request->planAttributes());

        return redirect()->route('platform.plans.edit', $plan)->with('success', 'Plan created. Add its prices and features.');
    }

    public function edit(Plan $plan): View
    {
        return view('platform.plans.form', [
            'plan' => $plan->load(['prices' => fn ($q) => $q->orderBy('archived_at')->orderBy('interval')]),
            'features' => Feature::cases(),
            'planFeatures' => DB::table('plan_features')->where('plan_id', $plan->id)->get()->keyBy('feature_key'),
        ]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->catalogue->updatePlan($this->actor($request), $plan, $request->planAttributes());

        return back()->with('success', 'Plan updated.');
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        $this->catalogue->deletePlan($this->actor($request), $plan);

        return redirect()->route('platform.plans.index')->with('success', 'Plan deleted.');
    }

    /**
     * Applies immediately to every company on the plan (Q3).
     */
    public function features(PlanFeaturesRequest $request, Plan $plan): RedirectResponse
    {
        $this->catalogue->syncFeatures($this->actor($request), $plan, $request->validated('features'));

        return back()->with('success', 'Plan features saved.');
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
