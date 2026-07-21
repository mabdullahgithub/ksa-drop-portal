<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JntTrackingWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVATE_KEY = 'TEST_KEY';
    private const API_ACCOUNT = 'TEST_ACCOUNT';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jnt_express', [
            'api_account'       => self::API_ACCOUNT,
            'private_key'       => self::PRIVATE_KEY,
            'customer_code'     => 'TEST_CODE',
            'customer_password' => 'TEST_PASS',
            'sandbox_uuid'      => null,
            'base_url'          => 'https://openapi.jtjms-sa.com',
        ]);
        config()->set('services.jnt.skip_webhook_verification', false);
    }

    private function makeShipment(string $trackingNumber = 'UTE300000056947'): Shipment
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
            'courier'         => 'jnt_express',
            'tracking_number' => $trackingNumber,
            'txlogistic_id'   => 'TX' . $order->order_number,
            'status'          => ShipmentStatus::PENDING->value,
        ]);
    }

    /** Build the form body + signed headers J&T would send. */
    private function push(array $bizContent, array $headerOverrides = []): array
    {
        $biz    = json_encode($bizContent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $digest = base64_encode(md5($biz . self::PRIVATE_KEY, true));

        $headers = array_merge([
            'apiAccount' => self::API_ACCOUNT,
            'digest'     => $digest,
            'timestamp'  => (string) round(microtime(true) * 1000),
        ], $headerOverrides);

        return [$biz, $headers];
    }

    public function test_tracking_push_records_all_scan_events_from_details_array(): void
    {
        $shipment = $this->makeShipment();

        [$biz, $headers] = $this->push([
            'billCode'     => 'UTE300000056947',
            'txlogisticId' => $shipment->txlogistic_id,
            'details'      => [
                [
                    'billCode'        => 'UTE300000056947',
                    'scanType'        => 'Pickup scan',
                    'scanTypeCode'    => '10',
                    'desc'            => 'The parcel has been picked up',
                    'scanTime'        => '2022-06-06 10:10:28',
                    'scanNetworkName' => 'Riyadh station 1',
                    'scanNetworkId'   => '193',
                    'scanNetworkCity' => 'Riyadh',
                    'staffName'       => 'Pinky',
                    'staffContact'    => '+966-08070003',
                ],
                [
                    'billCode'     => 'UTE300000056947',
                    'scanType'     => 'Delivery scan',
                    'scanTypeCode' => '94',
                    'desc'         => 'Out for delivery',
                    'scanTime'     => '2022-06-07 08:00:00',
                ],
                [
                    'billCode'     => 'UTE300000056947',
                    'scanType'     => 'Sign scan',
                    'scanTypeCode' => '100',
                    'desc'         => 'Delivered and signed',
                    'scanTime'     => '2022-06-07 14:30:00',
                    'otp'          => '123456',
                ],
            ],
        ]);

        $response = $this->call('POST', '/webhooks/jnt-express', ['bizContent' => $biz], [], [], $this->serverHeaders($headers));

        $response->assertOk();
        $response->assertJson(['code' => '1', 'msg' => 'success']);

        $shipment->refresh();

        // Latest scan (sign) drives the current status.
        $this->assertSame(ShipmentStatus::DELIVERED->value, $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertTrue((bool) $shipment->otp_verified);

        // All three scans are stored, newest first.
        $this->assertCount(3, $shipment->tracking_history);
        $this->assertSame(ShipmentStatus::DELIVERED->value, $shipment->tracking_history[0]['status']);
        $this->assertSame('Pinky', $shipment->tracking_history[2]['staff_name']);

        // Delivery flips the order to fulfilled.
        $this->assertSame('fulfilled', $shipment->order->fresh()->fulfillment_status);
    }

    public function test_top_level_single_scan_is_still_accepted(): void
    {
        $shipment = $this->makeShipment();

        [$biz, $headers] = $this->push([
            'billCode' => 'UTE300000056947',
            'scanType' => 'Sending scan',
            'desc'     => 'In transit',
            'scanTime' => '2022-06-06 12:00:00',
        ]);

        $this->call('POST', '/webhooks/jnt-express', ['bizContent' => $biz], [], [], $this->serverHeaders($headers))
            ->assertOk()
            ->assertJson(['code' => '1']);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::IN_TRANSIT->value, $shipment->status);
        $this->assertCount(1, $shipment->tracking_history);
    }

    public function test_duplicate_push_is_idempotent(): void
    {
        $shipment = $this->makeShipment();

        [$biz, $headers] = $this->push([
            'billCode' => 'UTE300000056947',
            'details'  => [[
                'scanType'      => 'Station arrival',
                'desc'          => 'Arrived at station',
                'scanTime'      => '2022-06-06 12:00:00',
                'scanNetworkId' => '193',
            ]],
        ]);

        $server = $this->serverHeaders($headers);
        $this->call('POST', '/webhooks/jnt-express', ['bizContent' => $biz], [], [], $server)->assertOk();
        $this->call('POST', '/webhooks/jnt-express', ['bizContent' => $biz], [], [], $server)->assertOk();

        $this->assertCount(1, $shipment->refresh()->tracking_history);
    }

    public function test_missing_api_account_returns_documented_code(): void
    {
        $this->makeShipment();

        [$biz, $headers] = $this->push(['billCode' => 'UTE300000056947', 'details' => []]);
        unset($headers['apiAccount']);

        $this->call('POST', '/webhooks/jnt-express', ['bizContent' => $biz], [], [], $this->serverHeaders($headers))
            ->assertOk()
            ->assertJson(['code' => '145003071']);
    }

    public function test_missing_digest_returns_documented_code(): void
    {
        [$biz, $headers] = $this->push(['billCode' => 'UTE300000056947']);
        unset($headers['digest']);

        $this->call('POST', '/webhooks/jnt-express', ['bizContent' => $biz], [], [], $this->serverHeaders($headers))
            ->assertOk()
            ->assertJson(['code' => '145003052']);
    }

    public function test_invalid_signature_returns_documented_code(): void
    {
        $this->makeShipment();

        [$biz, $headers] = $this->push(['billCode' => 'UTE300000056947']);
        $headers['digest'] = 'deadbeefdeadbeef==';

        $this->call('POST', '/webhooks/jnt-express', ['bizContent' => $biz], [], [], $this->serverHeaders($headers))
            ->assertOk()
            ->assertJson(['code' => '145003030']);
    }

    public function test_unknown_shipment_is_acknowledged(): void
    {
        [$biz, $headers] = $this->push([
            'billCode' => 'DOES_NOT_EXIST',
            'details'  => [['scanType' => 'Pickup scan', 'scanTime' => '2022-06-06 10:00:00']],
        ]);

        $this->call('POST', '/webhooks/jnt-express', ['bizContent' => $biz], [], [], $this->serverHeaders($headers))
            ->assertOk()
            ->assertJson(['code' => '1']);
    }

    /** Prefix headers with HTTP_ so the test client passes them through. */
    private function serverHeaders(array $headers): array
    {
        $server = [];
        foreach ($headers as $key => $value) {
            $server['HTTP_' . $key] = $value;
        }

        return $server;
    }
}
