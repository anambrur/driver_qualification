<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\FaqRequest;
use App\Models\Faq;
use App\Models\PlatformUser;
use App\Services\Platform\SiteContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Homepage FAQ.
 */
class FaqController extends Controller
{
    public function __construct(private SiteContentService $content) {}

    public function index(): View
    {
        return view('platform.content.faqs.index', ['faqs' => Faq::query()->ordered()->get()]);
    }

    public function create(): View
    {
        return view('platform.content.faqs.form', ['faq' => new Faq(['is_active' => true])]);
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        $this->content->createFaq($this->actor($request), $request->faqAttributes());

        return redirect()->route('platform.content.faqs.index')->with('success', 'Question added.');
    }

    public function edit(Faq $faq): View
    {
        return view('platform.content.faqs.form', compact('faq'));
    }

    public function update(FaqRequest $request, Faq $faq): RedirectResponse
    {
        $this->content->updateFaq($this->actor($request), $faq, $request->faqAttributes());

        return redirect()->route('platform.content.faqs.index')->with('success', 'Question updated.');
    }

    public function destroy(Request $request, Faq $faq): RedirectResponse
    {
        $this->content->deleteFaq($this->actor($request), $faq);

        return redirect()->route('platform.content.faqs.index')->with('success', 'Question deleted.');
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
