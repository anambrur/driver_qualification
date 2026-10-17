@props(['title', 'description' => null, 'icon' => 'inbox'])
<div {{ $attributes->class('empty border') }}>
    <header>
        <figure>{{ svg('lucide-'.$icon, '', ['aria-hidden' => 'true']) }}</figure>
        <h3>{{ $title }}</h3>
        @if ($description)
            <p>{{ $description }}</p>
        @endif
    </header>
    @if (trim($slot) !== '')
        <section class="flex-row! justify-center">{{ $slot }}</section>
    @endif
</div>
