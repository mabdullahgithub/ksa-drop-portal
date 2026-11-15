Your payout has been sent

Hi {{ $partner->user->name }},

We have sent your payout for request #{{ $payout->id }}.

Points redeemed: {{ number_format($payout->points) }}
Amount sent: {{ number_format((float) $payout->amount_paid, 2) }} {{ $payout->currency }}
@if ($payout->admin_note)
Note: {{ $payout->admin_note }}
@endif

{{ $dashboardUrl }}

Best regards,
The {{ config('app.name') }} Team
