@extends('layouts.site')

@section('title', $posts->currentPage() > 1 ? $page->title.' (page '.$posts->currentPage().')' : $page->title)
@section('description', (string) $page->summary)

@section('content')
    <x-site.page-hero :title="$page->title" :summary="$page->summary" />

    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        @if (filled($page->body) && $posts->onFirstPage())
            <x-site.prose :html="$page->body" class="mx-auto mb-12 max-w-3xl prose-base" />
        @endif

        @if ($posts->isEmpty())
            <p class="py-16 text-center text-muted-foreground">No articles yet. Check back soon.</p>
        @else
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    @include('blog.partials.card')
                @endforeach
            </div>
            <div class="mt-12">{{ $posts->links() }}</div>
        @endif
    </div>
@endsection
