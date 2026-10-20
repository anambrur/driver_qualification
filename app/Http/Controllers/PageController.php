<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

/**
 * Public site pages: About, Careers, Contact and the legal pages. Unpublished pages are 404.
 */
class PageController extends Controller
{
    public function show(Page $page): View
    {
        abort_unless($page->is_published && $page->slug !== 'blog', 404);

        return view($page->slug === 'contact' ? 'pages.contact' : 'pages.show', compact('page'));
    }
}
