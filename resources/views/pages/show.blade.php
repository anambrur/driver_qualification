@extends('layouts.site')

@section('title', $page->title)
@section('description', (string) $page->summary)

@section('content')
    <x-site.page-hero :title="$page->title" :summary="$page->summary" :eyebrow="$page->isLegal() ? 'Legal' : null">
        @if ($page->isLegal() && $page->updated_at)
            <p class="mt-6 text-sm text-muted-foreground">Last updated <time datetime="{{ $page->updated_at->toDateString() }}">{{ $page->updated_at->format('F j, Y') }}</time></p>
        @endif
    </x-site.page-hero>

    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <x-site.prose :html="$page->body" />
    </div>
@endsection
