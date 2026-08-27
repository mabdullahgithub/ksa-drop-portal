<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The read model behind the WhatsApp inbox: the conversation list, the thread,
 * and the filter counts.
 */
class WhatsAppInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie's registrar cache is process-level and survives RefreshDatabase's
        // rollback — see ConnectorSettingsRevealTest for the same guard.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin')->givePermissionTo(Permission::findOrCreate('view orders'));
    }

    private function actingAsAgent(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user);

        return $user;
    }

    private function makeConversation(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'KSA-' . random_int(10000, 99999),
            'customer_name' => 'Zain Al Otaibi',
            'customer_phone' => '0501234567',
            'shipping_city' => 'Riyadh',
            'shipping_address1' => 'Al Suwaidi District',
            'currency' => 'SAR',
            'total' => 150.0,
            'payment_method' => 'cod',
            'financial_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'call_status' => Order::CALL_NO_ANSWER,
            'whatsapp_status' => Order::WHATSAPP_SENT,
            'whatsapp_phone_e164' => '+966501234567',
            'whatsapp_sent_at' => now()->subHours(2),
        ], $overrides));
    }

    private function addMessage(Order $order, array $overrides = []): WhatsAppMessage
    {
        // `created_at` is not fillable (and shouldn't be), so a caller wanting a
        // specific thread order has to set it after the insert.
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $message = WhatsAppMessage::create(array_merge([
            'order_id' => $order->id,
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'twilio_sid' => 'SM' . random_int(100000, 999999),
            'template_key' => 'order_pending',
            'body' => 'Please confirm your order',
            'to_number' => '+966501234567',
            'status' => 'delivered',
            'sent_at' => now()->subHours(2),
            'delivered_at' => now()->subHours(2),
        ], $overrides));

        if ($createdAt) {
            $message->forceFill(['created_at' => $createdAt])->save();
        }

        return $message;
    }

    // ── Access ───────────────────────────────────────────────────────────

    public function test_the_inbox_requires_authentication(): void
    {
        $this->getJson('/api/whatsapp/conversations')->assertUnauthorized();
    }

    public function test_a_user_without_order_permission_is_denied(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/whatsapp/conversations')->assertForbidden();
    }

    // ── Conversation list ────────────────────────────────────────────────

    public function test_it_lists_only_orders_that_entered_the_whatsapp_flow(): void
    {
        $this->actingAsAgent();

        $inFlow = $this->makeConversation();
        $this->addMessage($inFlow);

        // Never called, so never messaged — must not appear in the inbox.
        $this->makeConversation([
            'order_number' => 'KSA-NOFLOW',
            'call_status' => Order::CALL_NOT_CALLED,
            'whatsapp_status' => null,
            'whatsapp_sent_at' => null,
        ]);

        $response = $this->getJson('/api/whatsapp/conversations')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.order_number', $inFlow->order_number);
    }

    public function test_a_row_carries_the_preview_and_delivery_state_the_list_renders(): void
    {
        $this->actingAsAgent();

        $order = $this->makeConversation();
        $this->addMessage($order, ['status' => 'read', 'read_at' => now()->subHour()]);
        $this->addMessage($order, [
            'direction' => WhatsAppMessage::DIRECTION_INBOUND,
            'template_key' => null,
            'body' => 'Yes please deliver tomorrow',
            'status' => 'received',
            'created_at' => now(),
        ]);

        $row = $this->getJson('/api/whatsapp/conversations')->assertOk()->json('data.0');

        $this->assertSame('Yes please deliver tomorrow', $row['last_message']);
        $this->assertSame('inbound', $row['last_message_direction']);
        // Ticks render from the newest *outbound* message, not the newest message.
        $this->assertSame('read', $row['last_outbound_status']);
        $this->assertSame(2, $row['message_count']);
    }

    public function test_replied_conversations_are_flagged_as_needing_attention(): void
    {
        $this->actingAsAgent();

        $this->makeConversation(['whatsapp_status' => Order::WHATSAPP_REPLIED]);
        $this->makeConversation(['order_number' => 'KSA-CONF', 'whatsapp_status' => Order::WHATSAPP_CONFIRMED]);

        $rows = collect($this->getJson('/api/whatsapp/conversations')->assertOk()->json('data'));

        $this->assertTrue($rows->firstWhere('whatsapp_status', Order::WHATSAPP_REPLIED)['needs_attention']);
        $this->assertFalse($rows->firstWhere('whatsapp_status', Order::WHATSAPP_CONFIRMED)['needs_attention']);
    }

    public function test_it_filters_by_status(): void
    {
        $this->actingAsAgent();

        $this->makeConversation(['whatsapp_status' => Order::WHATSAPP_SENT]);
        $this->makeConversation(['order_number' => 'KSA-GRAVE', 'whatsapp_status' => Order::WHATSAPP_GRAVEYARD]);

        $this->getJson('/api/whatsapp/conversations?status=graveyard')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', 'KSA-GRAVE');
    }

    public function test_the_needs_attention_filter_returns_only_unresolved_replies(): void
    {
        $this->actingAsAgent();

        $this->makeConversation(['whatsapp_status' => Order::WHATSAPP_REPLIED]);
        $this->makeConversation(['order_number' => 'KSA-SENT', 'whatsapp_status' => Order::WHATSAPP_SENT]);

        $this->getJson('/api/whatsapp/conversations?status=needs_attention')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.whatsapp_status', Order::WHATSAPP_REPLIED);
    }

    public function test_it_searches_by_name_phone_and_order_number(): void
    {
        $this->actingAsAgent();

        $target = $this->makeConversation(['order_number' => 'KSA-77777', 'customer_name' => 'Fatima Noor']);
        $this->makeConversation(['order_number' => 'KSA-11111', 'customer_name' => 'Omar Said']);

        foreach (['Fatima', 'KSA-77777', '966501234567'] as $term) {
            $data = $this->getJson('/api/whatsapp/conversations?search=' . urlencode($term))
                ->assertOk()
                ->json('data');

            $this->assertContains(
                $target->order_number,
                array_column($data, 'order_number'),
                "Search for '{$term}' did not return the target conversation"
            );
        }
    }

    // ── Thread ───────────────────────────────────────────────────────────

    public function test_it_returns_the_thread_oldest_first_with_receipts(): void
    {
        $this->actingAsAgent();

        $order = $this->makeConversation();
        $this->addMessage($order, ['body' => 'First message', 'created_at' => now()->subHours(3)]);
        $this->addMessage($order, [
            'direction' => WhatsAppMessage::DIRECTION_INBOUND,
            'template_key' => null,
            'body' => 'Reply from customer',
            'status' => 'received',
            'created_at' => now()->subHour(),
        ]);

        $response = $this->getJson("/api/whatsapp/conversations/{$order->id}")->assertOk();

        $response->assertJsonPath('messages.0.body', 'First message');
        $response->assertJsonPath('messages.1.body', 'Reply from customer');
        $response->assertJsonPath('messages.1.direction', 'inbound');
        $this->assertNotNull($response->json('messages.0.delivered_at'));
    }

    public function test_the_thread_carries_the_order_context_the_panel_renders(): void
    {
        $this->actingAsAgent();

        $order = $this->makeConversation();
        $this->addMessage($order);

        $response = $this->getJson("/api/whatsapp/conversations/{$order->id}")->assertOk();

        $response->assertJsonPath('order.order_number', $order->order_number);
        $response->assertJsonPath('order.shipping_city', 'Riyadh');
        $response->assertJsonPath('order.call_status', Order::CALL_NO_ANSWER);
        // COD is derived, not stored — the panel badge depends on it.
        $response->assertJsonPath('order.is_cod', true);
    }

    public function test_an_order_that_never_entered_the_flow_has_no_conversation(): void
    {
        $this->actingAsAgent();

        $order = $this->makeConversation(['whatsapp_status' => null, 'whatsapp_sent_at' => null]);

        $this->getJson("/api/whatsapp/conversations/{$order->id}")->assertNotFound();
    }

    // ── Stats ────────────────────────────────────────────────────────────

    public function test_stats_count_each_status_for_the_filter_tabs(): void
    {
        $this->actingAsAgent();

        $this->makeConversation(['whatsapp_status' => Order::WHATSAPP_SENT]);
        $this->makeConversation(['order_number' => 'KSA-A', 'whatsapp_status' => Order::WHATSAPP_REPLIED]);
        $this->makeConversation(['order_number' => 'KSA-B', 'whatsapp_status' => Order::WHATSAPP_CONFIRMED]);
        $this->makeConversation(['order_number' => 'KSA-C', 'whatsapp_status' => Order::WHATSAPP_CONFIRMED]);

        $this->getJson('/api/whatsapp/stats')
            ->assertOk()
            ->assertJson([
                'all' => 4,
                'sent' => 1,
                'replied' => 1,
                'needs_attention' => 1,
                'confirmed' => 2,
                'graveyard' => 0,
            ]);
    }
}
