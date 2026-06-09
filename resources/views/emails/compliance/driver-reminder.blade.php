@extends('emails.layouts.compliance')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:24px;">
        Hello {{ $driver->first_name }},
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
        @if ($companyName)
            {{ $companyName }} requires your attention regarding the following driver compliance document:
        @else
            Your compliance team requires your attention regarding the following driver document:
        @endif
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;border:1px solid #e4e4e7;border-radius:8px;overflow:hidden;">
        <tr>
            <td style="padding:12px 16px;background-color:#fafafa;font-size:13px;font-weight:700;color:#52525b;width:40%;">
                Document
            </td>
            <td style="padding:12px 16px;font-size:14px;color:#18181b;">
                {{ $documentType->name }}
            </td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background-color:#fafafa;font-size:13px;font-weight:700;color:#52525b;border-top:1px solid #e4e4e7;">
                Status
            </td>
            <td style="padding:12px 16px;font-size:14px;color:#18181b;border-top:1px solid #e4e4e7;">
                {{ $statusLabel }}
            </td>
        </tr>
        @if ($expiryDate)
            <tr>
                <td style="padding:12px 16px;background-color:#fafafa;font-size:13px;font-weight:700;color:#52525b;border-top:1px solid #e4e4e7;">
                    Expiry Date
                </td>
                <td style="padding:12px 16px;font-size:14px;color:#18181b;border-top:1px solid #e4e4e7;">
                    {{ $expiryDate }}
                    @if ($daysUntilExpiry !== null && $daysUntilExpiry >= 0)
                        ({{ $daysUntilExpiry }} {{ str('day')->plural($daysUntilExpiry) }} remaining)
                    @endif
                </td>
            </tr>
        @endif
    </table>

    <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
        @if ($complianceStatus === 'missing')
            This document has not been submitted. Please upload the required document as soon as possible to remain compliant.
        @elseif ($complianceStatus === 'expired')
            This document has expired. Please submit an updated document immediately to restore compliance.
        @elseif ($complianceStatus === 'expiring')
            This document is expiring soon. Please prepare and submit a renewed document before the expiry date.
        @else
            Please review this document and ensure your compliance records remain up to date.
        @endif
    </p>

    <p style="margin:0;font-size:14px;line-height:22px;color:#52525b;">
        If you have already submitted this document, please contact your fleet administrator.
    </p>
@endsection
