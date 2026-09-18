<?php

namespace Tests\Feature;

use App\Jobs\ProcessShopifyWebhookJob;
use App\Mail\Shopify\ShopifyStoreConnectedMail;
use App\Mail\Shopify\ShopifyStoreDisconnectedMail;
use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\User;
use App\Services\ShopifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The client's portal user is emailed when their Shopify store is linked to
 * their account, and again when it is unlinked — from the portal or by
 * uninstalling the app in Shopify admin.
 */
class ShopifyMerchantMailTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP = 'mystore.myshopify.com';

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');

        config([
            'services.shopify.key'    => 'test-api-key',
            'services.shopify.secret' => 'test-shopify-secret',
        ]);

        Mail::fake();
    }

    public function test_claiming_the_store_sends_the_connected_email(): void
    {
        $this->makeConnection();
        $user = $this->makeClientUser();

        $this->actingAs($user)
            ->postJson('/portal/api/shopify/claim', [
                'shop'        => self::SHOP,
                'claim_token' => app(ShopifyService::class)->makeClaimToken(self::SHOP),
            ])
            ->assertOk();

        Mail::assertQueued(ShopifyStoreConnectedMail::class, fn (ShopifyStoreConnectedMail $mail) => $mail->hasTo($user->email)
            && $mail->shopDomain === self::SHOP);
    }

    public function test_disconnecting_from_the_portal_sends_the_disconnected_email(): void
    {
        $user = $this->makeClientUser();
        $this->makeConnection(['client_id' => $user->client->id]);

        $this->actingAs($user)
            ->deleteJson('/portal/api/shopify/disconnect')
            ->assertOk();

        Mail::assertQueued(ShopifyStoreDisconnectedMail::class, fn (ShopifyStoreDisconnectedMail $mail) => $mail->hasTo($user->email)
            && $mail->reason === 'portal');
    }

    public function test_uninstalling_a_linked_store_sends_the_disconnected_email_once(): void
    {
        $user = $this->makeClientUser();
        $this->makeConnection(['client_id' => $user->client->id]);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'app/uninstalled', ['myshopify_domain' => self::SHOP]);
        // Shopify redelivers webhooks; the second one must not email again.
        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'app/uninstalled', ['myshopify_domain' => self::SHOP]);

        Mail::assertQueued(ShopifyStoreDisconnectedMail::class, 1);
        Mail::assertQueued(ShopifyStoreDisconnectedMail::class, fn (ShopifyStoreDisconnectedMail $mail) => $mail->hasTo($user->email)
            && $mail->reason === 'uninstalled');
    }

    public function test_uninstalling_an_unlinked_store_sends_nothing(): void
    {
        $this->makeConnection();

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'app/uninstalled', ['myshopify_domain' => self::SHOP]);

        Mail::assertNothingQueued();
    }

    public function test_emails_render(): void
    {
        $client = $this->makeClientUser()->client;

        $connected = new ShopifyStoreConnectedMail($client, self::SHOP);
        $connected->assertSeeInHtml('Your Shopify store is connected');
        $connected->assertSeeInText(self::SHOP);

        $uninstalled = new ShopifyStoreDisconnectedMail($client, self::SHOP, 'uninstalled');
        $uninstalled->assertSeeInHtml('was uninstalled from your Shopify admin');
        $uninstalled->assertSeeInText('reinstall');

        $portal = new ShopifyStoreDisconnectedMail($client, self::SHOP, 'portal');
        $portal->assertSeeInHtml('was disconnected from your');
        $portal->assertSeeInText('Test Client');
    }

    private function makeClientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        Client::create([
            'user_id'         => $user->id,
            'company_name'    => 'Test Client',
            'short_id'        => 'TST',
            'client_types'    => ['fulfilment'],
            'portal_features' => ['orders'],
        ]);

        return $user->refresh();
    }

    private function makeConnection(array $attributes = []): ClientShopifyConnection
    {
        return ClientShopifyConnection::create(array_merge([
            'client_id'        => null,
            'shop_domain'      => self::SHOP,
            'access_token'     => 'tok',
            'refresh_token'    => 'ref',
            'token_expires_at' => now()->addHour(),
            'scope'            => 'read_orders',
            'status'           => 'active',
            'connected_at'     => now(),
        ], $attributes));
    }
}
