<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformUser;
use App\Services\Platform\PlanCatalogueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The feature registry (05 §2). Rows come from the Feature enum; the platform sets availability.
 */
class FeatureController extends Controller
{
    public function index(): View
    {
        return view('platform.features.index', [
            'features' => DB::table('features')->orderBy('group')->orderBy('sort_order')->get(),
            'availability' => PlanCatalogueService::AVAILABILITY,
        ]);
    }

    public function update(Request $request, string $feature, PlanCatalogueService $catalogue): RedirectResponse
    {
        $validated = $request->validate(['availability' => ['required', Rule::in(PlanCatalogueService::AVAILABILITY)]]);

        /** @var PlatformUser $actor */
        $actor = $request->user('platform');
        $catalogue->setAvailability($actor, $feature, $validated['availability']);

        return back()->with('success', 'Feature availability saved.');
    }
}
