<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureRecycleBinUnlocked;
use App\Models\Client;
use App\Models\DeletionLog;
use App\Models\Order;
use App\Models\User;
use App\Support\ClientDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Every delete records who did it, from which IP and on what machine. The
 * per-row columns answer it for anything still in the bin; deletion_logs keeps
 * the trail after a purge destroys the row.
 */
class DeletionAuditTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['delete orders', 'delete client', 'delete inventory', 'view recycle bin', 'restore recycle bin', 'purge recycle bin'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $this->withSession([EnsureRecycleBinUnlocked::SESSION_KEY => now()]);
    }

    private function admin(): User
    {
        $role = Role::create(['name' => 'role-' . uniqid()]);
        $role->givePermissionTo(['delete orders', 'delete client', 'delete inventory', 'view recycle bin', 'restore recycle bin', 'purge recycle bin']);

        $user = User::factory()->create(['name' => 'Ops Admin']);
        $user->assignRole($role);

        return $user;
    }

    public function test_deleting_an_order_records_who_from_where_and_on_what(): void
    {
        $user = $this->admin();
        $order = Order::create(['order_number' => 'ORD-AUDIT-1']);

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->withHeaders(['User-Agent' => self::CHROME_MAC])
            ->deleteJson("/api/orders/{$order->id}")
            ->assertOk();

        $trashed = Order::withoutGlobalScopes()->withTrashed()->find($order->id);

        $this->assertSame($user->id, $trashed->deleted_by);
        $this->assertSame('203.0.113.42', $trashed->deleted_ip);
        $this->assertSame(self::CHROME_MAC, $trashed->deleted_user_agent);
        $this->assertStringContainsString('Chrome 140', $trashed->deleted_device);
        $this->assertStringContainsString('macOS', $trashed->deleted_device);
    }

    public function test_the_recycle_bin_listing_exposes_the_actor(): void
    {
        $user = $this->admin();
        $order = Order::create(['order_number' => 'ORD-AUDIT-2']);

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeaders(['User-Agent' => self::CHROME_MAC])
            ->deleteJson("/api/orders/{$order->id}");

        $this->actingAs($user)
            ->getJson('/api/recycle-bin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.deleted_by.name', 'Ops Admin')
            ->assertJsonPath('data.0.deleted_by.ip', '198.51.100.7')
            ->assertJsonPath('data.0.deleted_by.user_agent', self::CHROME_MAC);
    }

    public function test_restoring_clears_the_audit_columns(): void
    {
        $user = $this->admin();
        $order = Order::create(['order_number' => 'ORD-AUDIT-3']);
        $order->delete();

        $this->actingAs($user)
            ->postJson('/api/recycle-bin/orders/restore', ['ids' => [$order->id]])
            ->assertOk();

        $restored = Order::withoutGlobalScopes()->find($order->id);

        $this->assertNull($restored->deleted_by);
        $this->assertNull($restored->deleted_ip);
        $this->assertNull($restored->deleted_user_agent);
    }

    /**
     * The whole reason the log table exists: a purge takes the row and its
     * audit columns with it.
     */
    public function test_the_log_outlives_a_permanently_deleted_record(): void
    {
        $user = $this->admin();
        $order = Order::create(['order_number' => 'ORD-AUDIT-4']);
        $order->delete();

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->withHeaders(['User-Agent' => self::CHROME_MAC])
            ->postJson('/api/recycle-bin/orders/purge', ['ids' => [$order->id]])
            ->assertOk();

        $this->assertNull(Order::withoutGlobalScopes()->withTrashed()->find($order->id));

        $log = DeletionLog::where('action', 'purged')->where('subject_id', $order->id)->first();

        $this->assertNotNull($log);
        $this->assertSame('Order', $log->subject_type);
        $this->assertSame('ORD-AUDIT-4', $log->subject_label);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('203.0.113.99', $log->ip);
        $this->assertSame(self::CHROME_MAC, $log->user_agent);
    }

    public function test_the_full_lifecycle_is_logged_once_each(): void
    {
        $user = $this->admin();
        $order = Order::create(['order_number' => 'ORD-AUDIT-5']);

        $this->actingAs($user)->deleteJson("/api/orders/{$order->id}");
        $this->actingAs($user)->postJson('/api/recycle-bin/orders/restore', ['ids' => [$order->id]]);
        $this->actingAs($user)->deleteJson("/api/orders/{$order->id}");
        $this->actingAs($user)->postJson('/api/recycle-bin/orders/purge', ['ids' => [$order->id]]);

        $actions = DeletionLog::where('subject_id', $order->id)
            ->where('subject_type', 'Order')
            ->orderBy('id')
            ->pluck('action')
            ->all();

        // forceDelete() also fires deleting/deleted, so a naive observer would
        // log an extra "deleted" alongside the purge.
        $this->assertSame(['deleted', 'restored', 'deleted', 'purged'], $actions);
    }

    public function test_client_deletes_are_audited_too(): void
    {
        $user = $this->admin();
        $client = Client::create([
            'user_id' => User::factory()->create()->id,
            'client_types' => ['fulfilment'],
            'company_name' => 'Audited Co',
            'short_id' => 'AUD001',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->withHeaders(['User-Agent' => self::CHROME_MAC])
            ->deleteJson("/api/clients/{$client->id}")
            ->assertOk();

        $trashed = Client::withTrashed()->find($client->id);

        $this->assertSame($user->id, $trashed->deleted_by);
        $this->assertSame('192.0.2.10', $trashed->deleted_ip);
    }

    /**
     * Scheduled commands and queued jobs delete records too, with no request
     * and no signed-in user. That must not blow up.
     */
    public function test_a_delete_outside_a_request_is_recorded_without_an_actor(): void
    {
        $order = Order::create(['order_number' => 'ORD-AUDIT-6']);
        $order->delete();

        $trashed = Order::withoutGlobalScopes()->withTrashed()->find($order->id);

        $this->assertNull($trashed->deleted_by);

        $log = DeletionLog::where('subject_id', $order->id)->where('action', 'deleted')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
    }

    public function test_user_agent_parsing(): void
    {
        $this->assertStringContainsString('Chrome 140', ClientDevice::describe(self::CHROME_MAC));
        $this->assertStringContainsString('Desktop', ClientDevice::describe(self::CHROME_MAC));

        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
        $this->assertStringContainsString('iOS', ClientDevice::describe($iphone));
        $this->assertStringContainsString('Mobile', ClientDevice::describe($iphone));

        // Edge and Opera both also claim to be Chrome; order of matching matters.
        $edge = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0';
        $this->assertStringContainsString('Edge 140', ClientDevice::describe($edge));
        $this->assertStringContainsString('Windows', ClientDevice::describe($edge));

        $this->assertNull(ClientDevice::describe(null));
        $this->assertNull(ClientDevice::describe('   '));
    }
}
