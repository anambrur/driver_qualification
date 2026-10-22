@php($terms = \App\Models\Page::publishedUrl('terms-of-service'))
@php($privacy = \App\Models\Page::publishedUrl('privacy-policy'))
@if ($terms || $privacy)
    <p class="text-center text-xs text-muted-foreground">
        By creating an account you agree to our
        @if ($terms)<a href="{{ $terms }}" target="_blank" class="font-medium text-foreground underline underline-offset-4 hover:text-primary">Terms of Service</a>@endif
        @if ($terms && $privacy) and @endif
        @if ($privacy)<a href="{{ $privacy }}" target="_blank" class="font-medium text-foreground underline underline-offset-4 hover:text-primary">Privacy Policy</a>@endif.
    </p>
@endif
