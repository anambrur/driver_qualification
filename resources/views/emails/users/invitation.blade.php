@extends('emails.layouts.compliance')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:24px;">
        Hello,
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
        You have been invited to join <strong>{{ $companyName }}</strong> on {{ config('app.name') }} as <strong>{{ $roleName }}</strong>.
    </p>

    <p style="margin:0 0 24px;">
        <a href="{{ $url }}" style="display:inline-block;padding:10px 20px;background:#465fff;color:#ffffff;border-radius:8px;text-decoration:none;font-size:15px;">
            Accept invitation
        </a>
    </p>

    <p style="margin:0;font-size:14px;line-height:22px;color:#52525b;">
        This link expires on {{ $expiresAt->format('F j, Y') }}. If you did not expect this invitation, you can ignore this email.
    </p>
@endsection
