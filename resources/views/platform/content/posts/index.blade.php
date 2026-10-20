@extends('layouts.platform')

@section('title', 'Blog posts')

@section('content')
    <x-ui.page-header title="Blog posts" description="Articles on the public blog. A post goes live on its publish date; without one it stays a draft.">
        <x-slot:actions>
            <x-ui.button variant="outline" icon="file-text" :href="route('platform.content.pages.edit', 'blog')">Blog intro</x-ui.button>
            <x-ui.button icon="plus" :href="route('platform.content.posts.create')">New post</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($posts->isEmpty())
        <x-ui.empty-state icon="newspaper" title="No posts yet" description="The blog shows a short note until the first post is published.">
            <x-ui.button icon="plus" size="sm" :href="route('platform.content.posts.create')">New post</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.card flush class="py-0!">
            <x-ui.table>
                <thead>
                    <tr><th>Post</th><th>Status</th><th>Publish date</th><th>Author</th></tr>
                </thead>
                <tbody>
                    @foreach ($posts as $post)
                        <tr>
                            <td data-label="Post" data-primary>
                                @include('platform.partials.cell', [
                                    'title' => $post->title,
                                    'href' => route('platform.content.posts.edit', $post->id),
                                    'sub' => '/blog/'.$post->slug,
                                ])
                            </td>
                            <td data-label="Status">
                                @if ($post->isPublished())
                                    <span class="badge" data-variant="secondary">Published</span>
                                @elseif ($post->published_at)
                                    <span class="badge" data-variant="outline">Scheduled</span>
                                @else
                                    <span class="badge" data-variant="outline">Draft</span>
                                @endif
                            </td>
                            <td data-label="Publish date" class="text-muted-foreground">{{ $post->published_at?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td data-label="Author" class="text-muted-foreground">{{ $post->author?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        </x-ui.card>
        <div class="mt-4">{{ $posts->links() }}</div>
    @endif
@endsection
