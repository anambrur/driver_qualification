{{-- Public applicant entry pages (landing, start, OTP, status, complete): company bar, one card, help. --}}
@props(['company', 'heading', 'intro' => null, 'icon' => null, 'tone' => null, 'width' => 'max-w-xl'])
<div class="min-h-dvh bg-muted/40 px-4 py-6 sm:py-10">
    <div class="mx-auto w-full max-w-3xl">
        <header class="mb-6 flex items-center justify-between gap-4 sm:mb-10">
            <p class="min-w-0 truncate text-lg font-semibold tracking-tight">{{ $company->company_name }}</p>
            <x-ui.theme-toggle />
        </header>

        <x-ui.card class="mx-auto {{ $width }}">
            <div class="grid gap-6 py-2 text-center">
                @if ($icon)
                    <span @class([
                        'mx-auto flex size-14 items-center justify-center rounded-full',
                        'bg-success/15 text-success' => $tone === 'success',
                        'bg-primary/10 text-primary' => $tone !== 'success',
                    ])>
                        <x-ui.icon :name="$icon" class="size-7" />
                    </span>
                @endif
                <div class="grid gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight">{{ $heading }}</h1>
                    @if ($intro)
                        <p class="text-muted-foreground">{{ $intro }}</p>
                    @endif
                </div>
                <div class="text-left">
                    {{ $slot }}
                </div>
            </div>
        </x-ui.card>

        @if ($company->phone || $company->email)
            <div class="mt-8 grid justify-items-center gap-3 text-sm text-muted-foreground">
                <p>Need help?</p>
                <div class="flex flex-col items-center gap-x-6 gap-y-2 sm:flex-row">
                    @if ($company->phone)
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $company->phone) }}" class="inline-flex items-center gap-2 font-medium text-primary underline-offset-4 hover:underline">
                            <x-ui.icon name="phone" class="size-4" /> {{ $company->phone }}
                        </a>
                    @endif
                    @if ($company->email)
                        <a href="mailto:{{ $company->email }}" class="inline-flex items-center gap-2 font-medium text-primary underline-offset-4 hover:underline">
                            <x-ui.icon name="mail" class="size-4" /> {{ $company->email }}
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <p class="mt-6 inline-flex w-full items-center justify-center gap-2 text-center text-xs text-muted-foreground">
            <x-ui.icon name="shield-check" class="size-3.5" /> Secure application process. Your information is protected.
        </p>
    </div>
</div>
