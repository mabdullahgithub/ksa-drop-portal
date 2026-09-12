<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Shopify App Store requirement 5.5.2 asks a product sourcing app to put the
 * cost of goods sold in the Cost field of the merchant's product page. The
 * catalogue CSV a dropshipper downloads is how our products reach their store,
 * so it has to carry Shopify's "Cost per item" column.
 */
class DropshipperCatalogCostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('client');
    }

    private function dropshipper(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        Client::create([
            'user_id'       => $user->id,
            'company_name'  => 'Dropship Co',
            'short_id'      => 'DROP',
            'client_types'  => ['dropshipper'],
        ]);

        return $user;
    }

    private function download(User $user): array
    {
        $response = $this->actingAs($user)->get('/portal/api/products/download');
        $response->assertOk();

        $csv = $response->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv)), fn ($l) => $l !== ''));

        return $rows;
    }

    public function test_catalog_csv_carries_the_cost_per_item_column(): void
    {
        $user = $this->dropshipper();

        Product::create([
            'handle'        => 'abaya-black',
            'title'         => 'Black Abaya',
            'variant_sku'   => 'ABY-001',
            'variant_price' => 149.00,
            'cost_per_item' => 42.00,
            'published'     => true,
        ]);

        [$header, $row] = $this->download($user);

        $this->assertContains('Cost per item', $header, 'Shopify reads the Cost field from this column.');

        $cost = $row[array_search('Cost per item', $header)];
        $this->assertSame('149.00', $cost, 'The merchant pays our listed portal price.');
    }

    public function test_cost_falls_back_to_the_saudi_market_price(): void
    {
        $user = $this->dropshipper();

        Product::create([
            'handle'             => 'oud-perfume',
            'title'              => 'Oud Perfume',
            'variant_sku'        => 'OUD-001',
            'variant_price'      => null,
            'price_saudi_arabia' => 89.50,
            'published'          => true,
        ]);

        [$header, $row] = $this->download($user);

        $this->assertSame('89.50', $row[array_search('Cost per item', $header)]);
    }

    public function test_our_own_acquisition_cost_never_reaches_the_merchant(): void
    {
        $user = $this->dropshipper();

        Product::create([
            'handle'        => 'abaya-black',
            'title'         => 'Black Abaya',
            'variant_sku'   => 'ABY-001',
            'variant_price' => 149.00,
            'cost_per_item' => 42.00,
            'published'     => true,
        ]);

        [$header, $row] = $this->download($user);

        $this->assertNotContains('42.00', $row, 'cost_per_item is our margin, not the merchant"s.');
    }

    public function test_headers_match_shopifys_current_csv_format(): void
    {
        $user = $this->dropshipper();

        Product::create([
            'handle'        => 'abaya-black',
            'title'         => 'Black Abaya',
            'variant_price' => 149.00,
            'published'     => true,
        ]);

        [$header] = $this->download($user);

        // The legacy "Handle"/"Variant *" names still import, but Cost per item
        // was dropped on the way through, leaving merchants an empty Cost field.
        $this->assertNotContains('Handle', $header);
        $this->assertNotContains('Variant SKU', $header);
        $this->assertNotContains('Body (HTML)', $header);

        foreach (['Title', 'URL handle', 'Description', 'SKU', 'Price', 'Cost per item'] as $expected) {
            $this->assertContains($expected, $header);
        }
    }

    public function test_every_row_lines_up_with_the_header(): void
    {
        $user = $this->dropshipper();

        $product = Product::create([
            'handle'        => 'abaya-black',
            'title'         => 'Black Abaya',
            'variant_sku'   => 'ABY-001',
            'variant_price' => 149.00,
            'published'     => true,
        ]);

        // A second image emits a continuation row, which pads every other column.
        foreach ([1, 2] as $position) {
            $product->images()->create([
                'src'      => "https://cdn.ksadrop.com/abaya-{$position}.jpg",
                'position' => $position,
            ]);
        }

        $rows = $this->download($user);
        $width = count($rows[0]);

        $this->assertCount(3, $rows, 'header + product row + one extra-image row');

        foreach ($rows as $i => $row) {
            $this->assertCount($width, $row, "row {$i} does not match the header width");
        }
    }

    /**
     * The column that decides whether the fulfillment flow exists at all.
     *
     * A variant imported as `manual` is one the merchant fulfils themselves:
     * Shopify assigns its fulfillment orders to the merchant's own location,
     * never to KSADrop, so requirement 5.5.1's
     * fulfillmentOrderSubmitFulfillmentRequest has nothing to act on and the
     * order never reaches us for fulfillment.
     */
    public function test_catalog_csv_routes_variants_to_the_stores_ksadrop_service(): void
    {
        $user = $this->dropshipper();

        ClientShopifyConnection::create([
            'client_id'                  => Client::where('user_id', $user->id)->value('id'),
            'shop_domain'                => 'mystore.myshopify.com',
            'access_token'               => 'tok-123',
            'token_expires_at'           => now()->addHour(),
            'status'                     => 'active',
            'fulfillment_service_id'     => 'gid://shopify/FulfillmentService/1',
            // Shopify's own handle, which need not be a slug of our name: where
            // the handle is already taken on a store, Shopify appends a suffix,
            // and an import matching nothing falls back to `manual` in silence.
            'fulfillment_service_handle' => 'ksadrop-2',
            'fulfillment_location_id'    => 'gid://shopify/Location/9',
        ]);

        Product::create([
            'handle' => 'abaya-black', 'title' => 'Black Abaya', 'variant_sku' => 'ABY-001',
            'variant_price' => 149.00, 'published' => true,
        ]);

        [$header, $row] = $this->download($user);

        $this->assertSame('ksadrop-2', $row[array_search('Fulfillment service', $header)]);
    }

    public function test_catalog_csv_falls_back_to_manual_when_no_service_is_registered(): void
    {
        $user = $this->dropshipper();

        Product::create([
            'handle' => 'abaya-black', 'title' => 'Black Abaya', 'variant_sku' => 'ABY-001',
            'variant_price' => 149.00, 'published' => true,
        ]);

        [$header, $row] = $this->download($user);

        // A client who has not connected a store still gets a catalogue that
        // imports cleanly — just one they fulfil themselves.
        $this->assertSame('manual', $row[array_search('Fulfillment service', $header)]);
    }
}
