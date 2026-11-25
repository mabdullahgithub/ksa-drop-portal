@extends('emails.layouts.default', ['preheader' => 'Our team is reviewing your signup request.'])

@section('content')
    <h1 class="email-title">Your request has been submitted</h1>

    <p class="email-text">
        Hi {{ $name }},
    </p>

    <p class="email-text">
        Thank you for signing up with <strong>{{ config('app.name') }}</strong>. We have received your request, and it is now under review.
    </p>

    @component('emails.components.alert', ['type' => 'info'])
        <p style="margin: 0; color: #374151; font-size: 15px; line-height: 1.6;">
            <strong style="color: #111827;">Reference:</strong> {{ $client->client_id }}<br>
            <strong style="color: #111827;">Business:</strong> {{ $client->company_name }}<br>
            <strong style="color: #111827;">Service:</strong> {{ $client->is_fulfilment ? 'Fulfilment' : 'Dropshipping' }}<br>
            <strong style="color: #111827;">Email:</strong> {{ $recipient }}
        </p>
    @endcomponent

    <p class="email-text">
        <strong>What happens next</strong>
    </p>

    <ol style="color: #4b5563; margin: 0 0 16px 0; padding-left: 20px;">
        <li style="margin-bottom: 8px;">Our team reviews your business details.</li>
        <li style="margin-bottom: 8px;">We email you as soon as a decision is made.</li>
        <li style="margin-bottom: 8px;">Once approved, you sign in with the email and password you chose.</li>
    </ol>

    @php
        // Attached to the email like the logo, so it shows without the reader's client fetching it.
        $badgePath = public_path('images/integrations/shopify-partner.png');
        $badgeSrc = (isset($message) && file_exists($badgePath))
            ? $message->embed($badgePath)
            : rtrim(config('app.url'), '/') . '/images/integrations/shopify-partner.png';
    @endphp

    {{-- The badge's lettering is white, so it needs the dark cell behind it. --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 24px 0 12px 0;">
        <tr>
            <td bgcolor="#0b0f19" style="background-color: #0b0f19; padding: 10px 16px; border-radius: 8px;">
                <img src="{{ $badgeSrc }}" width="147" height="40" alt="Shopify Partner" style="display: block; width: 147px; height: 40px; color: #ffffff;">
            </td>
        </tr>
    </table>

    <p class="email-text">
        <strong>We are a Shopify Partner.</strong> KSA DROP has its own Shopify app. Use KSADrop Portal to connect your store once your account is approved.
    </p>

    @component('emails.components.button', ['url' => $shopifyAppUrl])
        View our app on Shopify
    @endcomponent

    <p class="email-text">
        There is nothing else you need to do for now. If you did not make this request, reply to this email and let us know.
    </p>

    <p class="email-text" style="margin-top: 32px;">
        Best regards,<br>
        <strong>The {{ config('app.name') }} Team</strong>
    </p>
@endsection
