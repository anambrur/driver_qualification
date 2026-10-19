{{-- A call-to-action link styled as a button in the platform brand colour: $url, $label. --}}
@php($theme = \App\Support\Ui\Theme::current())
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
    <tr>
        <td style="border-radius:8px;background-color:{{ $theme['brand'] }};">
            <a href="{{ $url }}" target="_blank" rel="noopener" style="display:inline-block;padding:12px 24px;border-radius:8px;background-color:{{ $theme['brand'] }};color:{{ $theme['brandForeground'] }};font-size:15px;font-weight:600;line-height:20px;text-decoration:none;">{{ $label }}</a>
        </td>
    </tr>
</table>
