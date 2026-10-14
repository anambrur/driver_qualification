<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\GrantSubscriptionRequest;
use App\Http\Requests\Billing\MoveToPriceRequest;
use App\Models\Company;
use App\Models\PlanPrice;
use App\Services\Billing\SubscriptionAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Platform billing actions on a company (07 §7). The /platform screens arrive in WP8.
 */
class CompanySubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionAdminService $admin,
    ) {}

    public function grant(GrantSubscriptionRequest $request, Company $company): RedirectResponse
    {
        return $this->run(fn () => $this->admin->grant(
            $request->user('platform'),
            $company,
            PlanPrice::query()->findOrFail($request->validated('plan_price_id')),
            Carbon::parse($request->validated('ends_at'))->endOfDay(),
        ), 'Complimentary subscription granted.');
    }

    public function expire(Request $request, Company $company): RedirectResponse
    {
        return $this->run(fn () => $this->admin->expireNow($request->user('platform'), $company), 'Subscription expired.');
    }

    public function suspend(Request $request, Company $company): RedirectResponse
    {
        return $this->run(fn () => $this->admin->suspend($request->user('platform'), $company), 'Subscription will end at period end.');
    }

    public function reactivate(Request $request, Company $company): RedirectResponse
    {
        return $this->run(fn () => $this->admin->reactivate($request->user('platform'), $company), 'Subscription resumed.');
    }

    public function movePrice(MoveToPriceRequest $request, Company $company): RedirectResponse
    {
        return $this->run(fn () => $this->admin->moveToPrice(
            $request->user('platform'),
            $company,
            PlanPrice::query()->findOrFail($request->validated('plan_price_id')),
        ), 'Subscription moved to the new price.');
    }

    /**
     * Rule failures are shown; Stripe and other errors are logged, never echoed to the client.
     */
    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();

            return back()->with('success', $success);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::warning('Platform subscription action failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'The action failed. Please try again.');
        }
    }
}
