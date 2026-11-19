<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The one-time $19 plan, billed through the Shopify Billing API. */
class ShopifyPlanPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP   = 'mystore.myshopify.com';
    private const SECRET = 'test-shopify-secret';
    private const GID    = 'gid://shopify/AppPurchaseOneTime/42';

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');

        config([
            'services.shopify.key'    => 'test-api-key',
            'services.shopify.secret' => self::SECRET,
        ]);
    }

    private function connection(array $overrides = []): ClientShopifyConnection
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $client = Client::create([
            'user_id' => $user->id, 'company_name' => 'Test Client', 'short_id' => 'TST',
            'client_types' => ['fulfilment'], 'portal_features' => ['orders'],
        ]);

        return ClientShopifyConnection::create(array_merge([
            'client_id'        => $client->id,
            'shop_domain'      => self::SHOP,
            'access_token'     => 'tok-123',
            'token_expires_at' => now()->addHour(),
            'status'           => 'active',
        ], $overrides));
    }

    private function headers(): array
    {
        $b64 = fn (array $d) => rtrim(strtr(base64_encode(json_encode($d)), '+/', '-_'), '=');
        $h = $b64(['alg' => 'HS256', 'typ' => 'JWT']);
        $p = $b64([
            'iss' => 'https://' . self::SHOP . '/admin', 'dest' => 'https://' . self::SHOP,
            'aud' => 'test-api-key', 'sub' => '1', 'exp' => time() + 60, 'nbf' => time() - 5,
            'iat' => time() - 5, 'jti' => 'j', 'sid' => 's',
        ]);
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", self::SECRET, true)), '+/', '-_'), '=');

        return ['Authorization' => "Bearer $h.$p.$sig"];
    }

    public function test_purchase_creates_a_60_dollar_one_time_charge_and_returns_confirmation_url(): void
    {
        $connection = $this->connection();

        Http::fake(['*' => Http::response(['data' => ['appPurchaseOneTimeCreate' => [
            'appPurchaseOneTime' => ['id' => self::GID],
            'confirmationUrl'    => 'https://mystore.myshopify.com/admin/charges/confirm',
            'userErrors'         => [],
        ]]])]);

        $this->postJson('/embedded/shopify/api/billing/purchase', [], $this->headers())
            ->assertOk()
            ->assertJsonPath('confirmation_url', 'https://mystore.myshopify.com/admin/charges/confirm')
            ->assertJsonPath('plan.price', 19)
            ->assertJsonPath('plan.currency', 'USD')
            ->assertJsonPath('purchased', false);

        Http::assertSent(function (Request $request) {
            $vars = json_decode(json_encode($request->data()['variables'] ?? []), true);

            return str_contains($request->data()['query'], 'appPurchaseOneTimeCreate')
                && $vars['price'] === ['amount' => '19.00', 'currencyCode' => 'USD']
                && str_contains($vars['returnUrl'], '/admin/apps/test-api-key');
        });

        $this->assertSame('pending', $connection->fresh()->plan_status);
        $this->assertSame(self::GID, $connection->fresh()->plan_purchase_id);
    }

    public function test_show_marks_plan_active_once_shopify_reports_it_active(): void
    {
        $connection = $this->connection(['plan_purchase_id' => self::GID, 'plan_status' => 'pending']);

        Http::fake(['*' => Http::response(['data' => ['node' => ['status' => 'ACTIVE']]])]);

        $this->getJson('/embedded/shopify/api/billing', $this->headers())
            ->assertOk()
            ->assertJsonPath('purchased', true);

        $this->assertTrue($connection->fresh()->hasPaidPlan());
        $this->assertNotNull($connection->fresh()->plan_purchased_at);
    }

    public function test_declined_purchase_can_be_retried(): void
    {
        $this->connection(['plan_purchase_id' => self::GID, 'plan_status' => 'pending']);

        Http::fake(['*' => Http::response(['data' => ['node' => ['status' => 'DECLINED']]])]);

        $this->getJson('/embedded/shopify/api/billing', $this->headers())
            ->assertOk()
            ->assertJsonPath('status', 'declined')
            ->assertJsonPath('purchased', false);
    }

    public function test_already_purchased_store_is_never_charged_twice(): void
    {
        $this->connection(['plan_purchase_id' => self::GID, 'plan_status' => 'active']);

        Http::fake();

        $this->postJson('/embedded/shopify/api/billing/purchase', [], $this->headers())
            ->assertOk()
            ->assertJsonPath('purchased', true)
            ->assertJsonMissingPath('confirmation_url');

        Http::assertNothingSent();
    }

    public function test_requires_a_session_token(): void
    {
        $this->getJson('/embedded/shopify/api/billing')->assertUnauthorized();
    }
}
