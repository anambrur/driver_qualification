<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\FooterRequest;
use App\Models\Page;
use App\Models\PlatformUser;
use App\Services\Platform\SitePageService;
use App\Support\Footer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The public site footer: brand, contact, social links, link columns, trust badges, call to action
 * and copyright line.
 */
class FooterController extends Controller
{
    public function __construct(private SitePageService $pages) {}

    public function edit(): View
    {
        return view('platform.content.footer', [
            'footer' => Footer::content(),
            'pages' => Page::query()->orderBy('id')->get(['id', 'slug', 'title', 'is_published']),
        ]);
    }

    public function update(FooterRequest $request): RedirectResponse
    {
        /** @var PlatformUser $actor */
        $actor = $request->user('platform');
        $this->pages->updateFooter($actor, $request->footer());

        return back()->with('success', 'Footer saved.');
    }
}
