@extends('emails.layouts.compliance')

@php
    $assetTypeLabel = $assetType === 'trailer' ? 'Trailer' : 'Vehicle';
@endphp

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:24px;">
        Hello {{ $driver->first_name }},
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
        @if ($companyName)
            {{ $companyName }} requires your attention regarding {{ strtolower($assetTypeLabel) }} compliance documentation.
        @else
            Your fleet administrator requires your attention regarding {{ strtolower($assetTypeLabel) }} compliance documentation.
        @endif
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;border:1px solid #e4e4e7;border-radius:8px;overflow:hidden;">
        <tr>
            <td style="padding:12px 16px;background-color:#fafafa;font-size:13px;font-weight:700;color:#52525b;width:40%;">
                {{ $assetTypeLabel }}
            </td>
            <td style="padding:12px 16px;font-size:14px;color:#18181b;">
                {{ $assetLabel }}
            </td>
        </tr>
        <tr>
            <td style="padding:12px 16px;background-color:#fafafa;font-size:13px;font-weight:700;color:#52525b;border-top:1px solid #e4e4e7;">
                Document
            </td>
            <td style="padding:12px 16px;font-size:14px;color:#18181b;border-top:1px solid #e4e4e7;">
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
            The required document for this {{ strtolower($assetTypeLabel) }} is missing. Please upload it as soon as possible.
        @elseif ($complianceStatus === 'expired')
            The document for this {{ strtolower($assetTypeLabel) }} has expired. Please submit an updated document immediately.
        @elseif ($complianceStatus === 'expiring')
            The document for this {{ strtolower($assetTypeLabel) }} is expiring soon. Please arrange for renewal before the expiry date.
        @else
            Please review the compliance status for this {{ strtolower($assetTypeLabel) }} and take any necessary action.
        @endif
    </p>

    <p style="margin:0;font-size:14px;line-height:22px;color:#52525b;">
        If you have already addressed this item, please contact your fleet administrator.
    </p>
@endsection
