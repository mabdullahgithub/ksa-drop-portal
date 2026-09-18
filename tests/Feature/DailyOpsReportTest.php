<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Models\ShopifySyncFailure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coverage for the daily Slack report.
 *
 * Two properties carry the weight. The first is the day boundary: the report
 * covers a Riyadh day, and orders cluster either side of local midnight, so an
 * off-by-three-hours window would quietly attribute the evening surge to the
 * wrong date every single morning. The second is that alerts stay conditional —
 * a report that warns about the same thing daily is one nobody reads, so a clean
 * day must produce a message with no warnings in it at all.
 */
class DailyOpsReportTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://hooks.slack.com/services/T000/B000/secret';

    private string $logs;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');

        // The report counts webhook outcomes and errors out of the log files, so
        // point both channels at an empty directory of our own. Left on the real
        // one, every assertion here would depend on whatever happens to be
        // sitting in storage/logs on the machine running the suite.
        $this->logs = sys_get_temp_dir() . '/ksadrop-report-test-' . uniqid();
        mkdir($this->logs, 0777, true);

        config([
            'services.slack.ops_webhook'    => self::WEBHOOK,
            'services.slack.ops_timezone'   => 'Asia/Riyadh',
            'logging.channels.shopify.path' => $this->logs . '/shopify.log',
            'logging.channels.daily.path'   => $this->logs . '/laravel.log',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logs . '/*') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->logs);

        parent::tearDown();
    }

    /**
     * Laravel keeps the first stub that matches a URL, so faking in setUp would
     * make the rejection case unfakeable. Each test states the response it wants.
     */
    private function fakeSlack(int $status = 200, string $body = 'ok'): void
    {
        Http::fake([self::WEBHOOK => Http::response($body, $status)]);
    }

    private function makeClient(string $shortId = 'TST'): Client
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return Client::create([
            'user_id'         => $user->id,
            'company_name'    => 'Test Client',
            'short_id'        => $shortId,
            'client_types'    => ['fulfilment'],
            'portal_features' => ['orders'],
        ]);
    }

    private function makeOrder(string $createdAtUtc, array $attributes = []): Order
    {
        static $sequence = 1000;
        $sequence++;

        $order = Order::create(array_merge([
            'order_number'   => 'TEST' . $sequence,
            'total'          => 100,
            'shipping_city'  => 'Riyadh',
        ], $attributes));

        // created_at is set by the framework; the report keys off it, so the
        // tests have to place orders on the clock deliberately.
        $order->forceFill(['created_at' => $createdAtUtc])->saveQuietly();

        return $order->refresh();
    }

    /** The payload Slack was sent, decoded. */
    private function postedBlocks(): array
    {
        $request = collect(Http::recorded())->last();

        $this->assertNotNull($request, 'Nothing was posted to Slack.');

        return $request[0]->data()['blocks'];
    }

    private function flatten(array $blocks): string
    {
        return json_encode($blocks, JSON_UNESCAPED_UNICODE);
    }

    public function test_it_reports_the_riyadh_day_not_the_utc_day(): void
    {
        $this->fakeSlack();

        // 2026-09-06 in Riyadh runs 2026-09-05 21:00 UTC → 2026-09-06 21:00 UTC.
        $this->makeOrder('2026-09-05 21:30:00');   // 00:30 Riyadh on the 6th — in
        $this->makeOrder('2026-09-06 18:00:00');   // 21:00 Riyadh on the 6th — in
        $this->makeOrder('2026-09-05 20:30:00');   // 23:30 Riyadh on the 5th — out
        $this->makeOrder('2026-09-06 21:30:00');   // 00:30 Riyadh on the 7th — out

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $blocks = $this->flatten($this->postedBlocks());

        $this->assertStringContainsString('*Orders*\n2 ', $blocks);
    }

    public function test_it_reports_the_peak_hour_in_local_time(): void
    {
        $this->fakeSlack();

        // Three orders at 21:00 Riyadh (18:00 UTC), one at 03:00 Riyadh.
        $this->makeOrder('2026-09-06 18:05:00');
        $this->makeOrder('2026-09-06 18:20:00');
        $this->makeOrder('2026-09-06 18:55:00');
        $this->makeOrder('2026-09-06 00:10:00');

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $this->assertStringContainsString('21:00 (3)', $this->flatten($this->postedBlocks()));
    }

    public function test_a_clean_day_carries_no_warnings(): void
    {
        $this->fakeSlack();

        $this->makeOrder('2026-09-06 18:00:00');

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $blocks = $this->flatten($this->postedBlocks());

        $this->assertStringNotContainsString('Needs attention', $blocks);
        $this->assertStringContainsString('No sync failures', $blocks);
    }

    public function test_it_flags_orders_with_no_shipping_city(): void
    {
        $this->fakeSlack();

        $this->makeOrder('2026-09-06 18:00:00', ['shipping_city' => '-']);
        $this->makeOrder('2026-09-06 18:10:00', ['shipping_city' => '']);
        $this->makeOrder('2026-09-06 18:20:00', ['shipping_city' => 'Jeddah']);

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $blocks = $this->flatten($this->postedBlocks());

        $this->assertStringContainsString('Needs attention', $blocks);
        $this->assertStringContainsString('2 of 3 orders have no shipping city', $blocks);
    }

    public function test_it_flags_stores_that_installed_but_never_connected(): void
    {
        $this->fakeSlack();

        ClientShopifyConnection::create([
            'client_id'    => null,
            'shop_domain'  => 'unclaimed.myshopify.com',
            'access_token' => 'token',
            'status'       => 'active',
        ]);

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $this->assertStringContainsString(
            '1 store(s) installed the app but never connected it',
            $this->flatten($this->postedBlocks())
        );
    }

    public function test_it_flags_unresolved_sync_failures(): void
    {
        $this->fakeSlack();

        ShopifySyncFailure::create([
            'shop_domain'      => 'shop.myshopify.com',
            'topic'            => 'orders/create',
            'shopify_order_id' => '123',
            'payload'          => ['id' => 123],
            'reason'           => ShopifySyncFailure::REASON_EXCEPTION,
            'status'           => ShopifySyncFailure::STATUS_PENDING,
            'attempts'         => 1,
            'next_attempt_at'  => CarbonImmutable::parse('2026-09-06 18:00:00'),
        ]);

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $blocks = $this->flatten($this->postedBlocks());

        $this->assertStringContainsString('sync failure(s) still unresolved', $blocks);
    }

    public function test_it_counts_orders_awaiting_client_review(): void
    {
        $this->fakeSlack();

        // These are hidden from every normal query by a global scope. They still
        // arrived, and a report that omits them understates the day.
        $this->makeOrder('2026-09-06 18:00:00', ['shopify_sync_status' => 'pending_review']);

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $this->assertStringContainsString('*Orders*\n1 ', $this->flatten($this->postedBlocks()));
    }

    public function test_it_shows_a_multiplier_when_the_day_beats_the_weekly_average(): void
    {
        $this->fakeSlack();

        // Seven prior days at one order each, then ten on the reported day.
        for ($i = 1; $i <= 7; $i++) {
            $this->makeOrder(CarbonImmutable::parse('2026-09-05 21:00:00')->subDays($i)->toDateTimeString());
        }

        for ($i = 0; $i < 10; $i++) {
            $this->makeOrder('2026-09-06 18:0' . $i . ':00');
        }

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $this->assertStringContainsString('10.0× avg', $this->flatten($this->postedBlocks()));
    }

    public function test_it_counts_webhooks_across_both_log_files_of_a_local_day(): void
    {
        $this->fakeSlack();

        // A Riyadh day runs 21:00 UTC to 21:00 UTC, so it always straddles two
        // of the daily-rotated log files. Lines outside that window belong to a
        // different report.
        file_put_contents($this->logs . '/shopify-2026-09-05.log', implode("\n", [
            '[2026-09-05 20:00:00] production.INFO: Shopify order synced from webhook {"shop":"a"}',
            '[2026-09-05 22:00:00] production.INFO: Shopify order synced from webhook {"shop":"a"}',
        ]) . "\n");

        file_put_contents($this->logs . '/shopify-2026-09-06.log', implode("\n", [
            '[2026-09-06 10:00:00] production.INFO: Shopify order synced from webhook {"shop":"a"}',
            '[2026-09-06 11:00:00] production.WARNING: Shopify webhook parked — no active linked connection {"shop":"b"}',
            '[2026-09-06 22:00:00] production.INFO: Shopify order synced from webhook {"shop":"a"}',
        ]) . "\n");

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $blocks = $this->flatten($this->postedBlocks());

        $this->assertStringContainsString('2 webhook(s) synced, 1 parked', $blocks);
    }

    public function test_it_counts_application_errors_within_the_reported_day(): void
    {
        $this->fakeSlack();

        file_put_contents($this->logs . '/laravel-2026-09-06.log', implode("\n", [
            '[2026-09-06 03:00:00] production.ERROR: J&T Express API failed after 3 attempts',
            '[2026-09-06 04:00:00] production.CRITICAL: something gave way',
            '[2026-09-06 05:00:00] production.INFO: this is not an error',
            '[2026-09-06 22:00:00] production.ERROR: belongs to the next report',
        ]) . "\n");

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $this->assertStringContainsString(
            '2 application error(s) logged',
            $this->flatten($this->postedBlocks())
        );
    }

    public function test_it_names_the_destination_channel_when_it_posts(): void
    {
        $this->fakeSlack();

        config(['services.slack.ops_channel' => 'daily-reports-portal']);

        $this->artisan('report:daily', ['--date' => '2026-09-06'])
            ->expectsOutputToContain('#daily-reports-portal')
            ->assertSuccessful();
    }

    public function test_dry_run_prints_the_report_without_posting(): void
    {
        $this->fakeSlack();

        $this->makeOrder('2026-09-06 18:00:00');

        $this->artisan('report:daily', ['--date' => '2026-09-06', '--dry-run' => true])->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_it_fails_loudly_when_no_webhook_is_configured(): void
    {
        $this->fakeSlack();

        config(['services.slack.ops_webhook' => null]);

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_fails_when_slack_rejects_the_post(): void
    {
        $this->fakeSlack(400, 'invalid_payload');

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertFailed();
    }
}
