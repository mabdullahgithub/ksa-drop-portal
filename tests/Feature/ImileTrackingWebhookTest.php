<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImileTrackingWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const PARTNER_CODE = 'C21063665436054';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.imile', [
            'customer_id' => self::PARTNER_CODE,
            'api_key'     => 'TEST_API_KEY',
            'base_url'    => 'https://openapi.imile.com',
            'time_zone'   => '+3',
        ]);
    }

    private function makeShipment(string $trackingNumber = '601032542303698734985'): Shipment
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
            'status'          => ShipmentStatus::PENDING->value,
        ]);
    }

    /** Build the JSON envelope iMile would POST. Signature is not yet verifiable (see ImileWebhookController::verifySignature). */
    private function push(array $param, string $partnerCode = self::PARTNER_CODE): array
    {
        return [
            'partnerCode' => $partnerCode,
            'param'       => $param,
            'sign'        => 'PLACEHOLDER_UNTIL_IMILE_CONFIRMS_FORMULA',
            'timestamp'   => '2025-01-06T19:17:06+08:00',
        ];
    }

    public function test_tracking_push_records_all_locus_events(): void
    {
        $shipment = $this->makeShipment();

        $body = $this->push([
            'billNo'           => $shipment->tracking_number,
            'orderNo'          => $shipment->txlogistic_id,
            'latestStatus'     => 'Delivered',
            'latestStatusTime' => '2025-01-06T19:17:01+08:00',
            'locus'            => [
                [
                    'latestSite'       => 'MKW DS01',
                    'latestStatus'     => 'Delivered',
                    'latestStatusTime' => '2025-01-06T19:17:01+08:00',
                    'locusDetailed'    => 'Delivered.',
                    'locusType'        => 'delivery',
                ],
                [
                    'latestSite'       => 'MKW DS01',
                    'latestStatus'     => 'OFD',
                    'latestStatusTime' => '2025-01-06T13:58:29+08:00',
                    'locusDetailed'    => 'Shipment out for delivery.',
                    'locusType'        => 'scan',
                ],
                [
                    'latestSite'       => 'Riyadh Distribution Center',
                    'latestStatus'     => 'RecevieOrder',
                    'latestStatusTime' => '2025-01-05T09:20:08+08:00',
                    'locusDetailed'    => 'Received.',
                    'locusType'        => 'scan',
                ],
            ],
        ]);

        $response = $this->postJson('/webhooks/imile/tracking', $body);

        $response->assertOk();
        $response->assertJson(['code' => '200', 'message' => 'success']);

        $shipment->refresh();

        $this->assertSame(ShipmentStatus::DELIVERED->value, $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertCount(3, $shipment->tracking_history);
        $this->assertSame(ShipmentStatus::DELIVERED->value, $shipment->tracking_history[0]['status']);
        $this->assertSame('fulfilled', $shipment->order->fresh()->fulfillment_status);
    }

    public function test_top_level_single_status_is_accepted_without_locus(): void
    {
        $shipment = $this->makeShipment();

        $body = $this->push([
            'billNo'           => $shipment->tracking_number,
            'latestStatus'     => 'OFD',
            'latestStatusTime' => '2025-01-06T13:58:29+08:00',
            'latestLocus'      => 'Shipment out for delivery.',
        ]);

        $this->postJson('/webhooks/imile/tracking', $body)
            ->assertOk()
            ->assertJson(['code' => '200']);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::OUT_FOR_DELIVERY->value, $shipment->status);
        $this->assertCount(1, $shipment->tracking_history);
    }

    public function test_duplicate_push_is_idempotent(): void
    {
        $shipment = $this->makeShipment();

        $body = $this->push([
            'billNo' => $shipment->tracking_number,
            'locus'  => [[
                'latestSite'       => 'Riyadh Distribution Center',
                'latestStatus'     => 'Shipping',
                'latestStatusTime' => '2025-01-05T15:17:36+08:00',
                'locusDetailed'    => 'Parcel shipped.',
            ]],
        ]);

        $this->postJson('/webhooks/imile/tracking', $body)->assertOk();
        $this->postJson('/webhooks/imile/tracking', $body)->assertOk();

        $this->assertCount(1, $shipment->refresh()->tracking_history);
    }

    public function test_unknown_shipment_is_acknowledged(): void
    {
        $body = $this->push([
            'billNo'       => 'DOES_NOT_EXIST',
            'latestStatus' => 'Delivered',
        ]);

        $this->postJson('/webhooks/imile/tracking', $body)
            ->assertOk()
            ->assertJson(['code' => '200']);
    }

    public function test_missing_required_fields_returns_400(): void
    {
        $this->postJson('/webhooks/imile/tracking', ['partnerCode' => self::PARTNER_CODE])
            ->assertStatus(400)
            ->assertJson(['code' => '400']);
    }

    public function test_partner_code_mismatch_returns_401(): void
    {
        $shipment = $this->makeShipment();

        $body = $this->push([
            'billNo'       => $shipment->tracking_number,
            'latestStatus' => 'Delivered',
        ], partnerCode: 'SOME_OTHER_ACCOUNT');

        $this->postJson('/webhooks/imile/tracking', $body)
            ->assertStatus(401)
            ->assertJson(['code' => '401']);

        $this->assertSame(ShipmentStatus::PENDING->value, $shipment->refresh()->status);
    }
}
