<?php

namespace Tests\Feature;

use App\Models\ClientProduct;
use App\Models\InventoryRequest;
use App\Notifications\InventoryRequestSubmittedNotification;
use App\Notifications\ProductSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MakesInventory;
use Tests\Feature\Concerns\MakesStaff;
use Tests\TestCase;

/**
 * A client works in My Inventory as usual. Saving a product that is already
 * verified changes nothing on it: the change waits for the team as a request.
 */
class InventoryRequestTest extends TestCase
{
    use MakesInventory, MakesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStaff();
        Storage::fake('public');
    }

    /** What the edit form sends for a product, with some fields changed. */
    private function form(ClientProduct $product, array $changed = []): array
    {
        return $changed + [
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'quantity' => $product->quantity,
            'base_quantity' => $product->quantity,
            'unit_price' => $product->unit_price,
            'notes' => $product->notes,
        ];
    }

    public function test_editing_a_verified_product_leaves_it_alone_and_asks_instead(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);

        $this->actingAs($user)
            ->putJson("/portal/api/inventory/{$product->id}", $this->form($product, ['name' => 'Navy Mug', 'quantity' => 300]))
            ->assertOk()
            ->assertJsonPath('pending_approval', true)
            ->assertJsonPath('outcome', 'sent')
            ->assertJsonCount(2, 'requests');

        $product->refresh();
        $this->assertSame('Blue Mug', $product->name);
        $this->assertSame(100, $product->quantity);

        $stock = InventoryRequest::where('type', 'stock')->sole();
        $this->assertSame('pending', $stock->status);
        $this->assertSame(300, $stock->requested_quantity);
        $this->assertSame(100, $stock->stock_at_request);
        $this->assertSame(200, $stock->requestedChange());
        $this->assertSame($user->id, $stock->requested_by);

        $details = InventoryRequest::where('type', 'details')->sole();
        $this->assertSame(['name' => 'Navy Mug'], $details->changes);
        $this->assertSame(['name' => 'Blue Mug'], $details->original);
    }

    public function test_only_the_part_that_changed_becomes_a_request(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);

        $this->actingAs($user)
            ->putJson("/portal/api/inventory/{$product->id}", $this->form($product, ['unit_price' => '30']))
            ->assertOk();

        $request = InventoryRequest::sole();
        $this->assertSame('details', $request->type);
        $this->assertSame(['unit_price' => '30.00'], $request->changes);
    }

    public function test_saving_with_nothing_changed_asks_for_nothing(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);

        $this->actingAs($user)
            // The price comes back from the form without its decimals.
            ->putJson("/portal/api/inventory/{$product->id}", $this->form($product, ['unit_price' => '25', 'name' => ' Blue Mug ']))
            ->assertOk()
            ->assertJsonPath('outcome', 'none');

        $this->assertSame(0, InventoryRequest::count());
    }

    public function test_saving_again_replaces_what_is_waiting(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $url = "/portal/api/inventory/{$product->id}";

        $this->actingAs($user)->putJson($url, $this->form($product, ['quantity' => 300, 'name' => 'Navy Mug']))->assertOk();
        $this->actingAs($user)->putJson($url, $this->form($product, ['quantity' => 400, 'name' => 'Navy Mug']))->assertOk();

        $stock = InventoryRequest::where('type', 'stock')->sole();
        $this->assertSame(400, $stock->requested_quantity);
        $this->assertSame(2, $stock->revision);

        // The name was not touched the second time, so its request was not either.
        $this->assertSame(1, InventoryRequest::where('type', 'details')->sole()->revision);
    }

    public function test_putting_a_value_back_withdraws_the_request(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $url = "/portal/api/inventory/{$product->id}";

        $this->actingAs($user)->putJson($url, $this->form($product, ['quantity' => 300, 'name' => 'Navy Mug']))->assertOk();
        $this->actingAs($user)->putJson($url, $this->form($product))->assertOk()->assertJsonPath('outcome', 'withdrawn');

        $this->assertSame(2, InventoryRequest::where('status', 'cancelled')->count());
        $this->assertSame(0, InventoryRequest::pending()->count());
    }

    public function test_stock_that_moved_while_the_form_was_open_is_not_a_request(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);

        // The form opened on 100; five went out before it was saved.
        $form = $this->form($product);
        $product->update(['quantity' => 95]);

        $this->actingAs($user)->putJson("/portal/api/inventory/{$product->id}", $form)->assertOk()->assertJsonPath('outcome', 'none');

        $this->assertSame(0, InventoryRequest::count());
    }

    public function test_a_form_left_on_what_is_waiting_keeps_it_as_it_is(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client, ['quantity' => 95]);
        $this->stockRequest($product, 300, shown: 100);

        // Reopened: the quantity shows the 300 asked for, the stock is 95 now.
        $this->actingAs($user)
            ->putJson("/portal/api/inventory/{$product->id}", $this->form($product, ['quantity' => 300, 'base_quantity' => 95]))
            ->assertOk()
            ->assertJsonPath('outcome', 'none');

        $request = InventoryRequest::sole();
        $this->assertSame(1, $request->revision);
        $this->assertSame(200, $request->requestedChange());
    }

    public function test_a_product_still_pending_is_edited_directly(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client, ['verification_status' => 'pending']);

        $this->actingAs($user)
            ->putJson("/portal/api/inventory/{$product->id}", $this->form($product, ['name' => 'Navy Mug', 'quantity' => 40]))
            ->assertOk()
            ->assertJsonMissingPath('pending_approval');

        $product->refresh();
        $this->assertSame('Navy Mug', $product->name);
        $this->assertSame(40, $product->quantity);
        $this->assertSame(0, InventoryRequest::count());
    }

    public function test_editing_a_rejected_product_sends_it_back_for_verification(): void
    {
        Notification::fake();

        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client, ['verification_status' => 'rejected', 'rejection_reason' => 'No barcode']);
        $reviewer = $this->staffWith(['view requests', 'view product requests']);

        $this->actingAs($user)
            ->putJson("/portal/api/inventory/{$product->id}", $this->form($product, ['sku' => 'MUG-BLUE-2']))
            ->assertOk();

        $product->refresh();
        $this->assertSame('pending', $product->verification_status);
        $this->assertNull($product->rejection_reason);
        $this->assertSame('MUG-BLUE-2', $product->sku);

        Notification::assertSentTo($reviewer, ProductSubmittedNotification::class);
    }

    public function test_images_wait_with_the_request_not_on_the_product(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $existing = $product->images()->create(['path' => "client-products/{$product->id}/old.jpg", 'position' => 1]);

        $this->actingAs($user)
            ->post("/portal/api/inventory/{$product->id}", $this->form($product, [
                '_method' => 'PUT',
                'images' => [UploadedFile::fake()->image('new.jpg')],
                'remove_image_ids' => [$existing->id],
            ]), ['Accept' => 'application/json'])
            ->assertOk();

        $request = InventoryRequest::sole();
        $this->assertSame(['remove_image_ids' => [$existing->id]], $request->changes);
        $this->assertCount(1, $request->images);
        Storage::disk('public')->assertExists($request->images->first()->path);
        $this->assertStringStartsWith("client-product-requests/{$request->id}/", $request->images->first()->path);

        // Nothing happened to the product's own images.
        $this->assertSame(1, $product->images()->count());
    }

    public function test_a_request_may_not_take_a_product_past_ten_images(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);

        foreach (range(1, 10) as $position) {
            $product->images()->create(['path' => "client-products/{$product->id}/{$position}.jpg", 'position' => $position]);
        }

        $this->actingAs($user)
            ->post("/portal/api/inventory/{$product->id}", $this->form($product, [
                '_method' => 'PUT',
                'images' => [UploadedFile::fake()->image('eleventh.jpg')],
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('images');

        $this->assertSame(0, InventoryRequest::count());
    }

    public function test_an_image_of_a_verified_product_is_not_deleted_directly(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $image = $product->images()->create(['path' => "client-products/{$product->id}/old.jpg", 'position' => 1]);

        $this->actingAs($user)
            ->deleteJson("/portal/api/inventory/{$product->id}/images/{$image->id}")
            ->assertStatus(422);

        $this->assertSame(1, $product->images()->count());
    }

    public function test_a_client_cancels_a_request_that_is_still_waiting(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $waiting = $this->stockRequest($product, 300);
        $decided = $this->stockRequest($product, 500, attributes: ['status' => 'approved']);

        $this->actingAs($user)->deleteJson("/portal/api/inventory/requests/{$waiting->id}")->assertOk();
        $this->actingAs($user)->deleteJson("/portal/api/inventory/requests/{$decided->id}")->assertStatus(422);

        $this->assertSame('cancelled', $waiting->fresh()->status);
        $this->assertSame('approved', $decided->fresh()->status);
    }

    public function test_another_clients_products_and_requests_are_out_of_reach(): void
    {
        [$user] = $this->fulfilmentClient();
        [, $other] = $this->fulfilmentClient('Other Trading');
        $product = $this->makeProduct($other);
        $request = $this->stockRequest($product, 300);

        $this->actingAs($user)->putJson("/portal/api/inventory/{$product->id}", $this->form($product, ['quantity' => 5]))->assertForbidden();
        $this->actingAs($user)->deleteJson("/portal/api/inventory/requests/{$request->id}")->assertForbidden();
        $this->actingAs($user)->getJson('/portal/api/inventory/requests')->assertOk()->assertJsonCount(0, 'data');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_the_requests_tab_and_the_product_list_show_what_is_waiting(): void
    {
        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $this->stockRequest($product, 300);
        $this->detailsRequest($product, ['name' => 'Navy Mug'], ['status' => 'rejected', 'rejection_reason' => 'Name already used']);

        $this->actingAs($user)->getJson('/portal/api/inventory/requests')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('pending', 1)
            ->assertJsonPath('data.0.rejection_reason', 'Name already used')
            ->assertJsonPath('data.1.change', 200)
            // Who on the team decided it is the team's business.
            ->assertJsonMissingPath('data.0.reviewed_by');

        $this->actingAs($user)->getJson('/portal/api/inventory/requests?status=pending')->assertJsonCount(1, 'data');

        $this->actingAs($user)->getJson('/portal/api/inventory')
            ->assertOk()
            ->assertJsonCount(1, 'data.0.pending_requests')
            ->assertJsonPath('data.0.pending_requests.0.requested_quantity', 300);
    }

    public function test_a_client_without_my_inventory_gets_nowhere(): void
    {
        [$dropshipper] = $this->fulfilmentClient('Drop Co', ['client_types' => ['dropshipper']]);
        [$noFeature] = $this->fulfilmentClient('Bare Co', ['portal_features' => ['orders']]);

        $this->actingAs($dropshipper)->getJson('/portal/api/inventory/requests')->assertForbidden();
        $this->actingAs($noFeature)->getJson('/portal/api/inventory/requests')->assertForbidden();
    }

    public function test_the_team_is_told_once_and_only_those_who_handle_it(): void
    {
        Notification::fake();

        [$user, $client] = $this->fulfilmentClient();
        [, $other] = $this->fulfilmentClient('Other Trading');
        $product = $this->makeProduct($client);

        $stockClerk = $this->staffWith(['view requests', 'view stock requests']);
        $detailsClerk = $this->staffWith(['view requests', 'view detail requests']);
        $elsewhere = $this->limitTo($this->staffWith(['view requests', 'view stock requests']), $other);
        $url = "/portal/api/inventory/{$product->id}";

        $this->actingAs($user)->putJson($url, $this->form($product, ['quantity' => 300]))->assertOk();
        // Replacing what is waiting is not news.
        $this->actingAs($user)->putJson($url, $this->form($product, ['quantity' => 400]))->assertOk();

        Notification::assertSentToTimes($stockClerk, InventoryRequestSubmittedNotification::class, 1);
        Notification::assertNotSentTo($detailsClerk, InventoryRequestSubmittedNotification::class);
        Notification::assertNotSentTo($elsewhere, InventoryRequestSubmittedNotification::class);
    }
}
