<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\LandingPageRequest;
use App\Models\PlatformUser;
use App\Services\Platform\SiteContentService;
use App\Support\Landing;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Homepage copy: hero, stats, features, steps, section headings and call to action. The footer has its own editor.
 */
class LandingPageController extends Controller
{
    public function __construct(private SiteContentService $content) {}

    public function edit(): View
    {
        return view('platform.content.landing', ['landing' => Landing::content()]);
    }

    public function update(LandingPageRequest $request): RedirectResponse
    {
        /** @var PlatformUser $actor */
        $actor = $request->user('platform');
        $this->content->updateLanding($actor, $request->landing());

        return back()->with('success', 'Landing page saved.');
    }
}
