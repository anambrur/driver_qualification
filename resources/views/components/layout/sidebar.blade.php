{{--
    App sidebar (company + platform shells). Desktop: expanded or collapsed rail (pinned by the
    header toggle). The width never changes on hover; in rail mode labels/children open in a
    fixed flyout over the content. Mobile: off-canvas sheet. Needs x-data with `sidebarOpen`.
--}}
@props(['groups' => [], 'home', 'label' => 'Main navigation'])
@php($theme = \App\Support\Ui\Theme::current())
<div x-show="sidebarOpen" x-transition.opacity x-on:click="sidebarOpen = false" x-cloak
    class="fixed inset-0 z-40 bg-black/50 lg:hidden" aria-hidden="true"></div>

<aside id="app-sidebar" class="app-sidebar" :class="sidebarOpen && 'is-open'"
    x-on:keydown.escape.window="sidebarOpen = false" x-trap.inert.noscroll="sidebarOpen && window.innerWidth < 1024">
    <div class="flex h-(--header-height) shrink-0 items-center gap-2 border-b border-sidebar-border px-4">
        <a href="{{ $home }}" class="sidebar-brand flex min-w-0 items-center gap-2 rounded-md focus-visible:ring-[3px] focus-visible:ring-ring/50 outline-hidden">
            <img src="{{ asset('images/logo/logo-icon.svg') }}" alt="" width="32" height="32" class="size-8 shrink-0" data-sidebar-icon>
            <span data-sidebar-label class="min-w-0">
                @if ($theme['logo'])
                    <img src="{{ $theme['logo'] }}" alt="{{ settings('site_name', config('app.name')) }}" class="h-7 w-auto max-w-40 object-contain dark:hidden">
                    <img src="{{ $theme['logoDark'] }}" alt="{{ settings('site_name', config('app.name')) }}" class="hidden h-7 w-auto max-w-40 object-contain dark:block">
                @else
                    <span class="truncate text-base font-semibold text-foreground">{{ settings('site_name', config('app.name')) }}</span>
                @endif
            </span>
        </a>
        <button type="button" class="btn ml-auto lg:hidden" data-variant="ghost" data-size="icon-sm" x-on:click="sidebarOpen = false" aria-label="Close navigation">
            {{ svg('lucide-x', '', ['aria-hidden' => 'true']) }}
        </button>
    </div>

    <nav aria-label="{{ $label }}" class="sidebar-scroll flex-1 overflow-y-auto overflow-x-hidden px-3 py-4">
        @foreach ($groups as $group)
            <div @class(['mt-6' => ! $loop->first])>
                <h2 class="sidebar-group-label mb-1 px-3 text-xs font-medium text-muted-foreground">{{ $group['label'] }}</h2>
                <ul class="flex flex-col gap-0.5" role="list">
                    @foreach ($group['items'] as $item)
                        @php($navId = 'nav-'.\Illuminate\Support\Str::slug($group['label'].'-'.$item['label']))
                        <li class="sidebar-item relative" x-data="{ open: @js($item['active']), top: 0 }"
                            x-on:mouseenter="top = $el.getBoundingClientRect().top" x-on:focusin="top = $el.getBoundingClientRect().top">
                            @if (! empty($item['children']))
                                <button type="button" class="sidebar-link" @if ($item['active']) data-active @endif
                                    x-on:click="open = ! open" :aria-expanded="open.toString()" aria-controls="{{ $navId }}">
                                    {{ svg('lucide-'.$item['icon'], 'sidebar-icon', ['aria-hidden' => 'true']) }}
                                    <span data-sidebar-label class="truncate">{{ $item['label'] }}</span>
                                    {{ svg('lucide-chevron-down', 'sidebar-chevron ml-auto size-4 shrink-0 transition-transform', [':class' => "open && 'rotate-180'", 'aria-hidden' => 'true']) }}
                                </button>
                                <ul id="{{ $navId }}" class="sidebar-sub" x-show="open" x-collapse @unless ($item['active']) x-cloak @endunless role="list">
                                    @foreach ($item['children'] as $child)
                                        <li>
                                            <a href="{{ $child['href'] }}" class="sidebar-sublink" @if ($child['active']) aria-current="page" @endif>{{ $child['label'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <a href="{{ $item['href'] }}" class="sidebar-link" @if ($item['active']) aria-current="page" data-active @endif>
                                    {{ svg('lucide-'.$item['icon'], 'sidebar-icon', ['aria-hidden' => 'true']) }}
                                    <span data-sidebar-label class="truncate">{{ $item['label'] }}</span>
                                    @if (! empty($item['badge']))
                                        <span data-sidebar-label class="badge ml-auto" data-tone="warning">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            @endif

                            {{-- Rail-mode flyout (desktop, collapsed only) --}}
                            <div class="sidebar-flyout" :style="{ top: top + 'px' }">
                                <div class="rounded-lg border border-border bg-popover p-1 text-popover-foreground shadow-lg">
                                    @if (! empty($item['children']))
                                        <p class="px-2 py-1.5 text-xs font-medium text-muted-foreground">{{ $item['label'] }}</p>
                                        @foreach ($item['children'] as $child)
                                            <a href="{{ $child['href'] }}" class="sidebar-sublink" @if ($child['active']) aria-current="page" @endif>{{ $child['label'] }}</a>
                                        @endforeach
                                    @else
                                        <span class="block px-2 py-1 text-sm font-medium">{{ $item['label'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    {{ $footer ?? '' }}
</aside>
