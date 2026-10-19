@extends('emails.layouts.compliance')

@section('eyebrow', 'Team invitation')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:24px;">
        Hello,
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
        You have been invited to join <strong>{{ $companyName }}</strong> on {{ config('app.name') }} as <strong>{{ $roleName }}</strong>.
    </p>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Accept invitation'])

    <p style="margin:0;font-size:14px;line-height:22px;color:#52525b;">
        This link expires on {{ $expiresAt->format('F j, Y') }}. If you did not expect this invitation, you can ignore this email.
    </p>
@endsection
