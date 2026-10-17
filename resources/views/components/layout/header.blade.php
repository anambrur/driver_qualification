{{-- App header. Slot = page context (optional); `user` slot = account menu. --}}
<header class="app-header sticky top-0 z-30 flex h-(--header-height) shrink-0 items-center gap-2 border-b border-border bg-background/85 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/70 sm:px-6">
    <button type="button" class="btn lg:hidden" data-variant="ghost" data-size="icon" x-on:click="sidebarOpen = true"
        aria-controls="app-sidebar" :aria-expanded="sidebarOpen.toString()" aria-label="Open navigation">
        {{ svg('lucide-menu', '', ['aria-hidden' => 'true']) }}
    </button>
    <button type="button" class="btn max-lg:hidden" data-variant="ghost" data-size="icon" x-on:click="collapsed = ! collapsed"
        aria-controls="app-sidebar" :aria-pressed="collapsed.toString()" aria-label="Collapse sidebar">
        {{ svg('lucide-panel-left', '', ['aria-hidden' => 'true']) }}
    </button>

    <div class="min-w-0 flex-1">{{ $slot }}</div>

    <div class="flex items-center gap-1">
        <x-ui.theme-toggle />
        {{ $user ?? '' }}
    </div>
</header>
