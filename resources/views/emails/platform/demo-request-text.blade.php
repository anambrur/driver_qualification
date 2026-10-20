{{ $demoRequest->source === \App\Models\DemoRequest::SOURCE_CONTACT ? 'A visitor sent a message from the Contact page.' : 'A visitor asked for a demo on the homepage.' }}

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

Open the inbox: {{ route('platform.content.demo-requests.index') }}
