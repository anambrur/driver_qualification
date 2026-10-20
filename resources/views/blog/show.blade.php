@extends('layouts.site')

@php
    $description = $post->meta_description ?: $post->excerpt;
    $cover = $post->cover_image ? asset('storage/'.$post->cover_image) : null;
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post->title,
        'datePublished' => $post->published_at->toAtomString(),
        'dateModified' => $post->updated_at?->toAtomString(),
        'mainEntityOfPage' => route('blog.show', $post),
        'image' => $cover,
        'description' => $description,
        'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author->name] : null,
        'publisher' => ['@type' => 'Organization', 'name' => settings('site_name', config('app.name'))],
    ];
    $schema = array_filter($schema);
@endphp

@section('title', $post->title)
@section('description', (string) $description)
@section('image', (string) $cover)
@section('og_type', 'article')

@section('content')
    <article>
        <x-site.page-hero :title="$post->title" :summary="$post->excerpt">
            <p class="mt-6 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('F j, Y') }}</time>
                @if ($post->author)
                    <span aria-hidden="true">·</span><span>{{ $post->author->name }}</span>
                @endif
            </p>
        </x-site.page-hero>

        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
            @if ($cover)
                <img src="{{ $cover }}" alt="" width="1200" height="630" class="mb-12 aspect-[1200/630] w-full rounded-3xl object-cover shadow-lg">
            @endif
            <x-site.prose :html="$post->body" />

            <a href="{{ route('blog.index') }}" class="mt-12 inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline">
                <x-ui.icon name="arrow-left" class="size-4" /> All articles
            </a>
        </div>
    </article>

    @if ($more->isNotEmpty())
        <section class="border-t border-border bg-muted/40 py-16" aria-labelledby="more-heading">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 id="more-heading" class="mb-8 text-2xl font-bold">More articles</h2>
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($more as $post)
                        @include('blog.partials.card')
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
