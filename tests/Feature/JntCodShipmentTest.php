<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Warehouse;
use App\Services\Shipping\Drivers\JntExpressDriver;
use App\Services\Shipping\DTOs\ShipmentData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JntCodShipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Give the driver working credentials so it builds a real request.
        config()->set('services.jnt_express', [
            'api_account'       => 'TEST_ACCOUNT',
            'private_key'       => 'TEST_KEY',
            'customer_code'     => 'TEST_CODE',
            'customer_password' => 'TEST_PASS',
            'sandbox_uuid'      => null,
            'base_url'          => 'https://openapi.jtjms-sa.com',
        ]);
    }

    private function makeOrder(string $paymentMethod, float $total = 150.0, string $financial = 'pending'): Order
    {
        $order = Order::create([
            'order_number'     => (string) random_int(100000, 999999),
            'customer_name'    => 'Zain',
            'customer_phone'   => '0500000000',
            'shipping_name'    => 'Zain',
            'shipping_phone'   => '0500000000',
            'shipping_province' => 'Riyadh',
            'shipping_city'    => 'Riyadh',
            'shipping_address1' => 'Al Suwaidi District',
            'shipping_country' => 'KSA',
            'currency'         => 'SAR',
            'total'            => $total,
            'payment_method'   => $paymentMethod,
            'financial_status' => $financial,
        ]);

        $order->items()->create([
            'lineitem_name'     => 'Multifunctional 3 in 1 Vegetable',
            'lineitem_quantity' => 1,
            'lineitem_price'    => $total,
        ]);

        return $order->fresh('items');
    }

    private function warehouse(): Warehouse
    {
        return Warehouse::create([
            'name'         => 'Riyadh WH',
            'contact_name' => 'KSA',
            'phone'        => '0501112229',
            'province'     => 'Riyadh',
            'city'         => 'Riyadh',
            'address'      => 'Al Suwaidi',
            'post_code'    => '12345',
            'country_code' => 'KSA',
        ]);
    }

    public function test_cod_detection_across_payment_method_variants(): void
    {
        $cases = [
            // [payment_method, financial_status, expected isCashOnDelivery]
            ['cod', 'pending', true],
            ['Cash on Delivery', 'pending', true],   // CSV import default
            ['COD', 'pending', true],
            ['الدفع عند الاستلام', 'pending', true],  // Arabic
            ['prepaid', 'paid', false],
            ['Credit Card', 'paid', false],
            ['mada', 'paid', false],
            ['', 'paid', false],                      // blank + already paid
            ['', 'pending', true],                    // blank + unpaid => treat as COD
        ];

        foreach ($cases as [$method, $financial, $expected]) {
            $order = $this->makeOrder($method, 150.0, $financial);
            $this->assertSame($expected, $order->isCashOnDelivery(), "payment_method='{$method}'");
        }
    }

    public function test_cod_order_sends_codmoney_to_jnt(): void
    {
        Http::fake([
            '*' => Http::response(['code' => '1', 'data' => ['billCode' => 'JTE123', 'sortingCode' => 'A1']], 200),
        ]);

        $order = $this->makeOrder('Cash on Delivery', 199.50);
        $data  = ShipmentData::fromOrder($order, $this->warehouse());

        $this->assertSame(199.50, $data->codAmount);

        (new JntExpressDriver())->createShipment($data);

        Http::assertSent(function ($request) {
            $biz = json_decode($request['bizContent'], true);

            return str_contains($request->url(), 'addOrder')
                && ($biz['itemsValue'] ?? null) === '199.50'
                && ($biz['priceCurrency'] ?? null) === 'SAR';
        });
    }

    public function test_prepaid_order_does_not_send_codmoney(): void
    {
        Http::fake([
            '*' => Http::response(['code' => '1', 'data' => ['billCode' => 'JTE124']], 200),
        ]);

        $order = $this->makeOrder('Credit Card', 199.50, 'paid');
        $data  = ShipmentData::fromOrder($order, $this->warehouse());

        $this->assertSame(0.0, $data->codAmount);

        (new JntExpressDriver())->createShipment($data);

        Http::assertSent(function ($request) {
            $biz = json_decode($request['bizContent'], true);

            return ! isset($biz['itemsValue']);
        });
    }
}
