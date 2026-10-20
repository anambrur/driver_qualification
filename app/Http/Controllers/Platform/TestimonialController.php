<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\TestimonialRequest;
use App\Models\PlatformUser;
use App\Models\Testimonial;
use App\Services\Platform\SiteContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Homepage testimonials.
 */
class TestimonialController extends Controller
{
    public function __construct(private SiteContentService $content) {}

    public function index(): View
    {
        return view('platform.content.testimonials.index', ['testimonials' => Testimonial::query()->ordered()->get()]);
    }

    public function create(): View
    {
        return view('platform.content.testimonials.form', ['testimonial' => new Testimonial(['rating' => 5, 'is_active' => true])]);
    }

    public function store(TestimonialRequest $request): RedirectResponse
    {
        $this->content->createTestimonial($this->actor($request), $request->testimonialAttributes(), $request->file('avatar'));

        return redirect()->route('platform.content.testimonials.index')->with('success', 'Testimonial added.');
    }

    public function edit(Testimonial $testimonial): View
    {
        return view('platform.content.testimonials.form', compact('testimonial'));
    }

    public function update(TestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $this->content->updateTestimonial(
            $this->actor($request), $testimonial, $request->testimonialAttributes(), $request->file('avatar'), $request->boolean('remove_avatar'),
        );

        return redirect()->route('platform.content.testimonials.index')->with('success', 'Testimonial updated.');
    }

    public function destroy(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $this->content->deleteTestimonial($this->actor($request), $testimonial);

        return redirect()->route('platform.content.testimonials.index')->with('success', 'Testimonial deleted.');
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
