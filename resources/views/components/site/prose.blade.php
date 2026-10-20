{{-- Stored rich text. $html is sanitized on save (App\Support\RichText), never raw input. --}}
@props(['html'])
<div {{ $attributes->class([
    'prose prose-lg max-w-none dark:prose-invert',
    'prose-headings:font-bold prose-headings:tracking-tight prose-a:text-primary prose-a:underline-offset-4 hover:prose-a:text-primary/80',
    'prose-blockquote:border-l-primary prose-li:marker:text-primary',
]) }}>{!! $html !!}</div>
