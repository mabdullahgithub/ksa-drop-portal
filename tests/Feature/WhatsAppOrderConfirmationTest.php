<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppOrderMessageJob;
use App\Models\Order;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The confirmation flow's state machine: what starts it, what advances it on
 * the 24h clock, and what stops it.
 */
class WhatsAppOrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp', [
            'phone_number_id' => '1221052164432743',
            'access_token' => 'test-token',
            'app_secret' => 'test-app-secret',
            'webhook_verify_token' => 'test-verify-token',
            'template_name_order_pending' => 'order_pending',
            'template_name_followup' => 'followup',
        ]);

        // No real Graph calls from the send path.
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.TEST', 'message_status' => 'accepted']],
            ], 200),
        ]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => (string) random_int(100000, 999999),
            'customer_name' => 'Zain',
            'customer_phone' => '0501234567',
            'shipping_city' => 'Riyadh',
            'shipping_address1' => 'Al Suwaidi District',
            'currency' => 'SAR',
            'total' => 150.0,
            'payment_method' => 'cod',
            'financial_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
        ], $overrides));
    }

    public function test_marking_a_call_no_answer_starts_the_whatsapp_flow(): void
    {
        Queue::fake();

        $order = $this->makeOrder();
        $order->update(['call_status' => Order::CALL_NO_ANSWER]);

        Queue::assertPushed(SendWhatsAppOrderMessageJob::class);
    }

    public function test_other_call_outcomes_do_not_message_the_customer(): void
    {
        Queue::fake();

        foreach ([Order::CALL_CONFIRMED, Order::CALL_CANCELLED, Order::CALL_WRONG_NUMBER] as $status) {
            $this->makeOrder()->update(['call_status' => $status]);
        }

        Queue::assertNothingPushed();
    }

    public function test_creating_an_order_does_not_message_the_customer(): void
    {
        // The trigger is the call outcome, not order creation — an order that
        // an agent reaches on the first ring should never cost a message.
        Queue::fake();

        $this->makeOrder();

        Queue::assertNothingPushed();
    }

    public function test_a_second_no_answer_does_not_restart_the_conversation(): void
    {
        Queue::fake();

        $order = $this->makeOrder([
            'call_status' => Order::CALL_NO_ANSWER,
            'whatsapp_status' => Order::WHATSAPP_SENT,
            'whatsapp_sent_at' => now(),
        ]);

        // Agent calls again, still no answer.
        $order->update(['call_status' => Order::CALL_NOT_CALLED]);
        Queue::fake();
        $order->update(['call_status' => Order::CALL_NO_ANSWER]);

        Queue::assertNothingPushed();
    }

    public function test_reaching_the_customer_later_closes_an_open_conversation(): void
    {
        $order = $this->makeOrder([
            'call_status' => Order::CALL_NO_ANSWER,
            'whatsapp_status' => Order::WHATSAPP_SENT,
            'whatsapp_sent_at' => now(),
        ]);

        $order->update(['call_status' => Order::CALL_CONFIRMED]);

        $this->assertSame(Order::WHATSAPP_CONFIRMED, $order->fresh()->whatsapp_status);
    }

    public function test_the_sweep_sends_a_follow_up_after_24h_of_silence(): void
    {
        Queue::fake();

        $order = $this->makeOrder([
            'call_status' => Order::CALL_NO_ANSWER,
            'whatsapp_status' => Order::WHATSAPP_SENT,
            'whatsapp_sent_at' => now()->subHours(25),
        ]);

        $this->artisan('whatsapp:process-followups')->assertSuccessful();

        Queue::assertPushed(
            SendWhatsAppOrderMessageJob::class,
            fn ($job) => (fn () => $this->orderId)->call($job) === $order->id
                && (fn () => $this->templateKey)->call($job) === MetaWhatsAppService::TEMPLATE_FOLLOWUP
        );
    }

    public function test_the_sweep_leaves_orders_alone_before_24h(): void
    {
        Queue::fake();

        $this->makeOrder([
            'call_status' => Order::CALL_NO_ANSWER,
            'whatsapp_status' => Order::WHATSAPP_SENT,
            'whatsapp_sent_at' => now()->subHours(23),
        ]);

        $this->artisan('whatsapp:process-followups')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_a_reply_stops_the_sweep_from_following_up(): void
    {
        Queue::fake();

        $this->makeOrder([
            'call_status' => Order::CALL_NO_ANSWER,
            'whatsapp_status' => Order::WHATSAPP_REPLIED,
            'whatsapp_sent_at' => now()->subHours(30),
            'whatsapp_replied_at' => now()->subHours(20),
        ]);

        $this->artisan('whatsapp:process-followups')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_the_sweep_buries_orders_silent_for_48h(): void
    {
        $order = $this->makeOrder([
            'call_status' => Order::CALL_NO_ANSWER,
            'whatsapp_status' => Order::WHATSAPP_FOLLOWUP_SENT,
            'whatsapp_sent_at' => now()->subHours(50),
            'whatsapp_followup_sent_at' => now()->subHours(25),
        ]);

        $this->artisan('whatsapp:process-followups')->assertSuccessful();

        $order->refresh();
        $this->assertSame(Order::WHATSAPP_GRAVEYARD, $order->whatsapp_status);
        $this->assertSame(['Graveyard'], $order->tags);
    }

    public function test_the_full_24h_then_48h_progression(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-08-28 09:00:00');

        $order = $this->makeOrder(['call_status' => Order::CALL_NO_ANSWER]);

        // The observer queued the first message; simulate it having sent.
        $order->forceFill([
            'whatsapp_status' => Order::WHATSAPP_SENT,
            'whatsapp_sent_at' => now(),
        ])->saveQuietly();

        Carbon::setTestNow('2026-08-29 09:01:00'); // +24h
        $this->artisan('whatsapp:process-followups')->assertSuccessful();
        Queue::assertPushed(SendWhatsAppOrderMessageJob::class);

        $order->forceFill([
            'whatsapp_status' => Order::WHATSAPP_FOLLOWUP_SENT,
            'whatsapp_followup_sent_at' => now(),
        ])->saveQuietly();

        Carbon::setTestNow('2026-08-30 09:02:00'); // +48h
        $this->artisan('whatsapp:process-followups')->assertSuccessful();

        $this->assertSame(Order::WHATSAPP_GRAVEYARD, $order->fresh()->whatsapp_status);

        Carbon::setTestNow();
    }

    public function test_the_send_job_skips_an_order_with_an_unusable_phone_number(): void
    {
        $order = $this->makeOrder([
            'customer_phone' => 'n/a',
            'shipping_phone' => null,
            'call_status' => Order::CALL_NO_ANSWER,
        ]);

        (new SendWhatsAppOrderMessageJob($order->id))->handle(app(MetaWhatsAppService::class));

        $this->assertSame(Order::WHATSAPP_FAILED, $order->fresh()->whatsapp_status);
    }

    public function test_the_send_job_skips_an_order_whose_call_outcome_changed(): void
    {
        // Dispatched on no_answer, but an agent reached the customer before the
        // worker picked the job up.
        $order = $this->makeOrder(['call_status' => Order::CALL_CONFIRMED]);

        (new SendWhatsAppOrderMessageJob($order->id))->handle(app(MetaWhatsAppService::class));

        $this->assertNull($order->fresh()->whatsapp_status);
    }

    /**
     * The customer bought from our client's store, not from us. Naming the
     * fulfilment platform in an outbound message would expose the dropshipping
     * relationship, so no brand of ours may appear in the copy or the
     * variables — including as a fallback when an order has no client.
     */
    public function test_outbound_copy_never_names_the_fulfilment_platform(): void
    {
        $service = new MetaWhatsAppService();

        $render = function (string $templateKey) use ($service) {
            $method = new \ReflectionMethod($service, 'renderPreview');

            return $method->invoke($service, $templateKey, [
                '1' => 'Zain',
                '2' => 'KSA-1001',
                '3' => 'Al Suwaidi District, Riyadh',
            ]);
        };

        foreach ([MetaWhatsAppService::TEMPLATE_ORDER_PENDING, MetaWhatsAppService::TEMPLATE_FOLLOWUP] as $key) {
            $body = $render($key);

            foreach (['KSA Drop', 'ksadrop', config('app.name')] as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase(
                    (string) $forbidden,
                    $body,
                    "Template '{$key}' leaks the platform name '{$forbidden}'"
                );
            }

            // Every slot must still be filled — an unsubstituted {{n}} would
            // reach the customer as literal braces.
            $this->assertStringNotContainsString('{{', $body);
        }
    }

    public function test_no_template_variable_falls_back_to_our_app_name(): void
    {
        // An order with no client is exactly the case that used to substitute
        // config('app.name') into the message.
        $order = $this->makeOrder(['client_id' => null]);

        $method = new \ReflectionMethod(SendWhatsAppOrderMessageJob::class, 'variablesFor');
        $variables = $method->invoke(new SendWhatsAppOrderMessageJob($order->id), $order);

        foreach ($variables as $value) {
            $this->assertStringNotContainsStringIgnoringCase('KSA Drop', (string) $value);
            $this->assertStringNotContainsStringIgnoringCase((string) config('app.name'), (string) $value);
        }
    }
}
