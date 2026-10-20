{{-- One post in a grid: $post. --}}
<article class="group relative flex flex-col overflow-hidden rounded-3xl border border-border bg-card shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
    <div class="aspect-[1200/630] overflow-hidden bg-gradient-to-br from-primary/15 to-highlight/20">
        @if ($post->cover_image)
            <img src="{{ asset('storage/'.$post->cover_image) }}" alt="" width="1200" height="630" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="flex size-full items-center justify-center text-primary/60"><x-ui.icon name="newspaper" class="size-12" /></div>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-6">
        <time datetime="{{ $post->published_at->toDateString() }}" class="text-xs font-semibold uppercase tracking-wider text-primary">{{ $post->published_at->format('M j, Y') }}</time>
        <h2 class="mt-2 text-lg font-bold leading-snug">
            <a href="{{ route('blog.show', $post) }}" class="after:absolute after:inset-0 focus-visible:outline-none group-hover:text-primary">{{ $post->title }}</a>
        </h2>
        @if (filled($post->excerpt))
            <p class="mt-3 line-clamp-3 text-sm text-muted-foreground">{{ $post->excerpt }}</p>
        @endif
        <span class="mt-auto inline-flex items-center gap-1 pt-5 text-sm font-semibold text-primary">Read article <x-ui.icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" /></span>
    </div>
</article>
