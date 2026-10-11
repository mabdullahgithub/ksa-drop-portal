<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\StockScan;
use App\Models\User;
use App\Services\Inventory\StockScanRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * Coverage for the daily Slack report of warehouse scans.
 *
 * Three properties carry the weight. The stock counts must be the ones each
 * scan found and left, since that is the whole point of the report. Parcels
 * must land under their own store, busiest store first. And the day boundary
 * is a Riyadh day: an off-by-three-hours window would move the evening's scans
 * into the wrong morning's report.
 */
class DailyStockReportTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    private const WEBHOOK = 'https://hooks.slack.com/services/T000/B000/secret';

    private Rider $manager;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.slack.ops_webhook'  => self::WEBHOOK,
            'services.slack.ops_timezone' => 'Asia/Riyadh',
        ]);

        $this->manager = $this->makeInventoryManager();
    }

    /**
     * Laravel keeps the first stub that matches a URL, so faking in setUp would
     * make the rejection case unfakeable. Each test states the response it wants.
     */
    private function fakeSlack(int $status = 200, string $body = 'ok'): void
    {
        Http::fake([self::WEBHOOK => Http::response($body, $status)]);
    }

    private function makeClient(string $name, string $shortId): Client
    {
        return Client::create([
            'user_id'      => User::factory()->create()->id,
            'company_name' => $name,
            'short_id'     => $shortId,
            'client_types' => ['fulfilment'],
        ]);
    }

    private function makeProduct(Client $client, string $sku, int $stock): ClientProduct
    {
        return ClientProduct::create([
            'client_id'    => $client->id,
            'product_code' => $client->short_id . '-' . $sku,
            'name'         => "Product {$sku}",
            'sku'          => $sku,
            'quantity'     => $stock,
        ]);
    }

    /**
     * A parcel of this client's whose one item (2 units) carries this SKU.
     */
    private function parcelOf(?Client $client, string $sku): Shipment
    {
        $shipment = $this->ksaShipment();
        $shipment->order->forceFill(['client_id' => $client?->id])->save();
        $shipment->order->items()->first()->update(['lineitem_sku' => $sku]);

        return $shipment;
    }

    /**
     * Scan a parcel the way the manager's app does, then place it on the clock:
     * the recorder only trusts a phone time close to now.
     */
    private function scan(Shipment $shipment, string $direction, string $occurredAtUtc): StockScan
    {
        $scan = app(StockScanRecorder::class)->record($shipment, $this->manager, $direction, [
            'client_uuid'  => (string) Str::uuid(),
            'entry_method' => 'camera',
        ]);

        $scan->forceFill(['occurred_at' => $occurredAtUtc])->save();

        return $scan;
    }

    /** Every payload Slack was sent, decoded. */
    private function postedMessages(): array
    {
        $messages = collect(Http::recorded())->map(fn (array $pair) => $pair[0]->data())->all();

        $this->assertNotEmpty($messages, 'Nothing was posted to Slack.');

        return $messages;
    }

    private function posted(): string
    {
        return json_encode(array_column($this->postedMessages(), 'blocks'), JSON_UNESCAPED_UNICODE);
    }

    public function test_it_shows_each_scan_with_the_stock_before_and_after_it(): void
    {
        $this->fakeSlack();

        $client = $this->makeClient('Noor Store', 'NOOR');
        $this->makeProduct($client, 'SKU-1', 10);
        $parcel = $this->parcelOf($client, 'SKU-1');

        $this->scan($parcel, StockScan::OUT, '2026-09-06 10:00:00');
        $this->scan($parcel, StockScan::IN, '2026-09-06 15:30:00');

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $report = $this->posted();

        // The parcel, both ways, at Riyadh time, by the manager who scanned it.
        $this->assertStringContainsString("13:00 OUT #{$parcel->order->order_number} · {$parcel->tracking_number} · Khalid Stock", $report);
        $this->assertStringContainsString("18:30 IN  #{$parcel->order->order_number}", $report);

        // What each scan did to the item's stock.
        $this->assertStringContainsString('Test item [SKU-1] ×2  10 → 8', $report);
        $this->assertStringContainsString('Test item [SKU-1] ×2  8 → 10', $report);

        // And the product over the whole day: 2 out, 2 back, where it started.
        $this->assertMatchesRegularExpression('/Test item \[SKU-1\]\s+2\s+2  10 → 10/u', $report);

        $this->assertStringContainsString('*Scanned OUT*\n1 parcel(s), 2 pcs', $report);
        $this->assertStringContainsString('*Scanned IN*\n1 parcel(s), 2 pcs', $report);
    }

    public function test_it_groups_parcels_by_store_with_the_busiest_store_first(): void
    {
        $this->fakeSlack();

        $quiet = $this->makeClient('Quiet Store', 'QUIET');
        $busy = $this->makeClient('Busy Store', 'BUSY');
        $this->makeProduct($quiet, 'Q-1', 10);
        $this->makeProduct($busy, 'B-1', 50);

        $this->scan($this->parcelOf($quiet, 'Q-1'), StockScan::OUT, '2026-09-06 09:00:00');
        $this->scan($this->parcelOf($busy, 'B-1'), StockScan::OUT, '2026-09-06 09:10:00');
        $this->scan($this->parcelOf($busy, 'B-1'), StockScan::OUT, '2026-09-06 09:20:00');
        // A parcel that belongs to no client and came from no shop.
        $this->scan($this->parcelOf(null, 'NOPE'), StockScan::OUT, '2026-09-06 09:30:00');

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $report = $this->posted();

        $this->assertStringContainsString('🏬 Busy Store* — 2 order(s)', $report);
        $this->assertStringContainsString('🏬 Quiet Store* — 1 order(s)', $report);
        $this->assertStringContainsString('🏬 No store* — 1 order(s)', $report);
        $this->assertLessThan(strpos($report, '🏬 Quiet Store'), strpos($report, '🏬 Busy Store'));

        // Each store's stock is its own: two parcels of two units off 50.
        $this->assertMatchesRegularExpression('/Test item \[B-1\]\s+4\s+0  50 → 46/u', $report);
        $this->assertMatchesRegularExpression('/Test item \[Q-1\]\s+2\s+0  10 → 8/u', $report);

        $this->assertStringContainsString('*Stores*\n3', $report);
        $this->assertStringContainsString('*Orders scanned*\n4', $report);
    }

    public function test_it_flags_items_no_stock_moved_for_and_stock_below_zero(): void
    {
        $this->fakeSlack();

        $client = $this->makeClient('Noor Store', 'NOOR');
        $this->makeProduct($client, 'LOW-1', 1);

        $this->scan($this->parcelOf($client, 'LOW-1'), StockScan::OUT, '2026-09-06 09:00:00');
        $this->scan($this->parcelOf($client, 'NOPE'), StockScan::OUT, '2026-09-06 09:10:00');

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $report = $this->posted();

        $this->assertStringContainsString('Needs attention', $report);
        $this->assertStringContainsString('1 item(s) are not linked to a product', $report);
        $this->assertStringContainsString('1 product(s) ended below zero', $report);
        $this->assertStringContainsString('Test item [NOPE] ×2  not linked', $report);
        $this->assertStringContainsString('Test item [LOW-1] ×2  1 → -1', $report);
    }

    public function test_a_clean_day_carries_no_warnings_and_no_order_figures(): void
    {
        $this->fakeSlack();

        $client = $this->makeClient('Noor Store', 'NOOR');
        $this->makeProduct($client, 'SKU-1', 10);
        $this->scan($this->parcelOf($client, 'SKU-1'), StockScan::OUT, '2026-09-06 09:00:00');

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $report = $this->posted();

        $this->assertStringNotContainsString('Needs attention', $report);
        // The order report this replaced: revenue, average order, pipeline.
        $this->assertStringNotContainsString('SAR', $report);
        $this->assertStringNotContainsString('Average order', $report);
        $this->assertStringNotContainsString('webhook', $report);
    }

    public function test_it_reports_the_riyadh_day_not_the_utc_day(): void
    {
        $this->fakeSlack();

        $client = $this->makeClient('Noor Store', 'NOOR');
        $this->makeProduct($client, 'SKU-1', 100);

        // 2026-09-06 in Riyadh runs 2026-09-05 21:00 UTC → 2026-09-06 21:00 UTC.
        $this->scan($this->parcelOf($client, 'SKU-1'), StockScan::OUT, '2026-09-05 20:30:00');   // 23:30 on the 5th — out
        $this->scan($this->parcelOf($client, 'SKU-1'), StockScan::OUT, '2026-09-05 21:30:00');   // 00:30 on the 6th — in
        $this->scan($this->parcelOf($client, 'SKU-1'), StockScan::OUT, '2026-09-06 18:00:00');   // 21:00 on the 6th — in
        $this->scan($this->parcelOf($client, 'SKU-1'), StockScan::OUT, '2026-09-06 21:30:00');   // 00:30 on the 7th — out

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $report = $this->posted();

        $this->assertStringContainsString('*Scanned OUT*\n2 parcel(s), 4 pcs', $report);
        // The day opens on the stock the evening before left behind.
        $this->assertMatchesRegularExpression('/Test item \[SKU-1\]\s+4\s+0  98 → 94/u', $report);
    }

    public function test_a_day_with_no_scans_says_so(): void
    {
        $this->fakeSlack();

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $this->assertStringContainsString('No parcels were scanned OUT or IN.', $this->posted());
    }

    public function test_a_busy_day_is_split_into_messages_slack_accepts(): void
    {
        $this->fakeSlack();

        $client = $this->makeClient('Noor Store', 'NOOR');
        $this->makeProduct($client, 'SKU-1', 1000);

        for ($i = 0; $i < 120; $i++) {
            $this->scan($this->parcelOf($client, 'SKU-1'), StockScan::OUT, '2026-09-06 09:00:00');
        }

        for ($i = 0; $i < 60; $i++) {
            $this->scan($this->parcelOf($this->makeClient("Store {$i}", "S{$i}"), 'NOPE'), StockScan::OUT, '2026-09-06 10:00:00');
        }

        $this->artisan('report:daily', ['--date' => '2026-09-06'])->assertSuccessful();

        $messages = $this->postedMessages();

        $this->assertGreaterThan(1, count($messages));

        foreach ($messages as $message) {
            $this->assertLessThanOrEqual(50, count($message['blocks']));

            foreach ($message['blocks'] as $block) {
                $this->assertLessThanOrEqual(3000, mb_strlen($block['text']['text'] ?? ''));
            }
        }

        // Nothing was dropped to make it fit.
        $this->assertSame(180, substr_count($this->posted(), ' OUT #'));
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
