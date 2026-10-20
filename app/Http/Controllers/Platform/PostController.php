<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PostRequest;
use App\Models\PlatformUser;
use App\Models\Post;
use App\Services\Platform\SitePageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public blog posts.
 */
class PostController extends Controller
{
    public function __construct(private SitePageService $pages) {}

    public function index(): View
    {
        return view('platform.content.posts.index', [
            'posts' => Post::query()->with('author:id,name')->orderByRaw('published_at is null desc')->latestFirst()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('platform.content.posts.form', ['post' => new Post]);
    }

    public function store(PostRequest $request): RedirectResponse
    {
        $this->pages->createPost($this->actor($request), $request->postAttributes(), $request->file('cover'));

        return redirect()->route('platform.content.posts.index')->with('success', 'Post added.');
    }

    public function edit(Post $post): View
    {
        return view('platform.content.posts.form', compact('post'));
    }

    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        $this->pages->updatePost($this->actor($request), $post, $request->postAttributes(), $request->file('cover'), $request->boolean('remove_cover'));

        return redirect()->route('platform.content.posts.index')->with('success', 'Post updated.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->pages->deletePost($this->actor($request), $post);

        return redirect()->route('platform.content.posts.index')->with('success', 'Post deleted.');
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}
