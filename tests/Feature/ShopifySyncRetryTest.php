<?php

namespace Tests\Feature;

use App\Jobs\ProcessShopifyWebhookJob;
use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopifySyncFailure;
use App\Models\User;
use App\Services\ShopifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coverage for the Shopify sync retry path: a webhook that does not become an
 * order is parked with its payload, replayed on a backoff, replayed immediately
 * when the store is connected or the merchant asks, and given up on once its
 * attempts run out — rather than disappearing into failed_jobs or a log line.
 */
class ShopifySyncRetryTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP   = 'mystore.myshopify.com';
    private const SECRET = 'test-shopify-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');

        config([
            'services.shopify.key'    => 'test-api-key',
            'services.shopify.secret' => self::SECRET,
        ]);
    }

    private function makeClient(string $shortId = 'TST'): Client
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return Client::create([
            'user_id'         => $user->id,
            'company_name'    => 'Test Client ' . $shortId,
            'short_id'        => $shortId,
            'client_types'    => ['fulfilment'],
            'portal_features' => ['orders'],
        ]);
    }

    /**
     * Drain the queue the Shopify jobs are pinned to.
     *
     * Every Shopify dispatch names the `database` connection so the webhook
     * endpoint can answer Shopify immediately and the scheduled worker is
     * guaranteed to pick the job up. That makes replays genuinely asynchronous,
     * so a test that triggers one has to run the worker before asserting —
     * which is also what production does a minute later.
     */
    private function drainQueue(): void
    {
        $this->artisan('queue:work database --stop-when-empty')->assertSuccessful();
    }

    private function connect(Client $client): ClientShopifyConnection
    {
        return ClientShopifyConnection::create([
            'shop_domain'  => self::SHOP,
            'client_id'    => $client->id,
            'access_token' => 'tok-123',
            'status'       => 'active',
            'connected_at' => now(),
        ]);
    }

    private function orderPayload(int $id = 9001, int $number = 1042): array
    {
        return [
            'id'               => $id,
            'order_number'     => $number,
            'name'             => '#' . $number,
            'email'            => 'jane@example.com',
            'currency'         => 'SAR',
            'total_price'      => '250.00',
            'financial_status' => 'paid',
            'customer'         => ['first_name' => 'Jane', 'last_name' => 'Buyer'],
            'shipping_address' => ['phone' => '+966500000000', 'city' => 'Riyadh'],
            'line_items'       => [['name' => 'Widget', 'quantity' => 2, 'price' => '125.00']],
        ];
    }

    // ── Parking ──────────────────────────────────────────────────────────────

    public function test_an_order_for_an_unconnected_store_is_not_held_at_all(): void
    {
        // A store can install the app and never connect it to a KSA Drop
        // account. That is not a failure and nothing is kept for it: syncing
        // begins when the merchant connects, and orders placed before that stay
        // in Shopify. Holding them would mean retrying against a state no amount
        // of retrying can change.
        ClientShopifyConnection::create([
            'shop_domain'  => self::SHOP,
            'client_id'    => null,
            'access_token' => 'tok-123',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());

        $this->assertSame(0, ShopifySyncFailure::count(), 'nothing should be parked for an unconnected store');
        $this->assertSame(0, Order::withoutGlobalScope('shopify_visible')->count());
    }

    public function test_repeated_failures_for_one_order_collapse_into_a_single_row(): void
    {
        // orders/create then orders/updated for the same stuck order is one
        // problem, and must not queue up as two rows the merchant has to
        // retry separately.
        (new ProcessShopifyWebhookJob(self::SHOP, 'orders/create', $this->orderPayload()))
            ->failed(new RuntimeException('first'));
        (new ProcessShopifyWebhookJob(self::SHOP, 'orders/updated', $this->orderPayload()))
            ->failed(new RuntimeException('second'));

        $this->assertSame(1, ShopifySyncFailure::count());
        $this->assertSame('orders/updated', ShopifySyncFailure::sole()->topic);
    }

    public function test_a_job_that_throws_is_parked_with_its_payload(): void
    {
        $job = new ProcessShopifyWebhookJob(self::SHOP, 'orders/create', $this->orderPayload());

        $job->failed(new RuntimeException('Data too long for column'));

        $failure = ShopifySyncFailure::sole();

        $this->assertSame(ShopifySyncFailure::REASON_EXCEPTION, $failure->reason);
        $this->assertStringContainsString('Data too long for column', $failure->error_message);
    }

    public function test_topics_with_nothing_to_replay_are_not_parked(): void
    {
        $job = new ProcessShopifyWebhookJob(self::SHOP, 'customers/data_request', ['shop_domain' => self::SHOP]);

        $job->failed(new RuntimeException('boom'));

        $this->assertSame(0, ShopifySyncFailure::count());
    }

    // ── Replay ───────────────────────────────────────────────────────────────

    public function test_retry_sweep_replays_a_due_failure_and_clears_it(): void
    {
        $client = $this->makeClient();
        $this->connect($client);

        // A delivery we accepted and could not process — the only thing parked.
        (new ProcessShopifyWebhookJob(self::SHOP, 'orders/create', $this->orderPayload()))
            ->failed(new RuntimeException('transient database error'));

        $this->assertSame(0, Order::withoutGlobalScope('shopify_visible')->count());

        $this->travel(2)->minutes();

        $this->artisan('shopify:retry-failed-syncs')->assertSuccessful();
        $this->drainQueue();

        $order = Order::withoutGlobalScope('shopify_visible')->sole();
        $this->assertSame('9001', $order->shopify_order_id);
        $this->assertSame($client->id, $order->client_id);
        $this->assertCount(1, $order->items);

        $failure = ShopifySyncFailure::sole();
        $this->assertSame(ShopifySyncFailure::STATUS_RESOLVED, $failure->status);
        $this->assertNotNull($failure->resolved_at);
    }

    public function test_retry_sweep_leaves_a_failure_alone_until_its_backoff_elapses(): void
    {
        (new ProcessShopifyWebhookJob(self::SHOP, 'orders/create', $this->orderPayload()))
            ->failed(new RuntimeException('boom'));

        // Faked only now, so the sweep's dispatches are observable without
        // swallowing the parking that set the failure up.
        Queue::fake();
        $this->artisan('shopify:retry-failed-syncs')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(0, ShopifySyncFailure::sole()->attempts);
    }

    public function test_a_failure_is_abandoned_once_its_attempts_run_out(): void
    {
        $client = $this->makeClient();
        $this->connect($client);

        (new ProcessShopifyWebhookJob(self::SHOP, 'orders/create', $this->orderPayload()))
            ->failed(new RuntimeException('boom'));

        // Every replay fails the same way, so the budget is spent in full.
        OrderItem::creating(fn () => throw new RuntimeException('still broken'));

        for ($i = 0; $i < ShopifySyncFailure::MAX_ATTEMPTS; $i++) {
            $this->travel(2)->days();
            $this->artisan('shopify:retry-failed-syncs')->assertSuccessful();
            rescue(fn () => $this->drainQueue());
        }

        $failure = ShopifySyncFailure::sole();

        $this->assertSame(ShopifySyncFailure::STATUS_ABANDONED, $failure->status);
        $this->assertSame(ShopifySyncFailure::MAX_ATTEMPTS, $failure->attempts);
        $this->assertNull($failure->next_attempt_at);

        // And it stays given up on — no further sweep picks it back up.
        $this->travel(2)->days();
        $this->artisan('shopify:retry-failed-syncs')->assertSuccessful();
        $this->assertSame(ShopifySyncFailure::MAX_ATTEMPTS, ShopifySyncFailure::sole()->attempts);
    }

    public function test_an_order_that_syncs_on_a_later_webhook_clears_its_parked_failure(): void
    {
        $client = $this->makeClient();
        $this->connect($client);

        (new ProcessShopifyWebhookJob(self::SHOP, 'orders/create', $this->orderPayload()))
            ->failed(new RuntimeException('boom'));

        // Shopify's own retry of the same order gets through on its own — the
        // parked row is moot even though nothing replayed it.
        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/updated', $this->orderPayload());

        $this->assertSame(ShopifySyncFailure::STATUS_RESOLVED, ShopifySyncFailure::sole()->status);
    }

    public function test_claiming_the_store_brings_in_nothing_that_arrived_before_it(): void
    {
        // Syncing starts at connection. Orders placed while the store was
        // unconnected stay in Shopify — nothing was held, so nothing appears.
        $client = $this->makeClient();

        ClientShopifyConnection::create([
            'shop_domain'  => self::SHOP,
            'client_id'    => null,
            'access_token' => 'tok-123',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());

        $this->actingAs($client->user)->postJson(route('portal.shopify.claim'), [
            'shop'        => self::SHOP,
            'claim_token' => app(ShopifyService::class)->makeClaimToken(self::SHOP),
        ])->assertOk();

        $this->drainQueue();

        $this->assertSame(0, Order::withoutGlobalScope('shopify_visible')->count());
        $this->assertSame(0, ShopifySyncFailure::count());

        // From here on it syncs normally.
        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload(9002, 1043));
        $this->assertSame(1, Order::withoutGlobalScope('shopify_visible')->count());
    }

    public function test_portal_lists_only_this_clients_failures(): void
    {
        $mine      = $this->makeClient('AAA');
        $theirs    = $this->makeClient('BBB');
        $otherShop = 'otherstore.myshopify.com';

        ClientShopifyConnection::create([
            'shop_domain' => self::SHOP, 'client_id' => $mine->id,
            'access_token' => 'tok-a', 'status' => 'active', 'connected_at' => now(),
        ]);
        ClientShopifyConnection::create([
            'shop_domain' => $otherShop, 'client_id' => $theirs->id,
            'access_token' => 'tok-b', 'status' => 'active', 'connected_at' => now(),
        ]);

        ShopifySyncFailure::record(self::SHOP, 'orders/create', $this->orderPayload(9001), ShopifySyncFailure::REASON_EXCEPTION, 'mine');
        ShopifySyncFailure::record($otherShop, 'orders/create', $this->orderPayload(9002), ShopifySyncFailure::REASON_EXCEPTION, 'theirs');

        $response = $this->actingAs($mine->user)
            ->getJson(route('portal.shopify.failures.index'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('9001', $response->json('data.0.shopify_order_id'));
        // The raw webhook body carries customer PII and never goes to the browser.
        $this->assertArrayNotHasKey('payload', $response->json('data.0'));
    }

    public function test_portal_retry_queues_the_order_for_import(): void
    {
        $client = $this->makeClient();

        ClientShopifyConnection::create([
            'shop_domain' => self::SHOP, 'client_id' => $client->id,
            'access_token' => 'tok-123', 'status' => 'active', 'connected_at' => now(),
        ]);

        $failure = ShopifySyncFailure::record(
            self::SHOP, 'orders/create', $this->orderPayload(),
            ShopifySyncFailure::REASON_EXCEPTION, 'transient database error', $client->id,
        );

        $this->actingAs($client->user)
            ->postJson(route('portal.shopify.failures.retry', $failure->id))
            ->assertOk();

        $this->drainQueue();

        $this->assertSame('9001', Order::withoutGlobalScope('shopify_visible')->sole()->shopify_order_id);
        $this->assertSame(ShopifySyncFailure::STATUS_RESOLVED, $failure->fresh()->status);
    }

    public function test_portal_cannot_retry_another_clients_failure(): void
    {
        $mine   = $this->makeClient('AAA');
        $theirs = $this->makeClient('BBB');

        ClientShopifyConnection::create([
            'shop_domain' => 'otherstore.myshopify.com', 'client_id' => $theirs->id,
            'access_token' => 'tok-b', 'status' => 'active', 'connected_at' => now(),
        ]);

        $failure = ShopifySyncFailure::record(
            'otherstore.myshopify.com', 'orders/create', $this->orderPayload(),
            ShopifySyncFailure::REASON_EXCEPTION, 'not yours', $theirs->id,
        );

        $this->actingAs($mine->user)
            ->postJson(route('portal.shopify.failures.retry', $failure->id))
            ->assertStatus(404);

        $this->assertSame(0, Order::withoutGlobalScope('shopify_visible')->count());
    }

    public function test_discarding_a_failure_stops_it_being_retried(): void
    {
        $client = $this->makeClient();

        ClientShopifyConnection::create([
            'shop_domain' => self::SHOP, 'client_id' => $client->id,
            'access_token' => 'tok-123', 'status' => 'active', 'connected_at' => now(),
        ]);

        $failure = ShopifySyncFailure::record(
            self::SHOP, 'orders/create', $this->orderPayload(),
            ShopifySyncFailure::REASON_EXCEPTION, 'test order, not wanted', $client->id,
        );

        $this->actingAs($client->user)
            ->deleteJson(route('portal.shopify.failures.discard', $failure->id))
            ->assertOk();

        $this->travel(2)->days();
        $this->artisan('shopify:retry-failed-syncs')->assertSuccessful();

        $this->assertSame(0, Order::withoutGlobalScope('shopify_visible')->count());
        $this->assertSame(0, $failure->fresh()->attempts);
    }


    // ── Idempotency ──────────────────────────────────────────────────────────
    //
    //  A replay re-processes a payload that has already been through this job,
    //  sometimes days late and sometimes alongside a live delivery of the same
    //  order. Every one of these has to be safe to run twice.

    public function test_replaying_the_same_payload_does_not_duplicate_the_order_or_its_items(): void
    {
        $client = $this->makeClient();
        $this->connect($client);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());
        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());
        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/updated', $this->orderPayload());

        $this->assertSame(1, Order::withoutGlobalScope('shopify_visible')->count());
        $this->assertCount(1, Order::withoutGlobalScope('shopify_visible')->sole()->items);
    }

    public function test_a_stale_replay_does_not_walk_a_delivered_order_backwards(): void
    {
        // Fulfillment belongs to our courier flow once a shipment moves —
        // Shipment::markDelivered() sets it. Shopify keeps reporting the order
        // as unfulfilled (it was never fulfilled there), so a routine
        // orders/updated, or a replay of a payload parked days ago, would
        // otherwise flip a delivered order back to unfulfilled in the portal.
        $client = $this->makeClient();
        $this->connect($client);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());

        Order::withoutGlobalScope('shopify_visible')->sole()
            ->update(['fulfillment_status' => 'fulfilled', 'fulfilled_at' => now()]);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/updated', $this->orderPayload());

        $order = Order::withoutGlobalScope('shopify_visible')->sole();
        $this->assertSame('fulfilled', $order->fulfillment_status);
        $this->assertNotNull($order->fulfilled_at);
    }

    public function test_a_stale_replay_does_not_clear_a_local_cancellation(): void
    {
        // Shipment::markReturned() cancels the order on our side. The Shopify
        // payload carries cancelled_at = null, which would wipe both the status
        // and the timestamp.
        $client = $this->makeClient();
        $this->connect($client);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());

        Order::withoutGlobalScope('shopify_visible')->sole()
            ->update(['fulfillment_status' => 'cancelled', 'cancelled_at' => now()]);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/updated', $this->orderPayload());

        $order = Order::withoutGlobalScope('shopify_visible')->sole();
        $this->assertSame('cancelled', $order->fulfillment_status);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_shopify_can_still_move_an_order_forward_to_fulfilled(): void
    {
        // The guard must only block regressions — Shopify fulfilling an order
        // itself (merchant ships from their own side) still has to land.
        $client = $this->makeClient();
        $this->connect($client);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());

        $payload = $this->orderPayload();
        $payload['fulfillment_status'] = 'fulfilled';

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/updated', $payload);

        $this->assertSame(
            'fulfilled',
            Order::withoutGlobalScope('shopify_visible')->sole()->fulfillment_status
        );
    }

    public function test_a_failed_item_write_leaves_the_order_untouched(): void
    {
        // Items are replaced by delete-then-insert. Without a transaction a
        // failure between the two leaves the order with no items at all — and
        // since the job then fails and parks, the order sits item-less in the
        // portal until a replay happens to succeed.
        $client = $this->makeClient();
        $this->connect($client);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());
        $this->assertCount(1, Order::withoutGlobalScope('shopify_visible')->sole()->items);

        OrderItem::creating(fn () => throw new RuntimeException('insert blew up mid-replacement'));

        try {
            ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/updated', $this->orderPayload());
            $this->fail('Expected the item write to fail.');
        } catch (RuntimeException) {
            // Expected — what matters is what it left behind.
        }

        $this->assertCount(1, Order::withoutGlobalScope('shopify_visible')->sole()->items);
    }

    public function test_parking_the_same_order_twice_at_once_keeps_one_row(): void
    {
        // Two deliveries for one order can fail concurrently; both then try to
        // park it. The unique key makes one of them lose the insert, and that
        // must fold into the existing row rather than throw.
        ShopifySyncFailure::record(self::SHOP, 'orders/create', $this->orderPayload(), ShopifySyncFailure::REASON_EXCEPTION, 'first');

        ShopifySyncFailure::withoutEvents(function () {
            // Simulate the racing writer: the row exists by the time the second
            // caller tries to insert its own.
            ShopifySyncFailure::record(self::SHOP, 'orders/updated', $this->orderPayload(), ShopifySyncFailure::REASON_EXCEPTION, 'second');
        });

        $this->assertSame(1, ShopifySyncFailure::count());
        $this->assertSame('second', ShopifySyncFailure::sole()->error_message);
    }

    public function test_replaying_a_cancellation_does_not_move_the_cancellation_time(): void
    {
        $client = $this->makeClient();
        $this->connect($client);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());
        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/cancelled', ['id' => 9001]);

        $cancelledAt = Order::withoutGlobalScope('shopify_visible')->sole()->cancelled_at;

        $this->travel(3)->hours();
        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/cancelled', ['id' => 9001]);

        $order = Order::withoutGlobalScope('shopify_visible')->sole();
        $this->assertSame('cancelled', $order->fulfillment_status);
        $this->assertTrue($cancelledAt->equalTo($order->cancelled_at));
    }

    // ── Workflow tags ────────────────────────────────────────────────────────

    public function test_a_synced_order_starts_on_the_pending_tag(): void
    {
        // Every order enters the portal as Pending whatever its source; CSV
        // import and manual creation already did this, Shopify orders arrived
        // with no tag at all and sat outside the workflow.
        $client = $this->makeClient();
        $this->connect($client);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());

        $this->assertSame(['Pending'], Order::withoutGlobalScope('shopify_visible')->sole()->tags);

        // The tag row itself has to exist or the portal cannot colour or filter it.
        $this->assertDatabaseHas('tags', ['name' => 'Pending']);
    }

    public function test_it_reuses_the_orders_own_spelling_of_the_tag(): void
    {
        // Adding "Pending" next to a lowercase "pending" would show the merchant
        // two tags for one state.
        $client = $this->makeClient();
        $this->connect($client);

        $payload         = $this->orderPayload();
        $payload['tags'] = 'pending, buyease-x';

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $payload);

        $tags = Order::withoutGlobalScope('shopify_visible')->sole()->tags;

        $this->assertContains('pending', $tags);
        $this->assertNotContains('Pending', $tags);
        $this->assertContains('buyease-x', $tags);
    }

    public function test_a_later_sync_does_not_drag_the_order_back_to_pending(): void
    {
        // Tags are the portal's workflow state once an order is in the list. The
        // mapper always produces the starting set, so writing it through on
        // every orders/updated would undo the operator's work — and before
        // Pending existed it emptied the tags outright.
        $client = $this->makeClient();
        $this->connect($client);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());

        Order::withoutGlobalScope('shopify_visible')->sole()->update(['tags' => ['Confirmed']]);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/updated', $this->orderPayload());

        $this->assertSame(['Confirmed'], Order::withoutGlobalScope('shopify_visible')->sole()->tags);
    }

    public function test_the_workflow_tag_does_not_leak_into_the_merchants_own_tags(): void
    {
        // shopify_raw_tags keeps the merchant's list untouched, and that is what
        // evaluateSyncFilters reads. Adding Pending to `tags` must not reach it,
        // or a tags_exclude rule could start matching a tag we invented.
        $client     = $this->makeClient();
        $connection = $this->connect($client);
        $connection->update(['sync_filters' => ['tags_include' => ['vip']]]);

        $payload         = $this->orderPayload();
        $payload['tags'] = 'wholesale, vip';

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $payload);

        $order = Order::withoutGlobalScope('shopify_visible')->sole();

        // Passed the filter on its own tags, so it syncs normally...
        $this->assertNull($order->shopify_sync_status);
        // ...with the merchant's list intact and the workflow tag kept separate.
        $this->assertSame(['wholesale', 'vip'], $order->shopify_raw_tags);
        $this->assertSame(['Pending'], $order->tags);
    }

    // ── Sync filters ─────────────────────────────────────────────────────────
    //
    //  These could not be written before: $table->enum() left a CHECK
    //  constraint on sqlite that refused 'skipped_filtered' outright, so the
    //  main outcome of the whole sync-filter feature had never been exercised.

    public function test_an_order_the_filters_reject_is_stored_hidden_not_dropped(): void
    {
        // The record is kept — it is the receipt for having seen and rejected
        // the order — but the shopify_visible scope keeps it out of every
        // normal view.
        $client     = $this->makeClient();
        $connection = $this->connect($client);
        $connection->update(['sync_filters' => ['tags_exclude' => ['wholesale']]]);

        $payload         = $this->orderPayload();
        $payload['tags'] = 'wholesale';

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $payload);

        $order = Order::withoutGlobalScope('shopify_visible')->sole();

        $this->assertSame('skipped_filtered', $order->shopify_sync_status);
        $this->assertSame(0, Order::count(), 'a filtered order must not appear in normal views');
    }

    public function test_each_filter_dimension_can_reject_an_order(): void
    {
        $client     = $this->makeClient();
        $connection = $this->connect($client);

        $cases = [
            'financial status' => [['financial_statuses' => ['pending']], []],
            'payment method'   => [['payment_method' => 'card'], []],
            'excluded tag'     => [['tags_exclude' => ['vip']], ['tags' => 'vip']],
            'missing tag'      => [['tags_include' => ['wholesale']], []],
        ];

        $id = 9100;

        foreach ($cases as $label => [$filters, $overrides]) {
            $connection->update(['sync_filters' => $filters]);

            $payload = array_merge($this->orderPayload(++$id, $id), $overrides);

            ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $payload);

            $order = Order::withoutGlobalScope('shopify_visible')
                ->where('shopify_order_id', (string) $id)
                ->sole();

            $this->assertSame('skipped_filtered', $order->shopify_sync_status, "{$label} should have rejected the order");
        }
    }

    public function test_a_filtered_order_is_never_promoted_by_a_later_sync(): void
    {
        // Loosening the filters must not retroactively pull in an order the
        // merchant already declined — the decision is made once, on arrival.
        $client     = $this->makeClient();
        $connection = $this->connect($client);
        $connection->update(['sync_filters' => ['tags_include' => ['wholesale']]]);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());
        $this->assertSame('skipped_filtered', Order::withoutGlobalScope('shopify_visible')->sole()->shopify_sync_status);

        $connection->update(['sync_filters' => []]);
        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/updated', $this->orderPayload());

        $this->assertSame('skipped_filtered', Order::withoutGlobalScope('shopify_visible')->sole()->shopify_sync_status);
    }
}
