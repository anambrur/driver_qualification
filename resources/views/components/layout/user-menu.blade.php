@props(['name', 'email' => null, 'logout'])
<x-ui.dropdown-menu id="user-menu">
    <x-slot:trigger class="btn gap-2 px-1.5" data-variant="ghost" aria-label="Account menu">
        @include('partials.ui.avatar', ['name' => $name, 'size' => 'sm', 'src' => null])
        <span class="hidden max-w-32 truncate text-sm font-medium md:inline">{{ $name }}</span>
        {{ svg('lucide-chevron-down', 'hidden size-4 text-muted-foreground md:block', ['aria-hidden' => 'true']) }}
    </x-slot:trigger>
    <div role="heading" class="grid! gap-0.5 normal-case!">
        <span class="truncate text-sm font-medium text-foreground">{{ $name }}</span>
        @if ($email)
            <span class="truncate text-xs font-normal text-muted-foreground">{{ $email }}</span>
        @endif
    </div>
    <hr role="separator">
    {{ $slot }}
    <hr role="separator">
    <form method="POST" action="{{ $logout }}">
        @csrf
        <button type="submit" role="menuitem" class="w-full">
            {{ svg('lucide-log-out', '', ['aria-hidden' => 'true']) }}
            Log out
        </button>
    </form>
</x-ui.dropdown-menu>
