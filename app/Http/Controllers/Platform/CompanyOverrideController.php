<?php

namespace App\Http\Controllers\Platform;

use App\Features\Feature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\FeatureOverrideRequest;
use App\Models\Company;
use App\Models\PlatformUser;
use App\Services\Entitlements\FeatureOverrideService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class CompanyOverrideController extends Controller
{
    public function __construct(private FeatureOverrideService $overrides) {}

    public function store(FeatureOverrideRequest $request, Company $company): RedirectResponse
    {
        $feature = Feature::from($request->validated('feature'));
        $unlimited = $request->boolean('is_unlimited');

        try {
            $this->overrides->grant(
                $this->actor($request),
                $company,
                $feature,
                $feature->isLimit() ? null : $request->boolean('enabled'),
                $feature->isLimit() && ! $unlimited && $request->filled('limit_value') ? $request->integer('limit_value') : null,
                $request->validated('reason'),
                $feature->isLimit() && $unlimited,
                endsAt: $request->filled('ends_at') ? Carbon::parse($request->validated('ends_at')) : null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['feature' => $e->getMessage()]);
        }

        return back()->with('success', 'Override saved.');
    }

    public function destroy(FeatureOverrideRequest $request, Company $company): RedirectResponse
    {
        $this->overrides->revoke($this->actor($request), $company, Feature::from($request->validated('feature')), $request->validated('reason'));

        return back()->with('success', 'Override revoked.');
    }

    private function actor(FeatureOverrideRequest $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
