Hello,

You have been invited to join {{ $companyName }} on {{ config('app.name') }} as {{ $roleName }}.

Accept the invitation: {{ $url }}

This link expires on {{ $expiresAt->format('F j, Y') }}. If you did not expect this invitation, you can ignore this email.

— {{ config('app.name') }}
