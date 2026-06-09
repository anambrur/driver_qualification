<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f5;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background-color:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e4e4e7;">
                    <tr>
                        <td style="background-color:#2563eb;padding:24px 32px;">
                            <h1 style="margin:0;font-size:20px;line-height:28px;color:#ffffff;font-weight:700;">
                                {{ config('app.name') }}
                            </h1>
                            <p style="margin:8px 0 0;font-size:14px;line-height:20px;color:#dbeafe;">
                                Compliance Notification
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;background-color:#fafafa;border-top:1px solid #e4e4e7;">
                            <p style="margin:0;font-size:12px;line-height:18px;color:#71717a;text-align:center;">
                                This is an automated message from {{ config('app.name') }}.
                                Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
