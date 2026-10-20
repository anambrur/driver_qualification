<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PageRequest;
use App\Models\Page;
use App\Models\PlatformUser;
use App\Services\Platform\SitePageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The fixed set of public site pages (About, Careers, legal pages…): edit only.
 */
class PageController extends Controller
{
    public function __construct(private SitePageService $pages) {}

    public function index(): View
    {
        return view('platform.content.pages.index', ['pages' => Page::query()->orderBy('id')->get()]);
    }

    public function edit(Page $page): View
    {
        return view('platform.content.pages.form', compact('page'));
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        /** @var PlatformUser $actor */
        $actor = $request->user('platform');
        $this->pages->updatePage($actor, $page, $request->validated());

        return redirect()->route('platform.content.pages.index')->with('success', "{$page->title} saved.");
    }
}
