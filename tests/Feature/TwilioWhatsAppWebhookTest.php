<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

/**
 * Twilio's two inbound webhooks: customer replies and delivery/read receipts.
 *
 * Mirrors ImileTrackingWebhookTest in shape, but every request here carries a
 * real HMAC signature — Twilio, unlike iMile, actually documents one.
 */
class TwilioWhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH_TOKEN = 'test-auth-token';
    private const CUSTOMER = '+966501234567';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.twilio', [
            'account_sid' => 'ACtest',
            'auth_token' => self::AUTH_TOKEN,
            'whatsapp_from' => 'whatsapp:+17372212163',
            'template_sid_order_pending' => null,
            'template_sid_followup' => null,
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

    /** POST with a correctly computed X-Twilio-Signature, as Twilio would send it. */
    private function signedPost(string $path, array $payload)
    {
        $url = config('app.url') . $path;
        $signature = (new RequestValidator(self::AUTH_TOKEN))->computeSignature($url, $payload);

        return $this->withServerVariables(['HTTP_X_TWILIO_SIGNATURE' => $signature])
            ->post($path, $payload);
    }

    private function inboundPayload(string $body, string $sid = 'SM123'): array
    {
        return [
            'MessageSid' => $sid,
            'From' => 'whatsapp:' . self::CUSTOMER,
            'To' => 'whatsapp:+17372212163',
            'Body' => $body,
        ];
    }

    // ── Signature verification ───────────────────────────────────────────

    public function test_it_rejects_a_request_with_an_invalid_signature(): void
    {
        $this->makeOrder();

        $this->withServerVariables(['HTTP_X_TWILIO_SIGNATURE' => 'obviously-wrong'])
            ->post('/webhooks/twilio/whatsapp', $this->inboundPayload('1'))
            ->assertForbidden();

        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_it_rejects_a_request_with_no_signature_at_all(): void
    {
        $this->post('/webhooks/twilio/whatsapp', $this->inboundPayload('1'))
            ->assertForbidden();
    }

    // ── Inbound replies ──────────────────────────────────────────────────

    public function test_a_confirmation_reply_confirms_the_order(): void
    {
        $order = $this->makeOrder();

        $this->signedPost('/webhooks/twilio/whatsapp', $this->inboundPayload('1'))
            ->assertSuccessful();

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
        $this->signedPost('/webhooks/twilio/whatsapp', $this->inboundPayload($newAddress))
            ->assertSuccessful();

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

        $this->signedPost('/webhooks/twilio/whatsapp', $this->inboundPayload('3'))
            ->assertSuccessful();

        $order->refresh();
        $this->assertSame(Order::WHATSAPP_REPLIED, $order->whatsapp_status);
        $this->assertSame(['Cancellation Requested'], $order->tags);

        // The order is not cancelled outright — that stays a human decision.
        $this->assertSame('unfulfilled', $order->fulfillment_status);
    }

    public function test_an_unrecognised_reply_is_routed_to_review(): void
    {
        $order = $this->makeOrder();

        $this->signedPost('/webhooks/twilio/whatsapp', $this->inboundPayload('???'))
            ->assertSuccessful();

        $this->assertSame(['Needs Review'], $order->fresh()->tags);
    }

    public function test_a_reply_is_recorded_in_the_conversation_log(): void
    {
        $order = $this->makeOrder();

        $this->signedPost('/webhooks/twilio/whatsapp', $this->inboundPayload('1'))
            ->assertSuccessful();

        $this->assertDatabaseHas('whatsapp_messages', [
            'order_id' => $order->id,
            'direction' => WhatsAppMessage::DIRECTION_INBOUND,
            'twilio_sid' => 'SM123',
            'body' => '1',
        ]);
    }

    public function test_a_redelivered_reply_does_not_duplicate_the_log(): void
    {
        $this->makeOrder();

        $this->signedPost('/webhooks/twilio/whatsapp', $this->inboundPayload('1'))->assertSuccessful();
        $this->signedPost('/webhooks/twilio/whatsapp', $this->inboundPayload('1'))->assertSuccessful();

        $this->assertDatabaseCount('whatsapp_messages', 1);
    }

    public function test_a_reply_from_an_unknown_number_is_a_no_op(): void
    {
        // Answering 2xx keeps Twilio from retrying a message we will never match.
        $payload = array_merge($this->inboundPayload('1'), ['From' => 'whatsapp:+966599999999']);

        $this->signedPost('/webhooks/twilio/whatsapp', $payload)->assertSuccessful();

        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_a_reply_cannot_reopen_a_settled_order(): void
    {
        $order = $this->makeOrder(['whatsapp_status' => Order::WHATSAPP_GRAVEYARD]);

        $this->signedPost('/webhooks/twilio/whatsapp', $this->inboundPayload('1'))->assertSuccessful();

        $this->assertSame(Order::WHATSAPP_GRAVEYARD, $order->fresh()->whatsapp_status);
    }

    // ── Delivery / read receipts ─────────────────────────────────────────

    private function outboundMessage(Order $order, string $sid = 'SMout1'): WhatsAppMessage
    {
        return WhatsAppMessage::create([
            'order_id' => $order->id,
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'twilio_sid' => $sid,
            'template_key' => 'order_pending',
            'body' => 'Please confirm your order',
            'to_number' => self::CUSTOMER,
            'status' => 'queued',
            'sent_at' => now(),
        ]);
    }

    public function test_a_delivered_receipt_is_recorded_on_the_message_and_the_order(): void
    {
        $order = $this->makeOrder();
        $message = $this->outboundMessage($order);

        $this->signedPost('/webhooks/twilio/whatsapp/status', [
            'MessageSid' => $message->twilio_sid,
            'MessageStatus' => 'delivered',
        ])->assertSuccessful();

        $this->assertSame('delivered', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->delivered_at);
        $this->assertNotNull($order->fresh()->whatsapp_delivered_at);
    }

    public function test_a_read_receipt_is_recorded_with_its_timestamp(): void
    {
        $order = $this->makeOrder();
        $message = $this->outboundMessage($order);

        $this->signedPost('/webhooks/twilio/whatsapp/status', [
            'MessageSid' => $message->twilio_sid,
            'MessageStatus' => 'read',
        ])->assertSuccessful();

        $this->assertNotNull($message->fresh()->read_at);
        $this->assertNotNull($order->fresh()->whatsapp_read_at);
    }

    public function test_a_failed_receipt_records_the_twilio_error(): void
    {
        $order = $this->makeOrder();
        $message = $this->outboundMessage($order);

        $this->signedPost('/webhooks/twilio/whatsapp/status', [
            'MessageSid' => $message->twilio_sid,
            'MessageStatus' => 'failed',
            'ErrorCode' => '63003',
            'ErrorMessage' => 'Channel could not find To address',
        ])->assertSuccessful();

        $message->refresh();
        $this->assertTrue($message->hasFailed());
        $this->assertSame('63003', $message->error_code);
        $this->assertNotNull($message->failed_at);
    }

    public function test_an_undeliverable_first_message_skips_the_paid_follow_up(): void
    {
        // No point spending a second template on a number that isn't on
        // WhatsApp — it goes back to the call queue instead.
        $order = $this->makeOrder(['whatsapp_sent_at' => now()->subHours(25)]);
        $message = $this->outboundMessage($order);

        $this->signedPost('/webhooks/twilio/whatsapp/status', [
            'MessageSid' => $message->twilio_sid,
            'MessageStatus' => 'undelivered',
        ])->assertSuccessful();

        $this->artisan('whatsapp:process-followups')->assertSuccessful();

        $order->refresh();
        $this->assertSame(Order::WHATSAPP_FAILED, $order->whatsapp_status);
        $this->assertSame(['WhatsApp Unreachable'], $order->tags);
    }

    public function test_a_status_callback_for_an_unknown_message_is_a_no_op(): void
    {
        $this->signedPost('/webhooks/twilio/whatsapp/status', [
            'MessageSid' => 'SMnope',
            'MessageStatus' => 'delivered',
        ])->assertSuccessful();
    }
}
