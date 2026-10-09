<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureWhatsAppUnlocked;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Support\WhatsAppMessaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The messaging on/off switch on the WhatsApp page. Off, nothing is sent to a
 * customer and the screens are told to hide what WhatsApp added; the flow
 * itself, on and off, is covered by WhatsAppOrderConfirmationTest and
 * MetaWhatsAppWebhookTest.
 */
class WhatsAppMessagingToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view whatsapp', 'reply whatsapp', 'edit apps'] as $permission) {
            Permission::findOrCreate($permission);
        }
        Role::findOrCreate('client');

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]], 200)]);
    }

    private function staff(array $permissions = ['view whatsapp', 'reply whatsapp', 'edit apps']): User
    {
        $role = Role::create(['name' => 'role-' . uniqid()]);
        $role->givePermissionTo($this->permissions($permissions));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function unlocked(): self
    {
        return $this->withSession([EnsureWhatsAppUnlocked::SESSION_KEY => now()]);
    }

    public function test_messaging_ships_switched_off(): void
    {
        $this->assertFalse(WhatsAppMessaging::enabled());
    }

    public function test_it_can_be_switched_on_and_off_from_the_whatsapp_page(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->unlocked()
            ->putJson('/api/whatsapp/messaging', ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('enabled', true);
        $this->assertTrue(WhatsAppMessaging::enabled());

        $this->actingAs($user)
            ->putJson('/api/whatsapp/messaging', ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('enabled', false);
        $this->assertFalse(WhatsAppMessaging::enabled());
    }

    public function test_switching_it_needs_edit_apps(): void
    {
        $this->actingAs($this->staff(['view whatsapp', 'reply whatsapp']))->unlocked()
            ->putJson('/api/whatsapp/messaging', ['enabled' => true])
            ->assertForbidden();

        $this->assertFalse(WhatsAppMessaging::enabled());
    }

    public function test_switching_it_needs_the_pin(): void
    {
        $this->actingAs($this->staff())
            ->putJson('/api/whatsapp/messaging', ['enabled' => true])
            ->assertStatus(423);

        $this->assertFalse(WhatsAppMessaging::enabled());
    }

    public function test_the_screens_are_told_which_way_it_is_set(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->get('/whatsapp')
            ->assertInertia(fn ($page) => $page->where('whatsappMessaging', false));

        WhatsAppMessaging::set(true);

        $this->actingAs($user)->get('/whatsapp')
            ->assertInertia(fn ($page) => $page->where('whatsappMessaging', true));
    }

    public function test_clients_never_see_it_on(): void
    {
        WhatsAppMessaging::set(true);

        $client = User::factory()->create();
        $client->assignRole('client');
        Client::create([
            'user_id' => $client->id,
            'company_name' => 'Test Client',
            'short_id' => 'TST',
            'client_types' => ['fulfilment'],
            'portal_features' => ['orders'],
        ]);

        $this->actingAs($client)->get('/notifications')
            ->assertInertia(fn ($page) => $page->where('whatsappMessaging', false));

        $keys = collect($this->actingAs($client)->getJson('/api/connectors/enabled')->json('connectors'))->pluck('key');
        $this->assertNotContains('whatsapp', $keys);
    }

    public function test_an_agent_reply_is_refused_while_it_is_off(): void
    {
        $order = Order::create([
            'order_number' => 'KSA-OFF-1',
            'customer_phone' => '0501234567',
            'call_status' => Order::CALL_NO_ANSWER,
            'whatsapp_status' => Order::WHATSAPP_REPLIED,
            'whatsapp_phone_e164' => '+966501234567',
            'whatsapp_sent_at' => now()->subHour(),
            'whatsapp_replied_at' => now()->subMinutes(5),
        ]);
        WhatsAppMessage::create([
            'order_id' => $order->id,
            'direction' => WhatsAppMessage::DIRECTION_INBOUND,
            'provider_message_id' => 'wamid.IN1',
            'body' => 'Yes',
            'from_number' => '+966501234567',
            'status' => 'received',
        ]);

        $this->actingAs($this->staff())->unlocked()
            ->postJson("/api/whatsapp/conversations/{$order->id}/reply", ['body' => 'Thanks!'])
            ->assertStatus(409);

        Http::assertNothingSent();
        $this->assertSame(0, $order->whatsappMessages()->where('direction', WhatsAppMessage::DIRECTION_OUTBOUND)->count());
    }
}
