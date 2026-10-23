@props(['title' => null, 'description' => null, 'image' => null, 'type' => 'website', 'schema' => null])
@php
    $indexable = \App\Support\Seo::indexable();
    $image = $image ?? \App\Support\Seo::image();
@endphp
<meta name="robots" content="{{ \App\Support\Seo::robots() }}">
@if ($indexable && settings('search_console_verification'))<meta name="google-site-verification" content="{{ settings('search_console_verification') }}">@endif
@if ($indexable)
    <link rel="canonical" href="{{ \App\Support\Seo::canonical() }}">
    @if ($description)<meta name="description" content="{{ $description }}">@endif
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:site_name" content="{{ settings('site_name', config('app.name')) }}">
    <meta property="og:url" content="{{ \App\Support\Seo::canonical() }}">
    @if ($title)<meta property="og:title" content="{{ $title }}">@endif
    @if ($description)<meta property="og:description" content="{{ $description }}">@endif
    @if ($image)<meta property="og:image" content="{{ $image }}">@endif
    <meta name="twitter:card" content="{{ $image ? settings('twitter_card', 'summary_large_image') : 'summary' }}">
    @if ($title)<meta name="twitter:title" content="{{ $title }}">@endif
    @if ($description)<meta name="twitter:description" content="{{ $description }}">@endif
    @if ($image)<meta name="twitter:image" content="{{ $image }}">@endif
    @if ($schema)<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>@endif
@endif
