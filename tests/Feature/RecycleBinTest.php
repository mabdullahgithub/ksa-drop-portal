<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureRecycleBinUnlocked;
use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The recycle bin surfaces records that were already being soft-deleted but
 * had no UI. These tests cover the parts that are easy to get silently wrong:
 * the permission split across the inventory tab, orders hidden by the
 * shopify_visible global scope, and the two unique-constraint collisions that
 * would otherwise make a restore fail.
 */
class RecycleBinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['delete orders', 'delete client', 'delete inventory', 'view orders', ...self::BIN_PERMISSIONS] as $permission) {
            Permission::findOrCreate($permission);
        }

        // The bin's data routes sit behind a PIN; the tests below are about
        // what the bin does once open, so unlock by default. The lock itself
        // is covered by RecycleBinLockTest.
        $this->withSession([EnsureRecycleBinUnlocked::SESSION_KEY => now()]);
    }

    private const BIN_PERMISSIONS = ['view recycle bin', 'restore recycle bin', 'purge recycle bin'];

    /**
     * A user with full use of the bin plus $permissions. Most tests here are
     * about per-entity scoping, which the delete permissions decide.
     */
    private function userWith(array $permissions): User
    {
        return $this->userWithOnly([...self::BIN_PERMISSIONS, ...$permissions]);
    }

    /** A user with exactly $permissions and nothing else. */
    private function userWithOnly(array $permissions): User
    {
        $role = Role::create(['name' => 'role-' . uniqid()]);
        $role->givePermissionTo($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeClient(array $attributes = []): Client
    {
        return Client::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'client_types' => ['fulfilment'],
            'company_name' => 'Acme Trading',
            // client_id on the model is a read accessor over short_id.
            'short_id' => 'ACME' . substr(uniqid(), -6),
            'status' => 'active',
        ], $attributes));
    }

    private function makeShipment(Order $order, string $status): Shipment
    {
        return Shipment::create([
            'order_id' => $order->id,
            'courier' => 'jnt_express',
            'tracking_number' => 'TRACK' . substr(uniqid(), -8),
            'txlogistic_id' => 'TX' . substr(uniqid(), -10),
            'status' => $status,
        ]);
    }

    private function makeOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-' . uniqid(),
            'customer_name' => 'Sara',
        ], $attributes));
    }

    // ------------------------------------------------------------ permissions

    public function test_delete_permissions_alone_do_not_open_the_bin(): void
    {
        $this->actingAs($this->userWithOnly(['delete orders', 'delete client', 'delete inventory']))
            ->get('/recycle-bin')
            ->assertForbidden();
    }

    public function test_the_view_permission_opens_the_bin(): void
    {
        $this->actingAs($this->userWithOnly(['view recycle bin', 'delete inventory']))
            ->get('/recycle-bin')
            ->assertOk();
    }

    public function test_a_tab_still_needs_that_entitys_delete_permission(): void
    {
        $this->actingAs($this->userWith(['view orders']))
            ->getJson('/api/recycle-bin/orders')
            ->assertForbidden();
    }

    public function test_restoring_needs_the_restore_permission(): void
    {
        $order = $this->makeOrder();
        $order->delete();

        $this->actingAs($this->userWithOnly(['view recycle bin', 'purge recycle bin', 'delete orders']))
            ->postJson('/api/recycle-bin/orders/restore', ['ids' => [$order->id]])
            ->assertForbidden();

        $this->assertSoftDeleted($order);
    }

    public function test_purging_needs_the_purge_permission(): void
    {
        $order = $this->makeOrder();
        $order->delete();

        $user = $this->userWithOnly(['view recycle bin', 'restore recycle bin', 'delete orders']);

        $this->actingAs($user)
            ->postJson('/api/recycle-bin/orders/purge', ['ids' => [$order->id]])
            ->assertForbidden();
        $this->actingAs($user)
            ->postJson('/api/recycle-bin/orders/purge-all')
            ->assertForbidden();

        $this->assertNotNull(Order::withTrashed()->find($order->id));
    }

    public function test_delete_inventory_alone_does_not_expose_deleted_client_products(): void
    {
        $client = $this->makeClient();
        ClientProduct::create([
            'client_id' => $client->id,
            'product_code' => 'ACME-001',
            'name' => 'Client stock item',
        ])->delete();

        Product::create(['handle' => 'catalog-item', 'title' => 'Catalog item'])->delete();

        $response = $this->actingAs($this->userWith(['delete inventory']))
            ->getJson('/api/recycle-bin/inventory')
            ->assertOk();

        $names = array_column($response->json('data'), 'name');

        $this->assertContains('Catalog item', $names);
        $this->assertNotContains('Client stock item', $names);
    }

    public function test_delete_client_alone_does_not_expose_deleted_catalog_products(): void
    {
        $client = $this->makeClient();
        ClientProduct::create([
            'client_id' => $client->id,
            'product_code' => 'ACME-001',
            'name' => 'Client stock item',
        ])->delete();

        Product::create(['handle' => 'catalog-item', 'title' => 'Catalog item'])->delete();

        $response = $this->actingAs($this->userWith(['delete client']))
            ->getJson('/api/recycle-bin/inventory')
            ->assertOk();

        $names = array_column($response->json('data'), 'name');

        $this->assertContains('Client stock item', $names);
        $this->assertNotContains('Catalog item', $names);
    }

    public function test_purging_a_catalog_product_is_refused_without_delete_inventory(): void
    {
        $product = Product::create(['handle' => 'catalog-item', 'title' => 'Catalog item']);
        $product->delete();

        $this->actingAs($this->userWith(['delete client']))
            ->postJson('/api/recycle-bin/inventory/purge', ['ids' => ["product:{$product->id}"]])
            ->assertOk();

        // The id was silently dropped rather than acted on, so the row survives.
        $this->assertNotNull(Product::withTrashed()->find($product->id));
    }

    // ---------------------------------------------------------------- orders

    public function test_deleted_orders_appear_in_the_bin_and_can_be_restored(): void
    {
        $order = $this->makeOrder();
        $order->delete();

        $user = $this->userWith(['delete orders']);

        $this->actingAs($user)
            ->getJson('/api/recycle-bin/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order_number', $order->order_number);

        $this->actingAs($user)
            ->postJson('/api/recycle-bin/orders/restore', ['ids' => [$order->id]])
            ->assertOk()
            ->assertJsonPath('restored_count', 1);

        $this->assertNull(Order::find($order->id)->deleted_at);
    }

    /**
     * The shopify_visible global scope hides non-approved Shopify orders. A
     * plain onlyTrashed() would leave them invisible in the bin forever.
     */
    public function test_a_trashed_pending_review_order_is_still_listed_in_the_bin(): void
    {
        $order = $this->makeOrder(['shopify_sync_status' => 'pending_review']);
        $order->delete();

        $this->actingAs($this->userWith(['delete orders']))
            ->getJson('/api/recycle-bin/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order_number', $order->order_number);
    }

    public function test_the_bin_never_lists_live_orders(): void
    {
        $this->makeOrder();
        $this->makeOrder(['shopify_sync_status' => 'pending_review']);

        $this->actingAs($this->userWith(['delete orders']))
            ->getJson('/api/recycle-bin/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_deleting_an_order_requires_the_delete_orders_permission(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->userWith(['view orders']))
            ->deleteJson("/api/orders/{$order->id}")
            ->assertForbidden();

        $this->assertNull($order->fresh()->deleted_at);
    }

    public function test_an_order_with_an_active_shipment_is_refused(): void
    {
        $order = $this->makeOrder();
        $this->makeShipment($order, 'in_transit');

        $this->actingAs($this->userWith(['delete orders']))
            ->deleteJson("/api/orders/{$order->id}")
            ->assertStatus(422);

        $this->assertNull($order->fresh()->deleted_at);
    }

    public function test_bulk_delete_reports_the_orders_it_skipped(): void
    {
        $deletable = $this->makeOrder();
        $blocked = $this->makeOrder();
        $this->makeShipment($blocked, 'in_transit');

        $this->actingAs($this->userWith(['delete orders']))
            ->postJson('/api/orders/bulk-delete', ['order_ids' => [$deletable->id, $blocked->id]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 1)
            ->assertJsonPath('requested_count', 2)
            ->assertJsonCount(1, 'blocked');

        $this->assertNotNull($deletable->fresh()->deleted_at);
        $this->assertNull($blocked->fresh()->deleted_at);
    }

    public function test_a_delivered_shipment_does_not_block_deletion(): void
    {
        $order = $this->makeOrder();
        $this->makeShipment($order, 'delivered');

        $this->actingAs($this->userWith(['delete orders']))
            ->deleteJson("/api/orders/{$order->id}")
            ->assertOk();

        $this->assertNotNull($order->fresh()->deleted_at);
    }

    // --------------------------------------------------------------- clients

    public function test_deleting_a_client_leaves_its_orders_visible_with_the_client_name(): void
    {
        $client = $this->makeClient(['company_name' => 'Orphan Co']);
        $order = $this->makeOrder(['client_id' => $client->id]);

        $client->delete();

        // Order::client() is withTrashed(), so the name still resolves.
        $this->assertNotNull(Order::find($order->id));
        $this->assertSame('Orphan Co', Order::find($order->id)->client->company_name);
    }

    public function test_clients_can_be_restored_from_the_bin(): void
    {
        $client = $this->makeClient();
        $client->delete();

        $user = $this->userWith(['delete client']);

        $this->actingAs($user)
            ->getJson('/api/recycle-bin/clients')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($user)
            ->postJson('/api/recycle-bin/clients/restore', ['ids' => [$client->id]])
            ->assertOk()
            ->assertJsonPath('restored_count', 1);

        $this->assertNull(Client::find($client->id)->deleted_at);
    }

    // ------------------------------------------------------------- inventory

    public function test_a_client_product_can_be_restored_from_the_bin(): void
    {
        $client = $this->makeClient(['short_id' => 'ACME']);

        $product = ClientProduct::create([
            'client_id' => $client->id,
            'product_code' => 'ACME-001',
            'name' => 'First',
        ]);
        $product->delete();

        $this->actingAs($this->userWith(['delete client']))
            ->postJson('/api/recycle-bin/inventory/restore', ['ids' => ["client_product:{$product->id}"]])
            ->assertOk()
            ->assertJsonPath('restored_count', 1);

        $this->assertNull(ClientProduct::find($product->id)->deleted_at);
        $this->assertSame('ACME-001', ClientProduct::find($product->id)->product_code);
    }

    public function test_the_product_code_generator_skips_codes_held_by_trashed_rows(): void
    {
        $client = $this->makeClient(['short_id' => 'ACME']);

        ClientProduct::create([
            'client_id' => $client->id,
            'product_code' => 'ACME-001',
            'name' => 'Trashed',
        ])->delete();

        $this->assertSame('ACME-002', ClientProduct::generateProductCode($client));
    }

    public function test_restore_is_blocked_while_the_parent_client_is_still_deleted(): void
    {
        $client = $this->makeClient();
        $product = ClientProduct::create([
            'client_id' => $client->id,
            'product_code' => 'ACME-001',
            'name' => 'Stranded',
        ]);

        $product->delete();
        $client->delete();

        $this->actingAs($this->userWith(['delete client']))
            ->postJson('/api/recycle-bin/inventory/restore', ['ids' => ["client_product:{$product->id}"]])
            ->assertOk()
            ->assertJsonPath('restored_count', 0)
            ->assertJsonCount(1, 'blocked');

        $this->assertNotNull(ClientProduct::withTrashed()->find($product->id)->deleted_at);
    }

    /**
     * Catalog and client-stock ids collide, so the bin addresses rows by a
     * composite key. Purging one must not touch the other.
     */
    public function test_composite_ids_keep_the_two_inventory_models_apart(): void
    {
        $client = $this->makeClient();

        $catalog = Product::create(['handle' => 'catalog-item', 'title' => 'Catalog item']);
        $stock = ClientProduct::create([
            'client_id' => $client->id,
            'product_code' => 'ACME-001',
            'name' => 'Client stock item',
        ]);

        // Line them up on the same id so a naive implementation would confuse them.
        $this->assertSame($catalog->id, $stock->id);

        $catalog->delete();
        $stock->delete();

        $this->actingAs($this->userWith(['delete inventory', 'delete client']))
            ->postJson('/api/recycle-bin/inventory/purge', ['ids' => ["product:{$catalog->id}"]])
            ->assertOk()
            ->assertJsonPath('purged_count', 1);

        $this->assertNull(Product::withTrashed()->find($catalog->id));
        $this->assertNotNull(ClientProduct::withTrashed()->find($stock->id));
    }

    /**
     * client_product_images rows are removed by the FK cascade, but the files
     * on disk are only cleaned by the observer -- which a mass forceDelete on
     * the builder would skip entirely.
     */
    public function test_purging_a_client_product_removes_its_uploaded_images(): void
    {
        Storage::fake('public');

        $client = $this->makeClient();
        $product = ClientProduct::create([
            'client_id' => $client->id,
            'product_code' => 'ACME-001',
            'name' => 'With images',
        ]);

        UploadedFile::fake()->image('photo.jpg')->storeAs("client-products/{$product->id}", 'photo.jpg', 'public');
        Storage::disk('public')->assertExists("client-products/{$product->id}/photo.jpg");

        $product->delete();

        $this->actingAs($this->userWith(['delete client']))
            ->postJson('/api/recycle-bin/inventory/purge', ['ids' => ["client_product:{$product->id}"]])
            ->assertOk()
            ->assertJsonPath('purged_count', 1);

        Storage::disk('public')->assertMissing("client-products/{$product->id}/photo.jpg");
    }

    public function test_emptying_the_inventory_bin_still_cleans_up_files(): void
    {
        Storage::fake('public');

        $client = $this->makeClient();
        $product = ClientProduct::create([
            'client_id' => $client->id,
            'product_code' => 'ACME-001',
            'name' => 'With images',
        ]);

        UploadedFile::fake()->image('photo.jpg')->storeAs("client-products/{$product->id}", 'photo.jpg', 'public');
        $product->delete();

        $this->actingAs($this->userWith(['delete client']))
            ->postJson('/api/recycle-bin/inventory/purge-all')
            ->assertOk();

        Storage::disk('public')->assertMissing("client-products/{$product->id}/photo.jpg");
    }

    public function test_counts_only_report_what_the_caller_may_see(): void
    {
        $client = $this->makeClient();
        $client->delete();
        $this->makeOrder()->delete();

        $this->actingAs($this->userWith(['delete orders']))
            ->getJson('/api/recycle-bin/counts')
            ->assertOk()
            ->assertJsonPath('orders', 1)
            ->assertJsonPath('clients', 0);
    }

    public function test_emptying_the_orders_bin_removes_everything_in_it(): void
    {
        $this->makeOrder()->delete();
        $this->makeOrder(['shopify_sync_status' => 'pending_review'])->delete();
        $live = $this->makeOrder();

        $this->actingAs($this->userWith(['delete orders']))
            ->postJson('/api/recycle-bin/orders/purge-all')
            ->assertOk()
            ->assertJsonPath('purged_count', 2);

        $this->assertSame(0, Order::withoutGlobalScopes()->onlyTrashed()->count());
        $this->assertNotNull(Order::find($live->id));
    }
}
