@if (settings('favicon'))
    <link rel="icon" type="image/png" href="{{ asset('storage/'.settings('favicon')) }}">
    <link rel="apple-touch-icon" href="{{ asset('storage/'.settings('favicon')) }}">
@else
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/logo-icon.svg') }}">
@endif
