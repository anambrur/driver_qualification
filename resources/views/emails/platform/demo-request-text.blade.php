A visitor asked for a demo on the homepage.

Name: {{ $demoRequest->name }}
Email: {{ $demoRequest->email }}
@if (filled($demoRequest->company))
Company: {{ $demoRequest->company }}
@endif
@if (filled($demoRequest->phone))
Phone: {{ $demoRequest->phone }}
@endif
@if (filled($demoRequest->message))

{{ $demoRequest->message }}
@endif

Open demo requests: {{ route('platform.content.demo-requests.index') }}
