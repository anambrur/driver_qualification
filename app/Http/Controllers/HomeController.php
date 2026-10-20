<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Plan;
use App\Models\Testimonial;
use App\Support\Landing;
use Illuminate\View\View;

/**
 * The public homepage. Its copy and lists are managed on Platform > Content.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('welcome', [
            'plans' => Plan::query()->forSale()->get(),
            'landing' => Landing::content(),
            'testimonials' => Testimonial::forHomepage(),
            'faqs' => Faq::forHomepage(),
        ]);
    }
}
