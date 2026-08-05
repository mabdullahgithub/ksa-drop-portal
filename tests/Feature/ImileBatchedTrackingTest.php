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

    public function test_track_shipments_maps_batched_results_by_bill_no(): void
    {
        Http::fake([
            '*/auth/accessToken/grant' => Http::response([
                'code' => '200',
                'data' => ['accessToken' => 'TEST_TOKEN', 'expiresIn' => 7200],
            ]),
            '*/client/track/list' => Http::response([
                'code' => '200',
                'data' => [
                    'list' => [
                        [
                            'billNo' => 'IM001',
                            'latestStatus' => 'OFD',
                            'locus' => [
                                ['status' => 'OFD', 'desc' => 'Out for delivery', 'scanTime' => '2026-08-05 09:00:00'],
                            ],
                        ],
                        [
                            'billNo' => 'IM002',
                            'latestStatus' => 'Delivered',
                            'locus' => [
                                ['status' => 'Delivered', 'desc' => 'Delivered', 'scanTime' => '2026-08-05 10:00:00'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $driver = new ImileDriver();
        $results = $driver->trackShipments(['IM001', 'IM002']);

        $this->assertCount(2, $results);
        $this->assertTrue($results['IM001']->success);
        $this->assertSame(ShipmentStatus::OUT_FOR_DELIVERY, $results['IM001']->currentStatus);
        $this->assertTrue($results['IM002']->success);
        $this->assertSame(ShipmentStatus::DELIVERED, $results['IM002']->currentStatus);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'client/track/list')
            && $request['param']['orderCodes'] === ['IM001', 'IM002']);
    }

    public function test_track_shipments_fails_individual_entries_missing_from_response(): void
    {
        Http::fake([
            '*/auth/accessToken/grant' => Http::response([
                'code' => '200',
                'data' => ['accessToken' => 'TEST_TOKEN', 'expiresIn' => 7200],
            ]),
            '*/client/track/list' => Http::response([
                'code' => '200',
                'data' => [
                    'list' => [
                        ['billNo' => 'IM001', 'latestStatus' => 'OFD', 'locus' => []],
                    ],
                ],
            ]),
        ]);

        $driver = new ImileDriver();
        $results = $driver->trackShipments(['IM001', 'IM_MISSING']);

        $this->assertTrue($results['IM001']->success);
        $this->assertFalse($results['IM_MISSING']->success);
    }

    public function test_sync_command_updates_shipments_via_single_batched_call(): void
    {
        $a = $this->makeShipment('IM001');
        $b = $this->makeShipment('IM002');

        Http::fake([
            '*/auth/accessToken/grant' => Http::response([
                'code' => '200',
                'data' => ['accessToken' => 'TEST_TOKEN', 'expiresIn' => 7200],
            ]),
            '*/client/track/list' => Http::response([
                'code' => '200',
                'data' => [
                    'list' => [
                        [
                            'billNo' => 'IM001',
                            'latestStatus' => 'Delivered',
                            'locus' => [['status' => 'Delivered', 'desc' => 'Delivered', 'scanTime' => '2026-08-05 10:00:00']],
                        ],
                        [
                            'billNo' => 'IM002',
                            'latestStatus' => 'OFD',
                            'locus' => [['status' => 'OFD', 'desc' => 'Out for delivery', 'scanTime' => '2026-08-05 09:00:00']],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('shipments:sync-tracking')->assertSuccessful();

        // Exactly one client/track/list call for both shipments — the whole
        // point of batching.
        Http::assertSentCount(2); // 1 token grant + 1 batched track/list

        $a->refresh();
        $b->refresh();

        $this->assertSame(ShipmentStatus::DELIVERED->value, $a->status);
        $this->assertNotNull($a->delivered_at);
        $this->assertSame('Delivered', $a->courier_status);
        $this->assertSame(ShipmentStatus::OUT_FOR_DELIVERY->value, $b->status);
        $this->assertSame('OFD', $b->courier_status);
        $this->assertSame('Out for delivery', $b->courier_status_description);
    }
}
