{!! config('app.name') !!}
{{ str_repeat('=', strlen(config('app.name'))) }}

Hi {!! $name !!},

Thank you for signing up with {!! config('app.name') !!}. We have received your request, and it is now under review.

Reference: {!! $client->client_id !!}
Business:  {!! $client->company_name !!}
Service:   {{ $client->is_fulfilment ? 'Fulfilment' : 'Dropshipping' }}
Email:     {!! $recipient !!}

What happens next:
1. Our team reviews your business details.
2. We email you as soon as a decision is made.
3. Once approved, you sign in with the email and password you chose.

There is nothing else you need to do for now. If you did not make this request, reply to this email and let us know.

Best regards,
The {!! config('app.name') !!} Team

---
This email was sent to {!! $recipient !!} about a signup request made with this address.
(c) {{ date('Y') }} {!! config('app.name') !!}. All rights reserved.
