{{-- Google / Facebook sign-in (P1-3): only providers with keys that Platform > Settings enables --}}
@inject('socialProviders', 'App\Services\Auth\SocialProviders')
@php($enabledProviders = $socialProviders->enabled())
@if ($enabledProviders !== [])
    <div class="grid gap-3">
        @foreach ($enabledProviders as $provider => $label)
            <x-ui.button variant="outline" class="w-full" :href="route('auth.social.redirect', $provider)">
                @include('auth.partials.provider-logo', ['provider' => $provider])
                Continue with {{ $label }}
            </x-ui.button>
        @endforeach
    </div>
    <div class="flex items-center gap-3 text-xs uppercase text-muted-foreground">
        <span class="h-px flex-1 bg-border"></span>
        or
        <span class="h-px flex-1 bg-border"></span>
    </div>
@endif
