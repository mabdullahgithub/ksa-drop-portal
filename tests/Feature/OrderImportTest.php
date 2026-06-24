<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('client');
    }

    private function makeClient(User $user): Client
    {
        return Client::create([
            'user_id'       => $user->id,
            'company_name'  => 'Test Client',
            'short_id'      => 'TST',
            'client_types'  => ['fulfilment'],
            'portal_features' => ['orders'],
        ]);
    }

    private function csvFile(array $rows, string $filename = 'orders.csv'): UploadedFile
    {
        $headers = [
            'Name', 'Email', 'Financial Status', 'Paid at', 'Fulfillment Status', 'Fulfilled at',
            'Accepts Marketing', 'Currency', 'Subtotal', 'Shipping', 'Taxes', 'Total',
            'Discount Code', 'Discount Amount', 'Shipping Method', 'Created at',
            'Lineitem quantity', 'Lineitem name', 'Lineitem price', 'Lineitem compare at price',
            'Lineitem sku', 'Lineitem requires shipping', 'Lineitem taxable', 'Lineitem fulfillment status',
            'Billing Name', 'Billing Street', 'Billing Address1', 'Billing Address2', 'Billing Company',
            'Billing City', 'Billing Zip', 'Billing Province', 'Billing Country', 'Billing Phone',
            'Shipping Name', 'Shipping Street', 'Shipping Address1', 'Shipping Address2', 'Shipping Company',
            'Shipping City', 'Shipping Zip', 'Shipping Province', 'Shipping Country', 'Shipping Phone',
            'Notes', 'Note Attributes', 'Cancelled at', 'Payment Method', 'Payment Reference',
            'Refunded Amount', 'Vendor', 'Outstanding Balance', 'Employee', 'Location', 'Device ID',
            'Id', 'Tags', 'Risk Level', 'Source',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    private function sampleRow(array $overrides = []): array
    {
        $defaults = [
            '#10001', 'customer@example.com', 'pending', '', 'unfulfilled', '',
            'no', 'SAR', '100.00', '15.00', '0.00', '115.00',
            '', '0.00', 'Standard', '2026-06-24 12:00:00',
            '1', 'Test Item', '100.00', '0.00',
            'SKU-100', 'true', 'false', 'unfulfilled',
            'Test Customer', 'Street 1', 'Street 1', '', '',
            'Riyadh', '12345', 'Riyadh', 'SA', '+966500000000',
            'Test Customer', 'Street 1', 'Street 1', '', '',
            'Riyadh', '12345', 'Riyadh', 'SA', '+966500000000',
            '', '', '', 'Cash on Delivery', '',
            '0.00', '', '0.00', '', '', '',
            '', 'tag1, tag2', 'Low', 'manual',
        ];

        // Apply overrides by column index (simplified – pass full row)
        return array_replace($defaults, $overrides);
    }

    // ─── Tags are always ignored on import ───────────────────────────────────

    public function test_tags_are_ignored_on_csv_import(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $file = $this->csvFile([$this->sampleRow()]);

        $response = $this->actingAs($user)
            ->postJson(route('portal.api.orders.import'), ['file' => $file]);

        $response->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('imported', 1);

        $order = Order::where('order_number', 'TST10001')->first();
        $this->assertNotNull($order);
        $this->assertEmpty($order->tags);
    }

    // ─── Import note is saved to every order ─────────────────────────────────

    public function test_import_note_is_prepended_to_order_notes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $row = $this->sampleRow();
        // Set 'Notes' column (index 44)
        $row[44] = 'Original CSV note';
        $file = $this->csvFile([$row]);

        $response = $this->actingAs($user)->postJson(route('portal.api.orders.import'), [
            'file'        => $file,
            'import_note' => 'Batch from June campaign',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $order = Order::where('order_number', 'TST10001')->first();
        $this->assertNotNull($order);
        $this->assertStringContainsString('Batch from June campaign', $order->notes);
        $this->assertStringContainsString('Original CSV note', $order->notes);
    }

    public function test_import_note_alone_when_csv_notes_empty(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $file = $this->csvFile([$this->sampleRow()]);

        $response = $this->actingAs($user)->postJson(route('portal.api.orders.import'), [
            'file'        => $file,
            'import_note' => 'Only this note',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $order = Order::where('order_number', 'TST10001')->first();
        $this->assertNotNull($order);
        $this->assertEquals('Only this note', $order->notes);
    }

    public function test_import_note_validation_max_2000_chars(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $file = $this->csvFile([$this->sampleRow()]);

        $response = $this->actingAs($user)->postJson(route('portal.api.orders.import'), [
            'file'        => $file,
            'import_note' => str_repeat('x', 2001),
        ]);

        $response->assertStatus(422);
    }

    // ─── Individual order creation ────────────────────────────────────────────

    public function test_client_can_create_individual_order(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $response = $this->actingAs($user)->postJson(route('portal.api.orders.store'), [
            'customer_name'  => 'Jane Doe',
            'customer_phone' => '+966512345678',
            'notes'          => 'Handle with care – fragile item.',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $orderNumber = $response->json('order_number');
        $this->assertNotNull($orderNumber);

        $order = Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);
        $this->assertEquals('Jane Doe', $order->customer_name);
        $this->assertEquals('Handle with care – fragile item.', $order->notes);
        $this->assertEquals('pending', $order->financial_status);
        $this->assertEquals('unfulfilled', $order->fulfillment_status);
        $this->assertEmpty($order->tags);
    }

    public function test_create_order_requires_customer_name_and_phone(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $response = $this->actingAs($user)->postJson(route('portal.api.orders.store'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_name', 'customer_phone']);
    }

    public function test_create_order_saves_line_item(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $response = $this->actingAs($user)->postJson(route('portal.api.orders.store'), [
            'customer_name'    => 'John Buyer',
            'customer_phone'   => '+966599999999',
            'lineitem_name'    => 'Blue T-Shirt / L',
            'lineitem_quantity' => 2,
            'lineitem_price'   => 79.99,
            'lineitem_sku'     => 'TSHIRT-BL-L',
            'notes'            => 'Customer requested gift wrapping.',
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);

        $order = Order::where('order_number', $response->json('order_number'))->with('items')->first();
        $this->assertNotNull($order);
        $this->assertEquals(1, $order->items->count());
        $this->assertEquals('Blue T-Shirt / L', $order->items->first()->lineitem_name);
        $this->assertEquals(2, $order->items->first()->lineitem_quantity);
        $this->assertEquals('Customer requested gift wrapping.', $order->notes);
    }
}
