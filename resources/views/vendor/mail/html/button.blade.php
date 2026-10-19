@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php($theme = \App\Support\Ui\Theme::current())
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
@if ($color === 'primary')
<a href="{{ $url }}" class="button" target="_blank" rel="noopener" style="background-color: {{ $theme['brand'] }}; border: 0; border-radius: 8px; color: {{ $theme['brandForeground'] }}; padding: 12px 24px;">{!! $slot !!}</a>
@else
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener">{!! $slot !!}</a>
@endif
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
