<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Meta's single WhatsApp Cloud API webhook: the subscription handshake, inbound
 * customer replies, and delivery/read receipts — all on one URL.
 *
 * Every request carries a real HMAC-SHA256 signature over the raw body, which
 * is what Meta actually sends. Building the signature over `json_encode` of the
 * same payload the request is given is the point: if the controller ever reads
 * the parsed input instead of the raw bytes, these tests fail.
 */
class MetaWhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'test-app-secret';
    private const VERIFY_TOKEN = 'test-verify-token';
    private const PHONE_NUMBER_ID = '1221052164432743';
    private const CUSTOMER = '+966501234567';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp', [
            'phone_number_id' => self::PHONE_NUMBER_ID,
            'access_token' => 'test-token',
            'app_secret' => self::APP_SECRET,
            'webhook_verify_token' => self::VERIFY_TOKEN,
            'display_phone_number' => '+15552027928',
            'template_name_order_pending' => 'order_pending',
            'template_name_followup' => 'followup',
        ]);

        // The controller marks inbound messages as read, which is a Graph call.
        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true], 200),
        ]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => (string) random_int(100000, 999999),
            'customer_name' => 'Zain',
            'customer_phone' => '0501234567',
            'shipping_city' => 'Riyadh',
            'currency' => 'SAR',
            'total' => 150.0,
            'payment_method' => 'cod',
            'financial_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'call_status' => Order::CALL_NO_ANSWER,
            'whatsapp_status' => Order::WHATSAPP_SENT,
            'whatsapp_phone_e164' => self::CUSTOMER,
            'whatsapp_sent_at' => now()->subHour(),
        ], $overrides));
    }

    /** POST raw JSON with a correctly computed X-Hub-Signature-256, as Meta sends it. */
    private function signedPost(array $payload, ?string $secret = null)
    {
        $body = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret ?? self::APP_SECRET);

        return $this->call(
            'POST',
            '/webhooks/whatsapp',
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $body
        );
    }

    /** Meta's envelope: entry → changes → value → messages/statuses. */
    private function envelope(array $value): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '2276232413229173',
                'changes' => [[
                    'field' => 'messages',
                    'value' => array_merge([
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '15552027928',
                            'phone_number_id' => self::PHONE_NUMBER_ID,
                        ],
                    ], $value),
                ]],
            ]],
        ];
    }

    private function inboundPayload(string $body, string $id = 'wamid.TEST123'): array
    {
        return $this->envelope([
            'contacts' => [['profile' => ['name' => 'Zain'], 'wa_id' => '966501234567']],
            'messages' => [[
                'from' => '966501234567',
                'id' => $id,
                'timestamp' => (string) now()->timestamp,
                'type' => 'text',
                'text' => ['body' => $body],
            ]],
        ]);
    }

    private function statusPayload(string $id, string $status, array $errors = []): array
    {
        return $this->envelope([
            'statuses' => [array_filter([
                'id' => $id,
                'status' => $status,
                'timestamp' => (string) now()->timestamp,
                'recipient_id' => '966501234567',
                'errors' => $errors ?: null,
            ])],
        ]);
    }

    // ── Subscription handshake ───────────────────────────────────────────

    public function test_it_echoes_the_challenge_when_the_verify_token_matches(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=' . self::VERIFY_TOKEN . '&hub.challenge=abc123')
            ->assertOk()
            ->assertSee('abc123');
    }

    public function test_it_refuses_the_handshake_when_the_verify_token_is_wrong(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc123')
            ->assertForbidden();
    }

    // ── Signature verification ───────────────────────────────────────────

    public function test_it_rejects_a_request_with_an_invalid_signature(): void
    {
        $this->makeOrder();

        $this->signedPost($this->inboundPayload('1'), 'the-wrong-secret')
            ->assertForbidden();

        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_it_rejects_a_request_with_no_signature_at_all(): void
    {
        $this->postJson('/webhooks/whatsapp', $this->inboundPayload('1'))
            ->assertForbidden();
    }

    // ── Inbound replies ──────────────────────────────────────────────────

    public function test_a_confirmation_reply_confirms_the_order(): void
    {
        $order = $this->makeOrder();

        $this->signedPost($this->inboundPayload('1'))->assertSuccessful();

        $order->refresh();
        $this->assertSame(Order::WHATSAPP_CONFIRMED, $order->whatsapp_status);
        $this->assertSame(Order::CALL_CONFIRMED, $order->call_status);
        $this->assertNotNull($order->whatsapp_replied_at);
        $this->assertSame('1', $order->whatsapp_reply_message);
        $this->assertSame(['Confirmed'], $order->tags);
    }

    public function test_an_address_reply_is_flagged_for_an_agent_and_not_applied_automatically(): void
    {
        $order = $this->makeOrder();
        $originalAddress = $order->shipping_address1;

        $newAddress = 'Villa 12, King Fahd Road, Al Olaya District, Riyadh 12212';
        $this->signedPost($this->inboundPayload($newAddress))->assertSuccessful();

        $order->refresh();
        $this->assertSame(Order::WHATSAPP_REPLIED, $order->whatsapp_status);
        $this->assertSame(['Address Update Requested'], $order->tags);
        $this->assertSame($newAddress, $order->whatsapp_reply_message);

        // Parsing a free-text address is deliberately out of scope — an agent
        // applies it through the order UI.
        $this->assertSame($originalAddress, $order->shipping_address1);
    }

    public function test_a_cancellation_reply_is_flagged_for_an_agent(): void
    {
        $order = $this->makeOrder();

        $this->signedPost($this->inboundPayload('3'))->assertSuccessful();

        $order->refresh();
        $this->assertSame(Order::WHATSAPP_REPLIED, $order->whatsapp_status);
        $this->assertSame(['Cancellation Requested'], $order->tags);

        // The order is not cancelled outright — that stays a human decision.
        $this->assertSame('unfulfilled', $order->fulfillment_status);
    }

    public function test_an_unrecognised_reply_is_routed_to_review(): void
    {
        $order = $this->makeOrder();

        $this->signedPost($this->inboundPayload('???'))->assertSuccessful();

        $this->assertSame(['Needs Review'], $order->fresh()->tags);
    }

    /**
     * Meta lets a customer tap a template quick-reply button instead of typing.
     * That arrives under an entirely different key from plain text and must
     * still reach the intent classifier.
     */
    public function test_a_quick_reply_button_tap_is_classified_like_typed_text(): void
    {
        $order = $this->makeOrder();

        $payload = $this->envelope([
            'messages' => [[
                'from' => '966501234567',
                'id' => 'wamid.BUTTON1',
                'timestamp' => (string) now()->timestamp,
                'type' => 'button',
                'button' => ['payload' => 'CONFIRM', 'text' => '1'],
            ]],
        ]);

        $this->signedPost($payload)->assertSuccessful();

        $this->assertSame(Order::WHATSAPP_CONFIRMED, $order->fresh()->whatsapp_status);
    }

    public function test_a_reply_is_recorded_in_the_conversation_log(): void
    {
        $order = $this->makeOrder();

        $this->signedPost($this->inboundPayload('1'))->assertSuccessful();

        $this->assertDatabaseHas('whatsapp_messages', [
            'order_id' => $order->id,
            'direction' => WhatsAppMessage::DIRECTION_INBOUND,
            'provider_message_id' => 'wamid.TEST123',
            'body' => '1',
        ]);
    }

    public function test_a_redelivered_reply_does_not_duplicate_the_log(): void
    {
        $this->makeOrder();

        $this->signedPost($this->inboundPayload('1'))->assertSuccessful();
        $this->signedPost($this->inboundPayload('1'))->assertSuccessful();

        $this->assertDatabaseCount('whatsapp_messages', 1);
    }

    public function test_a_reply_from_an_unknown_number_is_a_no_op(): void
    {
        // Answering 2xx keeps Meta from retrying a message we will never match.
        $payload = $this->inboundPayload('1');
        $payload['entry'][0]['changes'][0]['value']['messages'][0]['from'] = '966599999999';

        $this->signedPost($payload)->assertSuccessful();

        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_a_reply_cannot_reopen_a_settled_order(): void
    {
        $order = $this->makeOrder(['whatsapp_status' => Order::WHATSAPP_GRAVEYARD]);

        $this->signedPost($this->inboundPayload('1'))->assertSuccessful();

        $this->assertSame(Order::WHATSAPP_GRAVEYARD, $order->fresh()->whatsapp_status);
    }

    /**
     * One Meta app can serve several numbers. Traffic for a number that isn't
     * ours must not be acted on, even though the signature is valid.
     */
    public function test_a_payload_for_another_phone_number_is_ignored(): void
    {
        $order = $this->makeOrder();

        $payload = $this->inboundPayload('1');
        $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = '999999999999999';

        $this->signedPost($payload)->assertSuccessful();

        $this->assertDatabaseCount('whatsapp_messages', 0);
        $this->assertSame(Order::WHATSAPP_SENT, $order->fresh()->whatsapp_status);
    }

    // ── Delivery / read receipts ─────────────────────────────────────────

    private function outboundMessage(Order $order, string $id = 'wamid.OUT1'): WhatsAppMessage
    {
        return WhatsAppMessage::create([
            'order_id' => $order->id,
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'provider_message_id' => $id,
            'template_key' => 'order_pending',
            'body' => 'Please confirm your order',
            'to_number' => self::CUSTOMER,
            'status' => 'accepted',
            'sent_at' => now(),
        ]);
    }

    public function test_a_delivered_receipt_is_recorded_on_the_message_and_the_order(): void
    {
        $order = $this->makeOrder();
        $message = $this->outboundMessage($order);

        $this->signedPost($this->statusPayload($message->provider_message_id, 'delivered'))
            ->assertSuccessful();

        $this->assertSame('delivered', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->delivered_at);
        $this->assertNotNull($order->fresh()->whatsapp_delivered_at);
    }

    public function test_a_read_receipt_is_recorded_with_its_timestamp(): void
    {
        $order = $this->makeOrder();
        $message = $this->outboundMessage($order);

        $this->signedPost($this->statusPayload($message->provider_message_id, 'read'))
            ->assertSuccessful();

        $this->assertNotNull($message->fresh()->read_at);
        $this->assertNotNull($order->fresh()->whatsapp_read_at);
    }

    public function test_a_failed_receipt_records_the_meta_error(): void
    {
        $order = $this->makeOrder();
        $message = $this->outboundMessage($order);

        $this->signedPost($this->statusPayload($message->provider_message_id, 'failed', [[
            'code' => 131026,
            'title' => 'Message undeliverable',
            'error_data' => ['details' => 'Receiver is incapable of receiving this message.'],
        ]]))->assertSuccessful();

        $message->refresh();
        $this->assertTrue($message->hasFailed());
        $this->assertSame('131026', $message->error_code);
        $this->assertSame('Receiver is incapable of receiving this message.', $message->error_message);
        $this->assertNotNull($message->failed_at);
    }

    public function test_an_undeliverable_first_message_skips_the_paid_follow_up(): void
    {
        // No point spending a second template on a number that isn't on
        // WhatsApp — it goes back to the call queue instead.
        $order = $this->makeOrder(['whatsapp_sent_at' => now()->subHours(25)]);
        $message = $this->outboundMessage($order);

        $this->signedPost($this->statusPayload($message->provider_message_id, 'failed', [[
            'code' => 131026,
            'title' => 'Message undeliverable',
        ]]))->assertSuccessful();

        $this->artisan('whatsapp:process-followups')->assertSuccessful();

        $order->refresh();
        $this->assertSame(Order::WHATSAPP_FAILED, $order->whatsapp_status);
        $this->assertSame(['WhatsApp Unreachable'], $order->tags);
    }

    public function test_a_status_for_an_unknown_message_is_a_no_op(): void
    {
        $this->signedPost($this->statusPayload('wamid.NOPE', 'delivered'))
            ->assertSuccessful();
    }

    /**
     * Meta batches: a single POST can carry a reply and a receipt at once, and
     * both have to be processed rather than only the first array found.
     */
    public function test_messages_and_statuses_in_one_batch_are_both_processed(): void
    {
        $order = $this->makeOrder();
        $message = $this->outboundMessage($order);

        $payload = $this->envelope([
            'messages' => [[
                'from' => '966501234567',
                'id' => 'wamid.BATCH1',
                'timestamp' => (string) now()->timestamp,
                'type' => 'text',
                'text' => ['body' => '1'],
            ]],
            'statuses' => [[
                'id' => $message->provider_message_id,
                'status' => 'delivered',
                'timestamp' => (string) now()->timestamp,
            ]],
        ]);

        $this->signedPost($payload)->assertSuccessful();

        $this->assertSame(Order::WHATSAPP_CONFIRMED, $order->fresh()->whatsapp_status);
        $this->assertSame('delivered', $message->fresh()->status);
    }
}
