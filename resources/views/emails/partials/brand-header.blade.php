{{--
    The brand band at the top of every email: the site logo (if mail clients can show it) and
    name on the platform brand colour. Inline styles only; mail clients drop classes.
--}}
@php
    $theme = \App\Support\Ui\Theme::current();
    $logo = \App\Support\Ui\Theme::emailLogo();
@endphp
<td class="email-pad" style="background-color:{{ $theme['brand'] }};padding:24px 32px;">
    <table role="presentation" cellspacing="0" cellpadding="0">
        <tr>
            @if ($logo)
                <td style="padding:0 12px 0 0;vertical-align:middle;">
                    <img src="{{ $logo }}" alt="{{ config('app.name') }}" height="32" style="display:block;height:32px;width:auto;max-width:160px;border:0;border-radius:6px;">
                </td>
            @endif
            <td style="vertical-align:middle;">
                <p style="margin:0;font-size:20px;line-height:28px;font-weight:700;color:{{ $theme['brandForeground'] }};">{{ config('app.name') }}</p>
            </td>
        </tr>
    </table>
    @if ($eyebrow ?? null)
        <p style="margin:8px 0 0;font-size:14px;line-height:20px;color:{{ $theme['brandForeground'] }};opacity:0.85;">{{ $eyebrow }}</p>
    @endif
</td>
