@php
    $assetTypeLabel = $assetType === 'trailer' ? 'Trailer' : 'Vehicle';
@endphp
Hello {{ $driver->first_name }},

@if ($companyName)
{{ $companyName }} requires your attention regarding {{ strtolower($assetTypeLabel) }} compliance documentation.
@else
Your fleet administrator requires your attention regarding {{ strtolower($assetTypeLabel) }} compliance documentation.
@endif

{{ $assetTypeLabel }}: {{ $assetLabel }}
Document: {{ $documentType->name }}
Status: {{ $statusLabel }}
@if ($expiryDate)
Expiry Date: {{ $expiryDate }}@if ($daysUntilExpiry !== null && $daysUntilExpiry >= 0) ({{ $daysUntilExpiry }} {{ str('day')->plural($daysUntilExpiry) }} remaining)@endif

@endif

@if ($complianceStatus === 'missing')
The required document for this {{ strtolower($assetTypeLabel) }} is missing. Please upload it as soon as possible.
@elseif ($complianceStatus === 'expired')
The document for this {{ strtolower($assetTypeLabel) }} has expired. Please submit an updated document immediately.
@elseif ($complianceStatus === 'expiring')
The document for this {{ strtolower($assetTypeLabel) }} is expiring soon. Please arrange for renewal before the expiry date.
@else
Please review the compliance status for this {{ strtolower($assetTypeLabel) }} and take any necessary action.
@endif

If you have already addressed this item, please contact your fleet administrator.

— {{ config('app.name') }}
