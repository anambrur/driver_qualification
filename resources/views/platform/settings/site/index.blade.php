@extends('layouts.platform')

@section('title', 'Site settings')

@php
    $brand = old('primary_color', $setting->primary_color ?: \App\Support\Ui\Theme::DEFAULT_BRAND);
    $highlight = old('accent_color', $setting->accent_color ?: \App\Support\Ui\Theme::DEFAULT_HIGHLIGHT);
@endphp

@section('content')
    <x-ui.page-header title="Site settings" description="Name, branding, search metadata and billing defaults for the whole platform." />

    <form action="{{ route('platform.settings.site.update') }}" method="POST" enctype="multipart/form-data" class="max-w-5xl space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="General">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Site name" name="site_name" required>
                    <x-ui.input name="site_name" :value="$setting->site_name" required maxlength="255" />
                </x-ui.field>
                <x-ui.field label="Contact email" name="email">
                    <x-ui.input type="email" name="email" :value="$setting->email" maxlength="255" placeholder="contact@example.com" />
                </x-ui.field>
                <x-ui.field label="Phone" name="phone">
                    <x-ui.input type="tel" name="phone" :value="$setting->phone" maxlength="50" />
                </x-ui.field>
                <x-ui.field label="Address" name="address">
                    <x-ui.input name="address" :value="$setting->address" maxlength="500" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Logo and favicon">
            <div class="grid gap-6 sm:grid-cols-2">
                <x-ui.field label="Logo" name="logo" hint="PNG, JPG, GIF or WebP, up to 2 MB.">
                    @if ($setting->logo)
                        <div class="flex h-20 items-center justify-center rounded-lg border border-border bg-muted/40 p-3">
                            <img src="{{ asset('storage/'.$setting->logo) }}" alt="Current logo" class="h-12 w-auto object-contain">
                        </div>
                    @endif
                    <x-ui.input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/webp" />
                </x-ui.field>
                <x-ui.field label="Favicon" name="favicon" hint="ICO, PNG, JPG, GIF or WebP, up to 1 MB.">
                    @if ($setting->favicon)
                        <div class="flex h-20 items-center justify-center rounded-lg border border-border bg-muted/40 p-3">
                            <img src="{{ asset('storage/'.$setting->favicon) }}" alt="Current favicon" class="size-10 object-contain">
                        </div>
                    @endif
                    <x-ui.input type="file" name="favicon" accept=".ico,image/png,image/jpeg,image/gif,image/webp" />
                </x-ui.field>
            </div>
        </x-ui.card>

        {{-- Appearance: brand colour, colour mode, dark logo (partials/theme-head) --}}
        <div x-data="{ brand: @js($brand), highlight: @js($highlight) }"
            x-effect="if (/^#[0-9a-fA-F]{6}$/.test(brand) && /^#[0-9a-fA-F]{6}$/.test(highlight)) { document.documentElement.style.setProperty('--brand', brand); document.documentElement.style.setProperty('--highlight', highlight); }">
            <x-ui.card id="appearance" title="Appearance" description="One brand colour styles buttons, links, focus rings and the sidebar across the whole platform. Changes preview live; save to apply for everyone.">
                <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
                    <div class="grid gap-6 sm:grid-cols-2">
                        @foreach (['primary_color' => ['Brand colour', 'brand', \App\Support\Ui\Theme::DEFAULT_BRAND], 'accent_color' => ['Accent colour', 'highlight', \App\Support\Ui\Theme::DEFAULT_HIGHLIGHT]] as $field => [$label, $model, $default])
                            <x-ui.field :label="$label" :name="$field" :hint="$field === 'accent_color' ? 'Charts and highlights.' : 'Buttons, links and active states.'">
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="{{ $model }}" class="h-9 w-12 shrink-0 cursor-pointer rounded-md border border-input bg-transparent p-1" aria-label="{{ $label }} picker">
                                    <x-ui.input :name="$field" x-model="{{ $model }}" pattern="^#[0-9a-fA-F]{6}$" maxlength="7" class="font-mono uppercase" />
                                    <x-ui.button variant="ghost" size="sm" x-on:click="{{ $model }} = '{{ $default }}'">Reset</x-ui.button>
                                </div>
                            </x-ui.field>
                        @endforeach

                        <x-ui.field label="Default colour mode" name="default_color_mode" hint="Users can still switch from the header.">
                            <x-ui.select name="default_color_mode" :value="$setting->default_color_mode ?: 'system'"
                                :options="['system' => 'Follow the device', 'light' => 'Light', 'dark' => 'Dark']" />
                        </x-ui.field>

                        <x-ui.field label="Logo for dark mode" name="logo_dark" hint="PNG, JPG or WebP, up to 2 MB. Falls back to the main logo.">
                            @if ($setting->logo_dark)
                                <img src="{{ asset('storage/'.$setting->logo_dark) }}" alt="Current dark-mode logo" class="dark h-10 w-auto rounded-md bg-background object-contain p-1.5">
                            @endif
                            <x-ui.input type="file" name="logo_dark" accept="image/png,image/jpeg,image/webp,image/gif" />
                        </x-ui.field>
                    </div>

                    <div class="rounded-lg border border-border bg-background p-4" aria-hidden="true">
                        <p class="mb-3 text-xs font-medium text-muted-foreground">Preview</p>
                        <div class="space-y-3">
                            <div class="flex flex-wrap gap-2">
                                <span class="btn" data-size="sm">Primary</span>
                                <span class="btn" data-variant="outline" data-size="sm">Outline</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span class="badge" data-tone="brand">Brand</span>
                                <span class="badge" data-tone="success">Active</span>
                                <span class="badge" data-tone="warning">Pending</span>
                            </div>
                            <a class="text-sm font-medium text-primary underline-offset-4 hover:underline">A link</a>
                            <div class="h-2 overflow-hidden rounded-full bg-muted"><div class="h-full w-2/3 rounded-full bg-highlight"></div></div>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <x-ui.card title="Search and analytics" description="Default page metadata and the Google Analytics id.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Meta title" name="meta_title">
                    <x-ui.input name="meta_title" :value="$setting->meta_title" maxlength="255" />
                </x-ui.field>
                <x-ui.field label="Meta keywords" name="meta_keywords" hint="Comma separated.">
                    <x-ui.input name="meta_keywords" :value="$setting->meta_keywords" maxlength="500" />
                </x-ui.field>
                <x-ui.field label="Meta description" name="meta_description" class="sm:col-span-2">
                    <x-ui.textarea name="meta_description" rows="3" maxlength="1000" :value="$setting->meta_description" />
                </x-ui.field>
                <x-ui.field label="Google Analytics id" name="google_analytics_id" hint="Starts with G-.">
                    <x-ui.input name="google_analytics_id" :value="$setting->google_analytics_id" maxlength="50" placeholder="G-XXXXXXXXXX" class="font-mono" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Legal and contact" description="New demo requests and contact messages are emailed to the support email, or to the general email when it's empty. The public footer has its own editor under Content.">
            <x-slot:action>
                <x-ui.button variant="outline" size="sm" icon="panel-bottom" :href="route('platform.content.footer.edit')">Edit footer</x-ui.button>
            </x-slot:action>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Legal name" name="legal_name" hint="Used in the footer copyright line ({company}).">
                    <x-ui.input name="legal_name" :value="$setting->legal_name" maxlength="255" />
                </x-ui.field>
                <x-ui.field label="Support email" name="support_email">
                    <x-ui.input type="email" name="support_email" :value="$setting->support_email" maxlength="255" />
                </x-ui.field>
                <x-ui.field label="Support phone" name="support_phone">
                    <x-ui.input name="support_phone" :value="$setting->support_phone" maxlength="50" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Billing and support">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Trial plan" name="trial_plan_id" hint="A new company's trial runs on this plan's monthly price.">
                    <x-ui.select name="trial_plan_id" :value="$setting->trial_plan_id" :options="['' => 'None'] + $plans->pluck('name', 'id')->all()" />
                </x-ui.field>
                <x-ui.field label="Trial length (days)" name="trial_days" required>
                    <x-ui.input type="number" name="trial_days" :value="$setting->trial_days" required min="1" max="90" />
                </x-ui.field>
                <x-ui.field label="Grace period after a lapse (days)" name="grace_days" required>
                    <x-ui.input type="number" name="grace_days" :value="$setting->grace_days" required min="0" max="60" />
                </x-ui.field>
                <x-ui.field label="Longest support session (minutes)" name="support_session_max_minutes" required>
                    <x-ui.input type="number" name="support_session_max_minutes" :value="$setting->support_session_max_minutes" required min="15" max="1440" />
                </x-ui.field>
            </div>
        </x-ui.card>

        {{-- Social login is a platform capability (05 §2); the OAuth keys stay in the env --}}
        @inject('socialProviders', 'App\Services\Auth\SocialProviders')
        <x-ui.card title="Sign-in providers" description="Offer Google and Facebook sign-in on the login and register pages. A provider needs its OAuth keys in the server environment.">
            <div class="grid gap-4">
                @foreach ($socialProviders::PROVIDERS as $provider => $label)
                    @php($configured = $socialProviders->isConfigured($provider))
                    <div>
                        <input type="hidden" name="{{ $provider }}_login_enabled" value="0">
                        <x-ui.switch name="{{ $provider }}_login_enabled" :checked="(bool) $setting->{$provider.'_login_enabled'}"
                            :label="'Sign in with '.$label"
                            :description="$configured ? 'Keys are set.' : 'Not configured: set '.strtoupper($provider).'_CLIENT_ID and '.strtoupper($provider).'_CLIENT_SECRET.'" />
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button variant="outline" :href="route('platform.dashboard')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">Save settings</x-ui.button>
        </div>
    </form>

    <div class="mt-6 max-w-5xl">
        @include('settings.partials.reply-email-card', [
            'owner' => $setting,
            'description' => 'Platform emails and notifications are sent from the platform address. When someone replies, the reply goes to this inbox. Company emails use the company’s own email for replies.',
            'fallback' => 'Until then, replies use the server’s default reply address, if one is set.',
            'storeUrl' => route('platform.settings.site.reply-email.store'),
            'destroyUrl' => route('platform.settings.site.reply-email.destroy'),
        ])
    </div>
@endsection
