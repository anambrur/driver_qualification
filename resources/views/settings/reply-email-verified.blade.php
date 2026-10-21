{{-- Result of a signed reply-email link. Private: not whitelisted in config/seo.php, so SeoRobotsHeader keeps it noindex. --}}
@php($siteName = settings('site_name', config('app.name')))
@php($title = $verified ? 'Email confirmed' : 'Link no longer works')
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>{{ $title }} | {{ $siteName }}</title>

    @include('partials.theme-head')

    <x-seo :title="$title.' | '.$siteName" />

    @include('partials.favicon')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-dvh flex-col bg-background font-sans text-foreground antialiased">
    <main class="flex flex-1 items-center justify-center p-6">
        @if ($verified)
            <x-ui.empty-state icon="mail-check" :title="$title" class="w-full max-w-md bg-card"
                :description="'Replies to your emails will now go to '.$email.'. You can close this page.'" />
        @else
            <x-ui.empty-state icon="mail-x" :title="$title" class="w-full max-w-md bg-card"
                description="This link has expired or the email was changed since it was sent. Save the email again in settings to get a new link." />
        @endif
    </main>
    <p class="pb-6 text-center text-sm text-muted-foreground">{{ $siteName }}</p>
</body>

</html>
