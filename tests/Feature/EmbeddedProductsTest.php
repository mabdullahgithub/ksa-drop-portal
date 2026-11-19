<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmbeddedProductsTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP   = 'mystore.myshopify.com';
    private const SECRET = 'test-shopify-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');
        config(['services.shopify.key' => 'test-api-key', 'services.shopify.secret' => self::SECRET]);

        $user = User::factory()->create();
        $user->assignRole('client');
        $client = Client::create([
            'user_id' => $user->id, 'company_name' => 'Test Client', 'short_id' => 'TST',
            'client_types' => ['dropshipper'], 'portal_features' => ['orders'],
        ]);
        ClientShopifyConnection::create([
            'client_id' => $client->id, 'shop_domain' => self::SHOP, 'access_token' => 'tok',
            'token_expires_at' => now()->addHour(), 'status' => 'active',
        ]);
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

    public function test_lists_only_published_active_products_without_internal_cost(): void
    {
        Product::create(['handle' => 'a', 'title' => 'Alpha Mug', 'published' => true, 'status' => 'active',
            'variant_sku' => 'A-1', 'variant_price' => 25, 'cost_per_item' => 9]);
        Product::create(['handle' => 'b', 'title' => 'Hidden', 'published' => false, 'status' => 'active']);
        Product::create(['handle' => 'c', 'title' => 'Draft', 'published' => true, 'status' => 'draft']);

        $response = $this->getJson('/embedded/shopify/api/products', $this->headers())->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Alpha Mug')
            ->assertJsonPath('data.0.price', '25.00');
        $this->assertStringNotContainsString('cost_per_item', $response->getContent());
        $this->assertStringNotContainsString('9.00', $response->getContent());
    }

    public function test_search_filters_products(): void
    {
        Product::create(['handle' => 'a', 'title' => 'Alpha Mug', 'published' => true, 'status' => 'active']);
        Product::create(['handle' => 'b', 'title' => 'Beta Lamp', 'published' => true, 'status' => 'active']);

        $this->getJson('/embedded/shopify/api/products?search=lamp', $this->headers())
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Beta Lamp');
    }

    public function test_requires_a_session_token(): void
    {
        $this->getJson('/embedded/shopify/api/products')->assertUnauthorized();
    }

    public function test_products_shell_page_renders(): void
    {
        $this->get('/embedded/shopify/products?shop=' . self::SHOP)->assertOk()->assertSee('Products');
    }
}
