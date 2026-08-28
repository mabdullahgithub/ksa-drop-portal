<?php

namespace App\Services\WhatsApp;

use App\Models\ConnectorSetting;
use App\Models\Order;
use App\Models\WhatsAppMessage;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Meta WhatsApp Cloud API (Graph) client for the order-confirmation flow.
 *
 * Credential resolution follows the same precedence as every other connector
 * here (iMile/J&T/LogesTechs): whatever is saved in Apps → WhatsApp Settings
 * wins, with `config('services.whatsapp.*')` as the .env fallback so the
 * integration works before anything is configured in the UI.
 *
 * ── Templates are mandatory ───────────────────────────────────────────────
 * Unlike the Twilio integration this replaced, there is no sandbox and no
 * plain-text escape hatch. Meta rejects every business-initiated message that
 * is not a pre-approved template (error 131047), so a missing template name is
 * a hard configuration error we raise *before* spending an API call — see
 * {@see templateName()}. Free-form text is legal only inside the 24-hour
 * customer service window, which is what {@see sendFreeform()} is for.
 */
class MetaWhatsAppService
{
    public const TEMPLATE_ORDER_PENDING = 'order_pending';
    public const TEMPLATE_FOLLOWUP = 'followup';

    /** Graph API version used when none is configured. Matches what the
     *  Meta app dashboard currently generates in its sample calls. */
    private const DEFAULT_API_VERSION = 'v25.0';

    /** Cached connector settings — one DB round trip per instance. */
    private ?array $settings = null;

    /**
     * Send one approved template to an order's customer and record it in the
     * conversation log.
     *
     * @param  array<string,string>  $variables  Positional body variables, keyed "1", "2", …
     *
     * @throws RuntimeException when the connector is not configured, the order
     *                          has no usable phone number, the template name is
     *                          missing, or Meta rejects the send.
     */
    public function sendTemplate(Order $order, string $templateKey, array $variables = []): WhatsAppMessage
    {
        $to = $this->recipient($order);

        // Resolved before the HTTP call so a misconfigured template fails fast
        // and loudly instead of burning the job's retries on a 400 from Meta.
        $templateName = $this->templateName($templateKey);

        $response = $this->post([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->toWhatsAppNumber($to),
            'type' => 'template',
            'template' => array_filter([
                'name' => $templateName,
                'language' => ['code' => $this->setting('template_language') ?: 'ar'],
                // Omitted entirely for a template with no variables. Meta
                // rejects an empty body component with error 132000, which is
                // what a zero-parameter template (e.g. `hello_world`) would
                // otherwise produce.
                'components' => $variables === [] ? null : [[
                    'type' => 'body',
                    'parameters' => $this->bodyParameters($variables),
                ]],
            ]),
        ]);

        return WhatsAppMessage::create([
            'order_id' => $order->id,
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'provider_message_id' => $response['messages'][0]['id'] ?? null,
            'template_key' => $templateKey,
            // Store the rendered copy rather than the template name: the inbox
            // shows agents the thread as the customer sees it.
            'body' => $this->renderPreview($templateKey, $variables),
            'to_number' => $to,
            'from_number' => $this->setting('display_phone_number') ?: $this->setting('phone_number_id'),
            'status' => $response['messages'][0]['message_status'] ?? 'accepted',
            'sent_at' => now(),
        ]);
    }

    /**
     * Send an agent-typed reply as free-form text.
     *
     * Only legal inside WhatsApp's 24-hour customer service window, which the
     * customer opens by messaging us and which closes 24h after their last
     * inbound message. Inside it, free-form costs no template fee; outside it,
     * Meta rejects it with error 131047. The caller must check
     * {@see \App\Models\Order::whatsAppWindowExpiresAt()} first — this method
     * deliberately does not, so the guard lives in one place with the HTTP
     * response that reports it.
     */
    public function sendFreeform(Order $order, string $body, ?int $userId = null): WhatsAppMessage
    {
        $to = $this->recipient($order);

        $response = $this->post([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->toWhatsAppNumber($to),
            'type' => 'text',
            // Link previews are noise in a confirmation thread and can leak the
            // client's storefront domain into a chat we send on their behalf.
            'text' => ['preview_url' => false, 'body' => $body],
        ]);

        return WhatsAppMessage::create([
            'order_id' => $order->id,
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'provider_message_id' => $response['messages'][0]['id'] ?? null,
            'template_key' => null,
            'sent_by_user_id' => $userId,
            'body' => $body,
            'to_number' => $to,
            'from_number' => $this->setting('display_phone_number') ?: $this->setting('phone_number_id'),
            'status' => $response['messages'][0]['message_status'] ?? 'accepted',
            'sent_at' => now(),
        ]);
    }

