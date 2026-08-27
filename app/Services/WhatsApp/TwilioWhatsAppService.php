<?php

namespace App\Services\WhatsApp;

use App\Models\ConnectorSetting;
use App\Models\Order;
use App\Models\WhatsAppMessage;
use App\Support\PhoneNumber;
use RuntimeException;
use Twilio\Rest\Client;

/**
 * Thin wrapper over Twilio's Messages API for the order-confirmation flow.
 *
 * Credential resolution follows the same precedence as every other connector
 * here (iMile/J&T/LogesTechs): whatever is saved in Apps → WhatsApp Settings
 * wins, with `config('services.twilio.*')` as the .env fallback so the
 * integration works before anything is configured in the UI.
 *
 * ── Templates vs. free text ──────────────────────────────────────────────
 * Meta only allows business-initiated messages using a pre-approved template,
 * so production sends go out as a Content SID + variables. But template
 * approval takes days, and Twilio's WhatsApp *sandbox* accepts plain text
 * immediately. So: if a template SID is configured for the requested key we
 * use it; otherwise we fall back to sending the configured body text directly.
 * That fallback is what makes end-to-end sandbox testing possible on day one —
 * it is NOT viable in production, where an un-templated business-initiated
 * message is rejected by Meta.
 */
class TwilioWhatsAppService
{
    public const TEMPLATE_ORDER_PENDING = 'order_pending';
    public const TEMPLATE_FOLLOWUP = 'followup';

    private ?Client $client = null;

    /** Cached connector settings — one DB round trip per instance. */
    private ?array $settings = null;

    /**
     * Send one templated (or, in sandbox, plain-text) message to an order's
     * customer and record it in the conversation log.
     *
     * @param  array<string,string>  $variables  Content template variables, keyed "1", "2", …
     *
     * @throws RuntimeException when the connector is not configured or the
     *                          order has no usable phone number.
     */
    public function sendTemplate(Order $order, string $templateKey, array $variables = []): WhatsAppMessage
    {
        $to = $order->whatsapp_phone_e164 ?: PhoneNumber::toE164($order->customer_phone ?: $order->shipping_phone);

        if (! $to) {
            throw new RuntimeException("Order {$order->id} has no usable phone number for WhatsApp.");
        }

        $from = $this->setting('whatsapp_from');

        if (! $from) {
            throw new RuntimeException('WhatsApp sender number is not configured.');
        }

        $options = [
            'from' => PhoneNumber::toWhatsAppAddress($from),
        ];

        // Ask Twilio to POST delivery/read transitions back to us. Without this
        // we would only ever know a message was accepted, never whether it
        // actually reached the customer.
        if ($callback = $this->statusCallbackUrl()) {
            $options['statusCallback'] = $callback;
        }

        $contentSid = $this->setting("template_sid_{$templateKey}");
        $body = null;

        if ($contentSid) {
            $options['contentSid'] = $contentSid;

            if ($variables !== []) {
                $options['contentVariables'] = json_encode($variables, JSON_UNESCAPED_UNICODE);
            }
        } else {
            $body = $this->renderFallbackBody($templateKey, $variables);
            $options['body'] = $body;
        }

        $message = $this->client()->messages->create(
            PhoneNumber::toWhatsAppAddress($to),
            $options
        );

        return WhatsAppMessage::create([
            'order_id' => $order->id,
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'twilio_sid' => $message->sid,
            'template_key' => $templateKey,
            'body' => $body ?? $this->describeTemplate($contentSid, $variables),
            'to_number' => $to,
            'from_number' => $from,
            'status' => $message->status,
            'sent_at' => now(),
        ]);
    }

    /**
     * Validate the stored credentials by fetching the account — used by the
     * "Test Connection" button on the settings page.
     */
    public function testConnection(): bool
    {
        $sid = $this->setting('account_sid');

        if (! $sid) {
            throw new RuntimeException('Twilio Account SID is not configured.');
        }

        $account = $this->client()->api->v2010->accounts($sid)->fetch();

        return in_array($account->status, ['active', 'trial'], true);
    }

    /**
     * The auth token is needed outside this class too — the inbound webhook
     * verifies Twilio's request signature against it.
     */
    public function authToken(): ?string
    {
        return $this->setting('auth_token');
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->setting('account_sid') && $this->setting('auth_token') && $this->setting('whatsapp_from'));
    }

    private function client(): Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        $sid = $this->setting('account_sid');
        $token = $this->setting('auth_token');

        if (! $sid || ! $token) {
            throw new RuntimeException('Twilio credentials are not configured. Set them in Apps → WhatsApp Settings.');
        }

        return $this->client = new Client($sid, $token);
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

        return config("services.twilio.{$key}") ?: null;
    }

    /**
     * Absolute URL Twilio POSTs message status transitions to. Skipped for
     * local hosts, where Twilio cannot reach us and would only log delivery
     * errors on every send.
     */
    private function statusCallbackUrl(): ?string
    {
        $base = rtrim((string) config('app.url'), '/');

        if ($base === '' || preg_match('/localhost|127\.0\.0\.1/i', $base)) {
            return null;
        }

        return $base . '/webhooks/twilio/whatsapp/status';
    }

    /**
     * Sandbox-only plain-text rendering. Variables are substituted positionally
     * ({{1}}, {{2}}, …) so the same string can later be submitted to Meta
     * verbatim as the approved template body.
     */
    private function renderFallbackBody(string $templateKey, array $variables): string
    {
        $body = $this->setting("body_{$templateKey}") ?: $this->defaultBody($templateKey);

        foreach ($variables as $position => $value) {
            $body = str_replace('{{' . $position . '}}', (string) $value, $body);
        }

        return $body;
    }

    private function defaultBody(string $templateKey): string
    {
        return match ($templateKey) {
            self::TEMPLATE_ORDER_PENDING => "Hello {{1}}, this is {{2}}.\n\n"
                . "We tried calling you about your order {{3}} but couldn't reach you.\n\n"
                . "Please reply:\n"
                . "1 — to CONFIRM your order\n"
                . "2 — to UPDATE your delivery address\n"
                . "3 — to CANCEL\n\n"
                . 'Delivery address on file: {{4}}',
            self::TEMPLATE_FOLLOWUP => "Hello {{1}}, a reminder from {{2}} about your order {{3}}.\n\n"
                . "We still need your confirmation to ship it. Please reply:\n"
                . "1 — to CONFIRM\n"
                . "2 — to UPDATE your address\n"
                . "3 — to CANCEL\n\n"
                . 'If we do not hear back, the order will be put on hold.',
            default => 'Hello {{1}}, please contact us regarding your order {{3}}.',
        };
    }

    /**
     * Content-template sends have no body of our own to log, so store a
     * readable stand-in for the ops conversation view.
     */
    private function describeTemplate(string $contentSid, array $variables): string
    {
        return '[template ' . $contentSid . '] ' . json_encode($variables, JSON_UNESCAPED_UNICODE);
    }
}
