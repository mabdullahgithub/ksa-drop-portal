{!! config('app.name') !!}
{{ str_repeat('=', strlen(config('app.name'))) }}

Hi {!! $name !!},

Good news: your signup request for {!! $client->company_name !!} has been approved. Welcome to {!! config('app.name') !!}.

Reference: {!! $client->client_id !!}
Business:  {!! $client->company_name !!}
Service:   {{ $client->is_fulfilment ? 'Fulfilment' : 'Dropshipping' }}
Email:     {!! $recipient !!}

Sign in with your email address and the password you chose when you signed up:
{!! $loginUrl !!}

If you have forgotten your password, choose "Forgot Password" on the sign-in page.

Best regards,
The {!! config('app.name') !!} Team

---
This email was sent to {!! $recipient !!} about a signup request made with this address.
(c) {{ date('Y') }} {!! config('app.name') !!}. All rights reserved.
