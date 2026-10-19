<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ config('app.name') }}</title>
    <style>
        @media only screen and (max-width: 600px) {
            .email-outer { padding: 16px 8px !important; }
            .email-pad { padding-left: 20px !important; padding-right: 20px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#18181b;-webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f5;">
        <tr>
            <td class="email-outer" align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e4e4e7;">
                    <tr>
                        @include('emails.partials.brand-header', ['eyebrow' => trim($__env->yieldContent('eyebrow', 'Compliance Notification'))])
                    </tr>
                    <tr>
                        <td class="email-pad" style="padding:32px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td class="email-pad" style="padding:20px 32px;background-color:#fafafa;border-top:1px solid #e4e4e7;">
                            <p style="margin:0;font-size:12px;line-height:18px;color:#71717a;text-align:center;">
                                This is an automated message from {{ config('app.name') }}.
                                @if (config('mail.reply_to.address'))
                                    You can reply to this email if you need assistance.
                                @else
                                    Please do not reply directly to this email.
                                @endif
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
