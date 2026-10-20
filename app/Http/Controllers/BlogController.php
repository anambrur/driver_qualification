<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use Illuminate\View\View;

/**
 * The public blog. Drafts and posts scheduled for later are 404.
 */
class BlogController extends Controller
{
    public function index(): View
    {
        $page = Page::query()->where('slug', 'blog')->where('is_published', true)->firstOrFail();

        return view('blog.index', [
            'page' => $page,
            'posts' => Post::query()->published()->latestFirst()->paginate(9),
        ]);
    }

    public function show(Post $post): View
    {
        abort_unless($post->isPublished() && isset(Page::published()['blog']), 404);

        return view('blog.show', [
            'post' => $post->load('author:id,name'),
            'more' => Post::query()->published()->whereKeyNot($post->getKey())->latestFirst()->limit(3)->get(),
        ]);
    }
}
