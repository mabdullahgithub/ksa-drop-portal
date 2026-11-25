{!! config('app.name') !!}
{{ str_repeat('=', strlen(config('app.name'))) }}

Hi {!! $name !!},

Thank you for your interest in {!! config('app.name') !!}. We have reviewed the signup request for {!! $client->company_name !!}, and we are unable to approve it at this time.

@if($client->signup_rejection_reason)
Reason: {!! $client->signup_rejection_reason !!}

@endif
If you think this is a mistake or can share more information, reply to this email and quote your reference {!! $client->client_id !!}.

Best regards,
The {!! config('app.name') !!} Team

---
This email was sent to {!! $recipient !!} about a signup request made with this address.
(c) {{ date('Y') }} {!! config('app.name') !!}. All rights reserved.
