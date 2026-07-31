@extends('emails.layouts.compliance')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:24px;">
        Hello {{ $driver->first_name }},
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
        @if ($companyName)
            {{ $companyName }} needs your attention on the following compliance documents:
        @else
            The following compliance documents need your attention:
        @endif
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;border:1px solid #e4e4e7;border-radius:8px;overflow:hidden;">
        <tr>
            <td style="padding:12px 16px;background-color:#fafafa;font-size:13px;font-weight:700;color:#52525b;">Document</td>
            <td style="padding:12px 16px;background-color:#fafafa;font-size:13px;font-weight:700;color:#52525b;">Status</td>
            <td style="padding:12px 16px;background-color:#fafafa;font-size:13px;font-weight:700;color:#52525b;">Expiry</td>
        </tr>
        @foreach ($issues as $issue)
            <tr>
                <td style="padding:12px 16px;font-size:14px;color:#18181b;border-top:1px solid #e4e4e7;">
                    {{ $issue['name'] }}
                </td>
                <td style="padding:12px 16px;font-size:14px;color:#18181b;border-top:1px solid #e4e4e7;">
                    {{ $issue['label'] }}
                </td>
                <td style="padding:12px 16px;font-size:14px;color:#18181b;border-top:1px solid #e4e4e7;">
                    {{ $issue['expiry_date'] ?? '—' }}
                </td>
            </tr>
        @endforeach
    </table>

    <p style="margin:0;font-size:14px;line-height:22px;color:#52525b;">
        Please upload or renew these documents as soon as possible. Contact your fleet administrator if you have already submitted them.
    </p>
@endsection
