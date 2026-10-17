@props(['title', 'description' => null])
<div {{ $attributes->class('mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0 space-y-1.5">
        {{ $breadcrumbs ?? '' }}
        <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ $title }}</h1>
        @if ($description)
            <p class="text-sm text-muted-foreground">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 *:max-sm:flex-1">{{ $actions }}</div>
    @endisset
</div>
