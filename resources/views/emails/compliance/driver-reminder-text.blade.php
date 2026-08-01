Hello {{ $driver->first_name }},

@if ($companyName)
{{ $companyName }} requires your attention regarding the following driver compliance document:
@else
Your compliance team requires your attention regarding the following driver document:
@endif

Document: {{ $documentType->name }}
Status: {{ $statusLabel }}
@if ($expiryDate)
Expiry Date: {{ $expiryDate }}@if ($daysUntilExpiry !== null && $daysUntilExpiry >= 0) ({{ $daysUntilExpiry }} {{ str('day')->plural($daysUntilExpiry) }} remaining)@endif

@endif

@if ($complianceStatus === 'missing')
This document has not been submitted. Please upload the required document as soon as possible to remain compliant.
@elseif ($complianceStatus === 'expired')
This document has expired. Please submit an updated document immediately to restore compliance.
@elseif ($complianceStatus === 'expiring')
This document is expiring soon. Please prepare and submit a renewed document before the expiry date.
@else
Please review this document and ensure your compliance records remain up to date.
@endif

If you have already submitted this document, please contact your fleet administrator.

— {{ config('app.name') }}