    /**
     * Send a template straight to a number, with no order behind it and nothing
     * written to the conversation log.
     *
     * Exists purely so `whatsapp:test-send` can prove the credentials, the
     * template and the recipient all line up before the order flow depends on
     * them. Never call it from the order flow — a customer-facing message that
     * leaves no audit trail is exactly what whatsapp_messages exists to prevent.
     *
     * @param  list<string>  $variables  Positional body variables; empty for a
     *                                   zero-parameter template like hello_world.
     * @return array<string,mixed>
     */
    public function sendTestTemplate(string $toE164, string $templateName, string $language, array $variables = []): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->toWhatsAppNumber($toE164),
            'type' => 'template',
            'template' => array_filter([
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $variables === [] ? null : [[
                    'type' => 'body',
                    'parameters' => array_map(
                        static fn ($v) => ['type' => 'text', 'text' => (string) $v],
                        array_values($variables)
                    ),
                ]],
            ]),
        ]);
    }

    /**
     * Mark an inbound message as read, so the customer sees blue ticks and
     * knows a human is on the other end. Best-effort: a failure here must never
     * break webhook processing, so it swallows errors rather than throwing.
     */
    public function markAsRead(string $providerMessageId): void
    {
        try {
            $this->post([
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $providerMessageId,
            ]);
        } catch (RuntimeException) {
            // Already read, expired, or transient — nothing actionable.
        }
    }

    /**
     * Validate the stored credentials by fetching the phone number itself —
     * cheap, read-only, and it exercises exactly the auth path sends use.
     *
     * @return array{display_phone_number?:string, verified_name?:string, quality_rating?:string}
     */
    public function fetchPhoneNumber(): array
    {
        $phoneNumberId = $this->setting('phone_number_id');
        $token = $this->setting('access_token');

        if (! $phoneNumberId || ! $token) {
            throw new RuntimeException('Meta credentials are not configured. Set them in Apps → WhatsApp Settings.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get($this->graphUrl($phoneNumberId), [
                'fields' => 'display_phone_number,verified_name,quality_rating',
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response->json()));
        }

        return $response->json();
    }

    public function testConnection(): bool
    {
        return isset($this->fetchPhoneNumber()['display_phone_number']);
    }

    /**
     * The app secret is needed outside this class too — the webhook verifies
     * Meta's X-Hub-Signature-256 against it.
     */
    public function appSecret(): ?string
    {
        return $this->setting('app_secret');
    }

    /**
     * Shared secret Meta echoes back during the webhook subscription handshake.
     */
    public function webhookVerifyToken(): ?string
    {
        return $this->setting('webhook_verify_token');
    }

    /**
     * The phone number ID this portal owns. Inbound webhooks carry the ID they
     * were delivered for, and one Meta app can serve several numbers, so the
     * webhook drops anything addressed to a number that isn't ours.
     */
    public function phoneNumberId(): ?string
    {
        return $this->setting('phone_number_id');
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->setting('phone_number_id') && $this->setting('access_token'));
    }

    /**
     * POST to the Cloud API messages endpoint.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function post(array $payload): array
    {
        $phoneNumberId = $this->setting('phone_number_id');
        $token = $this->setting('access_token');

        if (! $phoneNumberId || ! $token) {
            throw new RuntimeException('Meta credentials are not configured. Set them in Apps → WhatsApp Settings.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->post($this->graphUrl($phoneNumberId . '/messages'), $payload);

        // The Http facade does not throw by default, and SendWhatsAppOrderMessageJob
        // keys its retry/fail handling off exceptions — so a rejected send has to
        // surface as one or the order would be recorded as sent.
        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response->json()));
        }

        return $response->json() ?? [];
    }

    private function graphUrl(string $path): string
    {
        $version = $this->setting('api_version') ?: self::DEFAULT_API_VERSION;

        return "https://graph.facebook.com/{$version}/{$path}";
    }

    /**
     * Flatten Meta's error envelope into something worth putting in a log line
     * or showing an agent. `code` is the part worth acting on — 131047 means
     * the 24h window closed, 131026 means the number can't receive WhatsApp.
     */
    private function errorMessage(mixed $body): string
    {
        $error = is_array($body) ? ($body['error'] ?? []) : [];

        $message = $error['message'] ?? 'WhatsApp API request failed.';
        $details = $error['error_data']['details'] ?? null;
        $code = $error['code'] ?? null;

        return trim(implode(' ', array_filter([
            $message,
            $details ? "({$details})" : null,
            $code !== null ? "[code {$code}]" : null,
        ])));
    }

    /**
     * Meta addresses recipients as bare E.164 digits — no `+`, no scheme
     * prefix. (Twilio's `whatsapp:+966…` form is not accepted.)
     */
    private function toWhatsAppNumber(string $e164): string
    {
        return ltrim($e164, '+');
    }

    private function recipient(Order $order): string
    {
        $to = $order->whatsapp_phone_e164 ?: PhoneNumber::toE164($order->customer_phone ?: $order->shipping_phone);

        if (! $to) {
            throw new RuntimeException("Order {$order->id} has no usable phone number for WhatsApp.");
        }

        return $to;
    }

    /**
     * Template names are per-key and must match what was approved in WhatsApp
     * Manager. There is no fallback: sending without an approved template is
     * rejected by Meta, so an unset name is a configuration error, not a case
     * to degrade gracefully.
     */
    private function templateName(string $templateKey): string
    {
        $name = $this->setting("template_name_{$templateKey}");

        if (! $name) {
            throw new RuntimeException(
                "No approved WhatsApp template configured for '{$templateKey}'. "
                . 'Set it in Apps → WhatsApp Settings — Meta rejects business-initiated messages without one.'
            );
        }

        return $name;
    }

    /**
     * Meta takes body variables as an ordered array, not the keyed map Twilio's
     * Content API used. Sort by the numeric key so {{1}}, {{2}}, {{3}} land in
     * the right slots regardless of the order the caller built them in — a
     * mismatch here is error 132000 and a rejected send.
     *
     * @param  array<string,string>  $variables
     * @return list<array{type:string, text:string}>
     */
    private function bodyParameters(array $variables): array
    {
        uksort($variables, static fn ($a, $b) => (int) $a <=> (int) $b);

        return array_values(array_map(
            static fn ($value) => ['type' => 'text', 'text' => (string) $value],
            $variables
        ));
    }

    /**
     * DB setting first, .env fallback second — mirrors ImileDriver's lookup.
     */
    private function setting(string $key): ?string
    {
        if ($this->settings === null) {
            $this->settings = ConnectorSetting::getAllForConnector('whatsapp');
        }

        $value = $this->settings[$key] ?? null;

        if ($value !== null && $value !== '') {
            return $value;
        }

        return config("services.whatsapp.{$key}") ?: null;
    }

    /**
     * Readable rendering of what the customer will see, for the conversation
     * log. Not what is sent — Meta renders the approved template itself — so a
     * drift between this copy and the approved template only affects the
     * internal preview, never the customer's message.
     */
    private function renderPreview(string $templateKey, array $variables): string
    {
        $body = $this->setting("body_{$templateKey}") ?: $this->defaultBody($templateKey);

        foreach ($variables as $position => $value) {
            $body = str_replace('{{' . $position . '}}', (string) $value, $body);
        }

        return $body;
    }

    /**
     * ⚠️ No sender name appears in this copy, deliberately.
     *
     * The customer bought from our client's store, not from us — naming the
     * fulfilment platform would confuse them and expose the dropshipping
     * relationship. WhatsApp already shows the sending business's profile name
     * in the chat header, so the message is still attributable without us
     * putting a brand in the body. Never reintroduce a name variable here, and
     * never fall back to config('app.name').
     *
     * This is also the copy to submit to Meta for approval, verbatim — keeping
     * them identical is what makes the inbox preview trustworthy.
     */
    private function defaultBody(string $templateKey): string
    {
        return match ($templateKey) {
            self::TEMPLATE_ORDER_PENDING => "Hello {{1}},\n\n"
                . "We tried calling you about your order {{2}} but couldn't reach you.\n\n"
                . "Please reply:\n"
                . "1 — to CONFIRM your order\n"
                . "2 — to UPDATE your delivery address\n"
                . "3 — to CANCEL\n\n"
                . 'Delivery address on file: {{3}}',
            self::TEMPLATE_FOLLOWUP => "Hello {{1}}, a reminder about your order {{2}}.\n\n"
                . "We still need your confirmation to ship it. Please reply:\n"
                . "1 — to CONFIRM\n"
                . "2 — to UPDATE your address\n"
                . "3 — to CANCEL\n\n"
                . 'If we do not hear back, the order will be put on hold.',
            default => 'Hello {{1}}, please get in touch regarding your order {{2}}.',
        };
    }
}
