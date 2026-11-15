About your partner application

Hi {{ $partner->user->name }},

Thank you for your interest in the {{ config('app.name') }} partner program. After reviewing your application, we are not able to approve it at this time.
@if ($partner->rejection_reason)

Reason: {{ $partner->rejection_reason }}
@endif

If anything has changed, reply to this email and we will take another look.

Best regards,
The {{ config('app.name') }} Team
