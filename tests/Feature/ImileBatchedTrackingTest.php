<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\Drivers\ImileDriver;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImileBatchedTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.imile', [
            'customer_id' => 'TEST_CUSTOMER',
            'api_key'     => 'TEST_KEY',
            'base_url'    => 'https://openapi.52imile.cn',
            'time_zone'   => '+3',
        ]);

        Http::fake([
            '*/auth/accessToken/grant' => Http::response([
                'code' => '200',
                'data' => ['accessToken' => 'TEST_TOKEN', 'expiresIn' => 7200],
            ]),
        ]);
    }

    private function makeShipment(string $trackingNumber): Shipment
    {
        $order = Order::create([
            'order_number'      => (string) random_int(100000, 999999),
            'customer_name'     => 'Zain',
            'customer_phone'    => '0500000000',
            'shipping_name'     => 'Zain',
            'shipping_phone'    => '0500000000',
            'shipping_province' => 'Riyadh',
            'shipping_city'     => 'Riyadh',
            'shipping_address1' => 'Al Suwaidi District',
            'shipping_country'  => 'KSA',
            'currency'          => 'SAR',
            'total'             => 150.0,
            'payment_method'    => 'cod',
            'financial_status'  => 'pending',
        ]);

        return Shipment::create([
            'order_id'        => $order->id,
            'courier'         => 'imile',
            'tracking_number' => $trackingNumber,
            'txlogistic_id'   => 'TX' . $order->order_number,
            'status'          => ShipmentStatus::IN_TRANSIT->value,
        ]);
    }

    /**
     * One `client/track/list` record, in the shape the live API returns
     * (captured 2026-10-22): scans newest first, times in UTC+3.
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $scans  [status, time, text]
     */
    private function record(string $billNo, array $scans): array
    {
        return [
            'billNo' => $billNo,
            'orderType' => '100',
            'orderNo' => 'ORD-' . $billNo,
            'latestStatus' => $scans[0][0],
            'latestStatusTime' => $scans[0][1],
            'locus' => array_map(fn (array $scan) => [
                'latestStatus' => $scan[0],
                'latestStatusTime' => $scan[1],
                'latestSite' => 'Riyadh Station',
                'locusDetailed' => $scan[2],
                'locusType' => 'scan',
                'timeZone' => 'GMT+03:00',
            ], $scans),
        ];
    }

    private function fakeTracking(array $records): void
    {
        Http::fake([
            '*/auth/accessToken/grant' => Http::response([
                'code' => '200',
                'data' => ['accessToken' => 'TEST_TOKEN', 'expiresIn' => 7200],
            ]),
            '*/client/track/list' => Http::response([
                'code' => '200',
                'message' => 'success',
                'data' => $records,
                'timeZone' => '+3',
            ]),
        ]);
    }

    public function test_track_shipments_maps_batched_results_by_bill_no(): void
    {
        $this->fakeTracking([
            $this->record('IM002', [
                ['Delivered', '2026-08-05 10:00:00', 'Delivered'],
                ['OFD', '2026-08-05 09:00:00', 'Out for delivery'],
            ]),
            $this->record('IM001', [['OFD', '2026-08-05 09:00:00', 'Out for delivery']]),
        ]);

        $results = (new ImileDriver())->trackShipments(['IM001', 'IM002']);

        $this->assertCount(2, $results);
        $this->assertTrue($results['IM001']->success);
        $this->assertSame(ShipmentStatus::OUT_FOR_DELIVERY, $results['IM001']->currentStatus);
        $this->assertTrue($results['IM002']->success);
        $this->assertSame(ShipmentStatus::DELIVERED, $results['IM002']->currentStatus);

        // Stamped the way the webhook stamps the same scan, so the two
        // de-duplicate in the stored history.
        $this->assertSame('2026-08-05T15:00:00+08:00', $results['IM002']->events[0]->timestamp);
        $this->assertSame('Delivered', $results['IM002']->events[0]->rawStatus);
        $this->assertSame('Riyadh Station', $results['IM002']->events[0]->location);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'client/track/list')
            && $request['param'] === ['orderType' => '1', 'language' => '2', 'orderNo' => ['IM001', 'IM002']]);
    }

    public function test_track_shipments_fails_entries_missing_or_blank_in_the_response(): void
    {
        // iMile answers a waybill it does not know with a blank record.
        $this->fakeTracking([
            $this->record('IM001', [['OFD', '2026-08-05 09:00:00', 'Out for delivery']]),
            ['billNo' => '', 'orderNo' => '', 'latestStatus' => '', 'locus' => []],
        ]);

        $results = (new ImileDriver())->trackShipments(['IM001', 'IM_BLANK', 'IM_MISSING']);

        $this->assertTrue($results['IM001']->success);
        $this->assertFalse($results['IM_BLANK']->success);
        $this->assertFalse($results['IM_MISSING']->success);
    }

    public function test_a_rejected_parameter_is_reported_as_such_and_not_retried_as_a_token_problem(): void
    {
        Http::fake([
            '*/auth/accessToken/grant' => Http::response([
                'code' => '200',
                'data' => ['accessToken' => 'TEST_TOKEN', 'expiresIn' => 7200],
            ]),
            '*/client/track/list' => Http::response(['code' => '402', 'message' => 'param is invalid']),
        ]);

        $result = (new ImileDriver())->trackShipment('IM001');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('param is invalid', $result->errorMessage);
        Http::assertSentCount(2); // 1 token grant + 1 track/list, no token-refresh retry
    }

    public function test_sync_command_updates_shipments_via_single_batched_call(): void
    {
        $a = $this->makeShipment('IM001');
        $b = $this->makeShipment('IM002');
        $c = $this->makeShipment('IM003');

        $this->fakeTracking([
            $this->record('IM001', [['Delivered', '2026-08-05 10:00:00', 'Delivered']]),
            $this->record('IM002', [['OFD', '2026-08-05 09:00:00', 'Out for delivery']]),
            $this->record('IM003', [
                ['DeliveryCompleted', '2026-08-05 09:41:23', 'Order Closed.'],
                ['1101', '2026-08-05 09:40:04', '<client/seller cancel orders>'],
            ]),
        ]);

        $this->artisan('shipments:sync-tracking')->assertSuccessful();

        // Exactly one client/track/list call for all three — the whole
        // point of batching.
        Http::assertSentCount(2); // 1 token grant + 1 batched track/list

        $a->refresh();
        $b->refresh();
        $c->refresh();

        $this->assertSame(ShipmentStatus::DELIVERED->value, $a->status);
        $this->assertNotNull($a->delivered_at);
        $this->assertSame('Delivered', $a->courier_status);
        $this->assertSame(ShipmentStatus::OUT_FOR_DELIVERY->value, $b->status);
        $this->assertSame('OFD', $b->courier_status);
        $this->assertSame('Out for delivery', $b->courier_status_description);
        $this->assertSame(ShipmentStatus::RETURNING->value, $c->status);
        $this->assertSame('DeliveryCompleted', $c->courier_status);
        $this->assertNotNull($c->tracking_synced_at);
    }

    public function test_pulled_scans_merge_with_pushed_ones_instead_of_replacing_them(): void
    {
        $shipment = $this->makeShipment('IM001');
        $shipment->update(['tracking_history' => [
            // As the webhook stored it: same scan the pull returns below.
            ['status' => 'in_transit', 'description' => 'Arrived', 'location' => 'Riyadh Station',
                'timestamp' => '2026-08-05T13:00:00+08:00', 'raw_status' => 'Arrive',
                'latitude' => '24.7', 'longitude' => '46.6'],
        ]]);

        $this->fakeTracking([
            $this->record('IM001', [
                ['OFD', '2026-08-05 09:00:00', 'Out for delivery'],
                ['Arrive', '2026-08-05 08:00:00', 'Arrived'],
            ]),
        ]);

        $this->artisan('shipments:sync-tracking')->assertSuccessful();

        $history = $shipment->refresh()->tracking_history;

        $this->assertCount(2, $history);
        $this->assertSame('OFD', $history[0]['raw_status']);
        $this->assertSame('24.7', $history[1]['latitude']);
    }

    public function test_sync_works_through_the_least_recently_asked_first(): void
    {
        $asked = $this->makeShipment('IM001');
        $never = $this->makeShipment('IM002');
        Shipment::whereKey($asked->id)->toBase()->update(['tracking_synced_at' => now()]);

        $this->fakeTracking([$this->record('IM002', [['OFD', '2026-08-05 09:00:00', 'Out for delivery']])]);

        $this->artisan('shipments:sync-tracking', ['--limit' => 1])->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'client/track/list')
            && $request['param']['orderNo'] === ['IM002']);
        $this->assertSame(ShipmentStatus::OUT_FOR_DELIVERY->value, $never->refresh()->status);
    }
}
