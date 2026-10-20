{{--
    The public site footer (Platform > Content > Footer). $cta shows the sign-up band on top; the
    homepage has its own call to action, so it passes false.
--}}
@props(['cta' => true])
@php
    $footer = \App\Support\Footer::content();
    $columns = \App\Support\Footer::columns($footer);
    $siteName = settings('site_name', config('app.name'));
    $contact = array_filter($footer['contact']);
    $social = array_filter($footer['social'], fn ($row) => isset(\App\Support\Footer::NETWORKS[$row['network'] ?? '']));
    $legal = array_filter([
        'Privacy' => \App\Models\Page::publishedUrl('privacy-policy'),
        'Terms' => \App\Models\Page::publishedUrl('terms-of-service'),
    ]);
    $showCta = $cta && $footer['cta']['enabled'] && Route::has('register');
@endphp
<footer class="bg-sidebar text-sidebar-foreground relative" aria-labelledby="site-footer-heading">
    <h2 id="site-footer-heading" class="sr-only">Footer</h2>

    @if ($showCta)
        <div class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary to-[color-mix(in_oklch,var(--primary)_70%,black)] px-6 py-10 sm:px-10 lg:flex lg:items-center lg:justify-between lg:gap-8">
                <div class="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full bg-highlight/20 blur-3xl"></div>
                <div class="relative max-w-2xl">
                    <p class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $footer['cta']['title'] }}</p>
                    @if (filled($footer['cta']['text']))
                        <p class="mt-2 text-white/80">{{ $footer['cta']['text'] }}</p>
                    @endif
                </div>
                <a href="{{ route('register') }}" class="relative mt-6 inline-flex shrink-0 items-center gap-2 rounded-xl bg-highlight px-6 py-3 text-sm font-semibold text-sidebar shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white lg:mt-0">
                    {{ $footer['cta']['button_label'] }}
                    <x-ui.icon name="arrow-right" class="size-4" />
                </a>
            </div>
        </div>
    @endif

    <div class="mx-auto max-w-7xl px-4 pt-12 pb-6 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-12">
            {{-- Brand and social --}}
            <div class="lg:col-span-4">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-highlight">
                    @if (settings('logo_dark'))
                        <img src="{{ asset('storage/'.settings('logo_dark')) }}" alt="{{ $siteName }}" class="h-9 w-auto max-w-[200px] object-contain">
                    @else
                        <img src="{{ asset('images/logo/logo-icon.svg') }}" alt="" width="32" height="32" class="size-8 shrink-0">
                        <span class="text-lg font-semibold text-white">{{ $siteName }}</span>
                    @endif
                </a>
                @if (filled($footer['brand']['tagline']))
                    <p class="mt-3 max-w-sm text-sm leading-relaxed">{{ $footer['brand']['tagline'] }}</p>
                @endif

                @if ($social)
                    <ul class="mt-4 flex flex-wrap gap-2" aria-label="Social media">
                        @foreach ($social as $row)
                            @php([$networkLabel, $networkIcon] = \App\Support\Footer::NETWORKS[$row['network']])
                            <li>
                                <a href="{{ $row['url'] }}" target="_blank" rel="noopener noreferrer"
                                    class="flex size-9 items-center justify-center rounded-lg bg-sidebar-accent text-white transition-all duration-300 hover:-translate-y-0.5 hover:bg-highlight hover:text-sidebar focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight">
                                    <x-ui.icon :name="$networkIcon" class="size-4" />
                                    <span class="sr-only">{{ $networkLabel }} (opens in a new tab)</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Link columns and contact: accordions on phones, side by side from sm up --}}
            @php($count = count($columns) + ($contact ? 1 : 0))
            @if ($count)
                <nav aria-label="Footer" @class(['grid gap-x-8 sm:grid-cols-2 lg:col-span-8', [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5'][min($count, 5)]])>
                    @foreach ($columns as $column)
                        <details class="group border-b border-sidebar-border sm:border-0" x-data x-init="if (window.matchMedia('(min-width: 640px)').matches) $el.open = true">
                            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between py-3 text-xs font-semibold uppercase tracking-wider text-white sm:pointer-events-none sm:min-h-0 sm:cursor-default sm:py-0 sm:pb-3 [&::-webkit-details-marker]:hidden">
                                {{ $column['heading'] }}
                                <x-ui.icon name="chevron-down" class="size-4 transition-transform group-open:rotate-180 sm:hidden" />
                            </summary>
                            <ul class="space-y-1 pb-4 text-sm sm:space-y-2 sm:pb-6 lg:pb-0">
                                    @foreach ($column['links'] as $link)
                                        <li>
                                            <a href="{{ $link['href'] }}" class="inline-flex min-h-11 items-center rounded transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-highlight sm:min-h-0">{{ $link['label'] }}</a>
                                        </li>
                                    @endforeach
                            </ul>
                        </details>
                    @endforeach

                    @if ($contact)
                        <div class="pt-6 sm:pt-0">
                            <p class="pb-3 text-xs font-semibold uppercase tracking-wider text-white">Contact</p>
                            <ul class="space-y-2 text-sm">
                                @isset($contact['email'])
                                    <li>
                                        <a href="mailto:{{ $contact['email'] }}" class="inline-flex items-center gap-2 break-all transition-colors hover:text-white">
                                            <x-ui.icon name="mail" class="size-4 shrink-0 text-highlight" />{{ $contact['email'] }}
                                        </a>
                                    </li>
                                @endisset
                                @isset($contact['phone'])
                                    <li>
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}" class="inline-flex items-center gap-2 transition-colors hover:text-white">
                                            <x-ui.icon name="phone" class="size-4 shrink-0 text-highlight" />{{ $contact['phone'] }}
                                        </a>
                                    </li>
                                @endisset
                                @isset($contact['address'])
                                    <li class="flex items-start gap-2">
                                        <x-ui.icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-highlight" />
                                        <address class="not-italic whitespace-pre-line">{{ $contact['address'] }}</address>
                                    </li>
                                @endisset
                            </ul>
                        </div>
                    @endif
                </nav>
            @endif
        </div>

        {{-- Bottom bar: copyright, trust badges, legal links --}}
        <div class="mt-10 flex flex-col gap-4 border-t border-sidebar-border pt-6 text-sm lg:flex-row lg:items-center lg:justify-between">
            <p class="shrink-0">{{ \App\Support\Footer::copyright($footer['bottom']['copyright']) }}</p>

            @if ($footer['badges'])
                <ul class="flex flex-wrap gap-2 lg:justify-center" aria-label="Trust and security">
                    @foreach ($footer['badges'] as $badge)
                        <li class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border px-2.5 py-1 text-xs text-white/85">
                            <x-ui.icon :name="$badge['icon']" class="size-3.5 text-highlight" />
                            {{ $badge['label'] }}
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="flex shrink-0 items-center gap-5">
                @foreach ($legal as $label => $url)
                    <a href="{{ $url }}" class="transition-colors hover:text-white">{{ $label }}</a>
                @endforeach
                @if ($footer['bottom']['back_to_top'])
                    <a href="#" x-data x-on:click.prevent="window.scrollTo({ top: 0, behavior: 'smooth' })"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-sidebar-border transition-colors hover:border-highlight hover:text-white">
                        <x-ui.icon name="arrow-up" class="size-4" />
                        <span class="sr-only">Back to top</span>
                    </a>
                @endif
            </div>
        </div>
    </div>
</footer>
