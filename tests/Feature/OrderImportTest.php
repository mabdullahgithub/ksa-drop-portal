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
            'user_id'         => $user->id,
            'company_name'    => 'Test Client',
            'short_id'        => 'TST',
            'client_types'    => ['fulfilment'],
            'portal_features' => ['orders'],
        ]);
    }

    /**
     * Build a CSV upload from the simplified template columns:
     * Name, Phone, City, Address, COD, Product Name, SKU, Notes
     */
    private function csvFile(array $rows, string $filename = 'orders.csv'): UploadedFile
    {
        $headers = ['Name', 'Phone', 'City', 'Address', 'COD', 'Product Name', 'SKU', 'Notes'];

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
            'John Doe',       // Name
            '+966500000000',  // Phone
            'Riyadh',         // City
            '123 King Fahd Rd', // Address
            '250.00',         // COD
            'Blue T-Shirt',   // Product Name
            'TSHIRT-BL-L',    // SKU
            '',               // Notes
        ];
        return array_replace($defaults, $overrides);
    }

    // ─── Order numbers are always auto-generated ──────────────────────────────

    public function test_order_number_is_auto_generated_with_client_prefix(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $file = $this->csvFile([$this->sampleRow()]);

        $response = $this->actingAs($user)
            ->postJson(route('portal.api.orders.import'), ['file' => $file]);

        $response->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('imported', 1);

        $order = Order::where('client_id', User::first()->client->id)->first();
        $this->assertNotNull($order);
        $this->assertStringStartsWith('TST', $order->order_number);
    }

    // ─── Imported orders carry the default tag ───────────────────────────────

    public function test_imported_orders_are_tagged_pending(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $file = $this->csvFile([$this->sampleRow()]);

        $this->actingAs($user)
            ->postJson(route('portal.api.orders.import'), ['file' => $file])
            ->assertStatus(200)->assertJsonPath('imported', 1);

        // Every order enters the workflow as Pending (PortalController::
        // defaultOrderTags); the CSV's own tag column is deliberately ignored.
        $order = Order::where('client_id', $user->client->id)->first();
        $this->assertSame(['Pending'], $order->tags);
    }

    // ─── Simplified columns are parsed correctly ──────────────────────────────

    public function test_simplified_template_columns_are_parsed(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $row = ['Sarah Al-Ahmed', '+966501111111', 'Jeddah', '45 Olaya St', '180.00', 'Running Shoes', 'SHOE-WHT-42', ''];
        $file = $this->csvFile([$row]);

        $this->actingAs($user)
            ->postJson(route('portal.api.orders.import'), ['file' => $file])
            ->assertStatus(200)->assertJsonPath('imported', 1);

        $order = Order::where('client_id', $user->client->id)->first();
        $this->assertEquals('Sarah Al-Ahmed', $order->customer_name);
        $this->assertEquals('+966501111111', $order->customer_phone);
        $this->assertEquals('45 Olaya St', $order->shipping_address1);
        $this->assertEquals('Jeddah', $order->shipping_city);
        $this->assertEquals(180.00, $order->total);

        // Line item
        $item = $order->items()->first();
        $this->assertNotNull($item);
        $this->assertEquals('Running Shoes', $item->lineitem_name);
        $this->assertEquals('SHOE-WHT-42', $item->lineitem_sku);
    }

    // ─── Shopify export: "Name" is the order name, not the customer ──────────

    public function test_shopify_export_uses_billing_name_not_order_name(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        // In Shopify exports, "Name" holds the order name (e.g. "#1082") and the
        // customer's actual name lives in "Billing Name" / "Shipping Name".
        $headers = ['Name', 'Billing Name', 'Shipping Name', 'Phone', 'Shipping City', 'Shipping Address1', 'Total', 'Lineitem name'];
        $row = ['#1082', 'Ahmed Al-Bahah', 'Ahmed Al-Bahah', '+966503315451', 'Al Bahah', 'Some Street', '179.00', 'Solar Camera'];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        fputcsv($handle, $row);
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        $file = UploadedFile::fake()->createWithContent('shopify.csv', $content);

        $this->actingAs($user)
            ->postJson(route('portal.api.orders.import'), ['file' => $file])
            ->assertStatus(200)->assertJsonPath('imported', 1);

        $order = Order::where('client_id', $user->client->id)->first();
        $this->assertEquals('Ahmed Al-Bahah', $order->customer_name);
        $this->assertEquals('Ahmed Al-Bahah', $order->shipping_name);
        $this->assertNotEquals('#1082', $order->customer_name);
    }

    // ─── Import note applied to all orders ───────────────────────────────────

    public function test_import_note_is_prepended_to_order_notes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $row = $this->sampleRow([7 => 'Customer requested gift wrap']); // Notes column
        $file = $this->csvFile([$row]);

        $this->actingAs($user)->postJson(route('portal.api.orders.import'), [
            'file'        => $file,
            'import_note' => 'Batch from June campaign',
        ])->assertStatus(200)->assertJsonPath('imported', 1);

        $order = Order::where('client_id', $user->client->id)->first();
        $this->assertStringContainsString('Batch from June campaign', $order->notes);
        $this->assertStringContainsString('Customer requested gift wrap', $order->notes);
    }

    public function test_import_note_alone_when_csv_notes_empty(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $file = $this->csvFile([$this->sampleRow()]);

        $this->actingAs($user)->postJson(route('portal.api.orders.import'), [
            'file'        => $file,
            'import_note' => 'Only this note',
        ])->assertStatus(200);

        $order = Order::where('client_id', $user->client->id)->first();
        $this->assertEquals('Only this note', $order->notes);
    }

    public function test_import_note_validation_max_2000_chars(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $file = $this->csvFile([$this->sampleRow()]);

        $this->actingAs($user)->postJson(route('portal.api.orders.import'), [
            'file'        => $file,
            'import_note' => str_repeat('x', 2001),
        ])->assertStatus(422);
    }

    // ─── Validation: name and phone required ─────────────────────────────────

    public function test_row_with_missing_name_is_skipped(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $row = $this->sampleRow([0 => '']); // blank Name
        $file = $this->csvFile([$row]);

        $response = $this->actingAs($user)
            ->postJson(route('portal.api.orders.import'), ['file' => $file]);

        $response->assertStatus(200)->assertJsonPath('imported', 0);
    }

    public function test_row_with_missing_phone_is_skipped(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $row = $this->sampleRow([1 => '']); // blank Phone
        $file = $this->csvFile([$row]);

        $response = $this->actingAs($user)
            ->postJson(route('portal.api.orders.import'), ['file' => $file]);

        $response->assertStatus(200)->assertJsonPath('imported', 0);
    }

    // ─── Individual order creation ────────────────────────────────────────────

    public function test_client_can_create_individual_order_with_note(): void
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
        $order = Order::where('order_number', $response->json('order_number'))->first();
        $this->assertNotNull($order);
        $this->assertEquals('Jane Doe', $order->customer_name);
        $this->assertEquals('Handle with care – fragile item.', $order->notes);
        $this->assertEquals('pending', $order->financial_status);
        $this->assertSame(['Pending'], $order->tags);
        $this->assertStringStartsWith('TST', $order->order_number);
    }

    public function test_create_order_requires_customer_name_and_phone(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        $this->makeClient($user);

        $this->actingAs($user)->postJson(route('portal.api.orders.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_name', 'customer_phone']);
    }

    public function test_create_order_saves_line_item_and_note(): void
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
        $this->assertEquals(1, $order->items->count());
        $this->assertEquals('Blue T-Shirt / L', $order->items->first()->lineitem_name);
        $this->assertEquals('Customer requested gift wrapping.', $order->notes);
    }
}
