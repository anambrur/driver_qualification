<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlanPriceRequest;
use App\Models\Plan;
use App\Models\PlatformUser;
use App\Services\Platform\PlanCatalogueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlanPriceController extends Controller
{
    public function __construct(private PlanCatalogueService $catalogue) {}

    public function store(PlanPriceRequest $request, Plan $plan): RedirectResponse
    {
        $this->catalogue->addPrice(
            $this->actor($request),
            $plan,
            $request->validated('interval'),
            (string) $request->validated('amount'),
            $request->validated('currency'),
            $request->validated('stripe_price_id'),
        );

        return back()->with('success', 'Price added. The previous price for this interval was archived.');
    }

    public function archive(Request $request, Plan $plan, int $price): RedirectResponse
    {
        $this->catalogue->archivePrice($this->actor($request), $plan->prices()->findOrFail($price));

        return back()->with('success', 'Price archived.');
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
