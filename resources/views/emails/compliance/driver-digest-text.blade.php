Hello {{ $driver->first_name }},

@if ($companyName)
{{ $companyName }} needs your attention on the following compliance documents:
@else
The following compliance documents need your attention:
@endif

@foreach ($issues as $issue)
- {{ $issue['name'] }}: {{ $issue['label'] }}@if (! empty($issue['expiry_date'])) (expiry: {{ $issue['expiry_date'] }})@endif

@endforeach
Please upload or renew these documents as soon as possible. Contact your fleet administrator if you have already submitted them.

— {{ config('app.name') }}
