@extends('emails.layouts.compliance')

@section('eyebrow', 'Demo request')

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
        A visitor asked for a demo on the homepage.
    </p>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px;font-size:14px;line-height:22px;">
        @foreach (['Name' => $demoRequest->name, 'Email' => $demoRequest->email, 'Company' => $demoRequest->company, 'Phone' => $demoRequest->phone] as $label => $value)
            @if (filled($value))
                <tr>
                    <td style="padding:0 16px 4px 0;color:#52525b;">{{ $label }}</td>
                    <td style="padding:0 0 4px;">{{ $value }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    @if (filled($demoRequest->message))
        <p style="margin:0 0 24px;font-size:14px;line-height:22px;white-space:pre-line;">{{ $demoRequest->message }}</p>
    @endif

    @include('emails.partials.button', ['url' => route('platform.content.demo-requests.index'), 'label' => 'Open demo requests'])
@endsection
