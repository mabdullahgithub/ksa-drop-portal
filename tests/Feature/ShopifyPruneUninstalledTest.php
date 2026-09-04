<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coverage for closing connections whose store has uninstalled the app.
 *
 * app/uninstalled is the delivery that fails most often in our own stats, and a
 * missed one leaves a row `active` with a token Shopify has already revoked. The
 * command asks Shopify directly instead of waiting to be told — so the property
 * that matters most is the negative one: nothing short of an outright 401 may
 * disconnect a store, because disconnecting a live one silently stops a paying
 * merchant's orders.
 */
class ShopifyPruneUninstalledTest extends TestCase
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
    }

    private function makeConnection(string $shop = self::SHOP): ClientShopifyConnection
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $client = Client::create([
            'user_id'         => $user->id,
            'company_name'    => 'Test Client',
            'short_id'        => 'TST',
            'client_types'    => ['fulfilment'],
            'portal_features' => ['orders'],
        ]);

        return ClientShopifyConnection::create([
            'client_id'           => $client->id,
            'shop_domain'         => $shop,
            'access_token'        => 'tok-123',
            'refresh_token'       => 'ref-123',
            'token_expires_at'    => now()->addHour(),
            'status'              => 'active',
            'webhooks_registered' => true,
            'connected_at'        => now(),
        ]);
    }

    public function test_it_closes_a_connection_whose_store_uninstalled(): void
    {
        $connection = $this->makeConnection();

        // The token is live, so no renewal happens; Shopify refuses the call.
        Http::fake(['https://' . self::SHOP . '/admin/api/*' => Http::response(
            ['errors' => '[API] Invalid API key or access token (unrecognized login or wrong password)'],
            401
        )]);

        $this->artisan('shopify:prune-uninstalled')
            ->expectsOutputToContain('uninstalled')
            ->assertSuccessful();

        $connection->refresh();

        $this->assertSame('disconnected', $connection->status);
        $this->assertNull($connection->access_token);
        $this->assertNull($connection->refresh_token);
        // Shopify drops every subscription on uninstall; a stale true here made
        // the next reinstall skip re-registration and come back silent.
        $this->assertFalse($connection->webhooks_registered);
    }

    public function test_it_leaves_a_live_store_alone(): void
    {
        $connection = $this->makeConnection();

        Http::fake(['https://' . self::SHOP . '/admin/api/*' => Http::response(
            ['data' => ['shop' => ['name' => 'My Store']]]
        )]);

        $this->artisan('shopify:prune-uninstalled')->assertSuccessful();

        $connection->refresh();
        $this->assertSame('active', $connection->status);
        $this->assertNotNull($connection->access_token);
    }

    #[DataProvider('inconclusiveResponses')]
    public function test_it_never_disconnects_on_anything_short_of_a_401(int $status): void
    {
        // The property this command lives or dies by. A rate limit, a 5xx or a
        // gateway error must read as "ask again later", never as "gone" —
        // disconnecting a paying merchant on a blip stops their orders dead,
        // which is far worse than leaving a dead row in place for another day.
        $connection = $this->makeConnection();

        Http::fake(['https://' . self::SHOP . '/admin/api/*' => Http::response('', $status)]);

        $this->artisan('shopify:prune-uninstalled')
            ->expectsOutputToContain('could not determine')
            ->assertSuccessful();

        $connection->refresh();
        $this->assertSame('active', $connection->status, "status {$status} must not disconnect the store");
        $this->assertNotNull($connection->access_token);
    }

    public static function inconclusiveResponses(): array
    {
        return [
            'rate limited'        => [429],
            'server error'        => [500],
            'bad gateway'         => [502],
            'service unavailable' => [503],
            'gateway timeout'     => [504],
            'forbidden'           => [403],
        ];
    }

    public function test_dry_run_reports_without_changing_anything(): void
    {
        $connection = $this->makeConnection();

        Http::fake(['https://' . self::SHOP . '/admin/api/*' => Http::response([], 401)]);

        $this->artisan('shopify:prune-uninstalled --dry-run')
            ->expectsOutputToContain('would be closed')
            ->assertSuccessful();

        $connection->refresh();
        $this->assertSame('active', $connection->status);
        $this->assertNotNull($connection->access_token);
    }

    public function test_it_skips_already_disconnected_connections(): void
    {
        $connection = $this->makeConnection();
        $connection->update(['status' => 'disconnected', 'access_token' => null]);

        Http::fake();

        $this->artisan('shopify:prune-uninstalled')->assertSuccessful();

        Http::assertNothingSent();
    }

    // ── Expired access tokens ────────────────────────────────────────────────
    //
    //  Shopify's access tokens are short-lived and ours are normally already
    //  expired; the refresh token is the credential that lasts. Testing the
    //  stored token directly reported every store as uninstalled — live paying
    //  merchants included — because an expired token is refused with the same
    //  401 as a revoked one.

    public function test_an_expired_token_on_a_live_store_is_renewed_not_disconnected(): void
    {
        $connection = $this->makeConnection();
        $connection->update(['token_expires_at' => now()->subDay()]);

        Http::fake([
            'https://' . self::SHOP . '/admin/oauth/access_token' => Http::response([
                'access_token'             => 'tok-fresh',
                'refresh_token'            => 'ref-fresh',
                'expires_in'               => 86400,
                'refresh_token_expires_in' => 7776000,
            ]),
            'https://' . self::SHOP . '/admin/api/*' => Http::response(
                ['data' => ['shop' => ['name' => 'My Store']]]
            ),
        ]);

        $this->artisan('shopify:prune-uninstalled')->assertSuccessful();

        $connection->refresh();

        $this->assertSame('active', $connection->status);
        // Renewed rather than discarded — a healthy row is left better than found.
        $this->assertSame('tok-fresh', $connection->access_token);
        $this->assertFalse($connection->isTokenExpired());
    }

    public function test_an_expired_token_whose_grant_shopify_refuses_is_disconnected(): void
    {
        // The genuine uninstall signature: the refresh token is still within its
        // ninety days, and Shopify refuses it anyway because the grant is gone.
        $connection = $this->makeConnection();
        $connection->update(['token_expires_at' => now()->subDay()]);

        Http::fake([
            'https://' . self::SHOP . '/admin/oauth/access_token' => Http::response(
                ['error' => 'invalid_grant'], 400
            ),
        ]);

        $this->artisan('shopify:prune-uninstalled')
            ->expectsOutputToContain('uninstalled')
            ->assertSuccessful();

        $connection->refresh();
        $this->assertSame('disconnected', $connection->status);
        $this->assertNull($connection->access_token);
    }

    public function test_a_failing_token_endpoint_leaves_the_store_alone(): void
    {
        // Shopify having a bad moment is not a merchant leaving.
        $connection = $this->makeConnection();
        $connection->update(['token_expires_at' => now()->subDay()]);

        Http::fake([
            'https://' . self::SHOP . '/admin/oauth/access_token' => Http::response('', 503),
        ]);

        $this->artisan('shopify:prune-uninstalled')
            ->expectsOutputToContain('could not determine')
            ->assertSuccessful();

        $this->assertSame('active', $connection->fresh()->status);
    }

    public function test_an_expired_refresh_token_is_not_treated_as_an_uninstall(): void
    {
        // The merchant has to reconnect either way, but a lapsed refresh window
        // is not proof the app was removed, so nothing is concluded.
        $connection = $this->makeConnection();
        $connection->update([
            'token_expires_at'         => now()->subDay(),
            'refresh_token_expires_at' => now()->subDay(),
        ]);

        Http::fake();

        $this->artisan('shopify:prune-uninstalled')
            ->expectsOutputToContain('could not determine')
            ->assertSuccessful();

        $this->assertSame('active', $connection->fresh()->status);
        Http::assertNothingSent();
    }
}
