{{-- The heading band at the top of a public page. --}}
@props(['title', 'summary' => null, 'eyebrow' => null])
<section class="relative overflow-hidden border-b border-border bg-gradient-to-br from-background via-[color-mix(in_oklch,var(--brand)_7%,var(--background))] to-[color-mix(in_oklch,var(--highlight)_10%,var(--background))] pt-32 pb-16 lg:pt-40 lg:pb-20">
    <div class="pointer-events-none absolute -top-24 right-0 size-96 rounded-full bg-primary/10 blur-3xl"></div>
    <div class="relative mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
        @if ($eyebrow)
            <p class="mb-4 inline-flex items-center rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary">{{ $eyebrow }}</p>
        @endif
        <h1 class="text-4xl font-extrabold tracking-tight text-balance sm:text-5xl">{{ $title }}</h1>
        @if (filled($summary))
            <p class="mx-auto mt-5 max-w-2xl text-lg text-muted-foreground text-pretty">{{ $summary }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
