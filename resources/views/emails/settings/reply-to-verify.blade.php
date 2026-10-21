@extends('emails.layouts.compliance')

@section('eyebrow', 'Email for replies')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:24px;">
        Hello,
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
        <strong>{{ $ownerName }}</strong> wants replies to its emails on {{ config('app.name') }} to come to this inbox. Confirm it is yours to start receiving them.
    </p>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Confirm this email'])

    <p style="margin:0;font-size:14px;line-height:22px;color:#52525b;">
        This link works for {{ $hours }} hours. If you did not ask for this, you can ignore this email.
    </p>
@endsection
