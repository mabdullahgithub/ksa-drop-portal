<?php

namespace Tests\Feature;

use App\Models\InventoryRequest;
use App\Models\User;
use App\Notifications\InventoryRequestReviewedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MakesInventory;
use Tests\Feature\Concerns\MakesStaff;
use Tests\TestCase;

/**
 * The Requests page: the team decides what clients asked for. Approving is
 * the only way a client's change reaches a verified product.
 */
class InventoryRequestReviewTest extends TestCase
{
    use MakesInventory, MakesStaff, RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStaff();
        Storage::fake('public');

        $this->unlockRequests();

        $this->staff = $this->fullAccessUser();
    }

    // ------------------------------------------------------------------ stock

    public function test_approving_adds_the_count_received_to_the_stock_as_it_is_now(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $request = $this->stockRequest($product, 300);

        // Five went out on a scan while the request waited.
        $product->update(['quantity' => 95]);

        $this->actingAs($this->staff)
            ->postJson("/api/requests/stock/{$request->id}/approve", ['units' => 180, 'revision' => 1])
            ->assertOk()
            ->assertJsonPath('request.status', 'approved')
            ->assertJsonPath('request.stock_after', 275);

        $this->assertSame(275, $product->fresh()->quantity);

        $request->refresh();
        $this->assertSame(180, $request->received_quantity);
        $this->assertSame(95, $request->stock_before);
        $this->assertSame(275, $request->stock_after);
        $this->assertSame($this->staff->id, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);
    }

    public function test_a_request_for_less_stock_takes_units_off_but_never_below_zero(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $request = $this->stockRequest($product, 80);

        $this->actingAs($this->staff)
            ->postJson("/api/requests/stock/{$request->id}/approve", ['units' => 150])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'not_enough');

        $this->assertSame(100, $product->fresh()->quantity);
        $this->assertSame('pending', $request->fresh()->status);

        $this->actingAs($this->staff)
            ->postJson("/api/requests/stock/{$request->id}/approve", ['units' => 20])
            ->assertOk();

        $this->assertSame(80, $product->fresh()->quantity);
        $this->assertSame(-20, $request->fresh()->received_quantity);
    }

    public function test_approving_can_put_a_product_back_in_stock(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client, ['quantity' => 0, 'is_out_of_stock' => true]);
        $request = $this->stockRequest($product, 50);

        $this->actingAs($this->staff)
            ->postJson("/api/requests/stock/{$request->id}/approve", ['units' => 50, 'mark_in_stock' => true])
            ->assertOk();

        $product->refresh();
        $this->assertSame(50, $product->quantity);
        $this->assertFalse($product->is_out_of_stock);
    }

    public function test_the_count_received_is_required(): void
    {
        [, $client] = $this->fulfilmentClient();
        $request = $this->stockRequest($this->makeProduct($client), 300);

        $this->actingAs($this->staff)->postJson("/api/requests/stock/{$request->id}/approve", [])->assertStatus(422);
        // Nothing arrived is a rejection, not an approval of zero.
        $this->actingAs($this->staff)->postJson("/api/requests/stock/{$request->id}/approve", ['units' => 0])->assertStatus(422);
    }

    // ---------------------------------------------------------------- details

    public function test_approving_a_details_change_applies_it_to_the_product(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $old = $product->images()->create(['path' => "client-products/{$product->id}/old.jpg", 'position' => 1]);
        Storage::disk('public')->put($old->path, 'old');

        $request = $this->detailsRequest($product, ['name' => 'Navy Mug', 'unit_price' => '30.00', 'remove_image_ids' => [$old->id]]);
        $proposed = UploadedFile::fake()->image('new.jpg')->store(InventoryRequest::imageDirectory($request->id), 'public');
        $request->images()->create(['path' => $proposed, 'position' => 1]);

        $this->actingAs($this->staff)
            ->postJson("/api/requests/details/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('request.status', 'approved');

        $product->refresh();
        $this->assertSame('Navy Mug', $product->name);
        $this->assertSame('30.00', $product->unit_price);
        // Untouched fields stay as they were.
        $this->assertSame('MUG-BLUE', $product->sku);
        $this->assertSame(100, $product->quantity);

        $image = $product->images()->sole();
        $this->assertStringStartsWith("client-products/{$product->id}/", $image->path);
        Storage::disk('public')->assertExists($image->path);
        Storage::disk('public')->assertMissing($old->path);
        Storage::disk('public')->assertMissing($proposed);
        $this->assertSame(0, $request->images()->count());
    }

    public function test_the_team_is_warned_when_the_new_sku_belongs_to_another_product(): void
    {
        [, $client] = $this->fulfilmentClient();
        $this->makeProduct($client, ['sku' => 'MUG-RED']);
        $taken = $this->detailsRequest($this->makeProduct($client), ['sku' => 'MUG-RED']);
        $free = $this->detailsRequest($this->makeProduct($client), ['sku' => 'MUG-GREEN']);

        $rows = collect($this->actingAs($this->staff)->getJson('/api/requests/details')->assertOk()->json('data'))->keyBy('id');

        $this->assertTrue($rows[$taken->id]['sku_in_use']);
        $this->assertFalse($rows[$free->id]['sku_in_use']);
        $this->assertSame(['sku' => 'MUG-BLUE'], $rows[$taken->id]['current']);
    }

    // --------------------------------------------------------------- rejecting

    public function test_rejecting_needs_a_reason_and_drops_what_was_proposed(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $request = $this->detailsRequest($product, ['name' => 'Navy Mug']);
        $proposed = UploadedFile::fake()->image('new.jpg')->store(InventoryRequest::imageDirectory($request->id), 'public');
        $request->images()->create(['path' => $proposed, 'position' => 1]);

        $this->actingAs($this->staff)->postJson("/api/requests/details/{$request->id}/reject", [])->assertStatus(422);

        $this->actingAs($this->staff)
            ->postJson("/api/requests/details/{$request->id}/reject", ['reason' => 'Name already used'])
            ->assertOk();

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertSame('Name already used', $request->rejection_reason);
        $this->assertSame('Blue Mug', $product->fresh()->name);
        Storage::disk('public')->assertMissing($proposed);
    }

    // ------------------------------------------------- deciding once, in time

    public function test_a_request_is_decided_once(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $request = $this->stockRequest($product, 300);

        $this->actingAs($this->staff)->postJson("/api/requests/stock/{$request->id}/approve", ['units' => 200])->assertOk();

        $this->actingAs($this->staff)
            ->postJson("/api/requests/stock/{$request->id}/approve", ['units' => 200])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'already_decided');
        $this->actingAs($this->staff)->postJson("/api/requests/stock/{$request->id}/reject", ['reason' => 'Late'])->assertStatus(409);

        $this->assertSame(300, $product->fresh()->quantity);
    }

    public function test_a_request_the_client_changed_meanwhile_is_not_approved_blind(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $request = $this->stockRequest($product, 300, attributes: ['revision' => 2]);

        $this->actingAs($this->staff)
            ->postJson("/api/requests/stock/{$request->id}/approve", ['units' => 200, 'revision' => 1])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'changed');

        $this->assertSame(100, $product->fresh()->quantity);
    }

    public function test_one_queues_permission_cannot_decide_the_others_request(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $details = $this->detailsRequest($product, ['name' => 'Navy Mug']);

        $this->actingAs($this->staff)->postJson("/api/requests/stock/{$details->id}/approve", ['units' => 5])->assertNotFound();
        $this->actingAs($this->staff)->postJson("/api/requests/stock/{$details->id}/reject", ['reason' => 'No'])->assertNotFound();

        $this->assertSame('pending', $details->fresh()->status);
    }

    // ----------------------------------------------- things changing underneath

    public function test_deleting_a_product_cancels_what_was_waiting_on_it(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $waiting = $this->stockRequest($product, 300);
        $decided = $this->stockRequest($product, 500, attributes: ['status' => 'approved']);

        $product->delete();

        $this->assertSame('cancelled', $waiting->fresh()->status);
        $this->assertSame('approved', $decided->fresh()->status);

        // And it stays cancelled when the product comes back.
        $product->restore();
        $this->assertSame('cancelled', $waiting->fresh()->status);
    }

    public function test_a_product_no_longer_verified_has_nothing_waiting_on_it(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $request = $this->stockRequest($product, 300);

        $this->actingAs($this->staff)
            ->postJson("/api/requests/products/{$product->id}/review", ['action' => 'reject', 'rejection_reason' => 'Recalled'])
            ->assertOk();

        $this->assertSame('cancelled', $request->fresh()->status);
    }

    public function test_a_client_in_the_recycle_bin_takes_its_requests_out_of_sight(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $this->stockRequest($product, 300);
        $this->makeProduct($client, ['verification_status' => 'pending', 'sku' => 'NEW-1']);

        $this->actingAs($this->staff)->getJson('/api/requests/counts')
            ->assertOk()
            ->assertJsonPath('counts.stock', 1)
            ->assertJsonPath('counts.products', 1);

        $client->delete();

        $this->actingAs($this->staff)->getJson('/api/requests/counts')
            ->assertJsonPath('counts.stock', 0)
            ->assertJsonPath('counts.products', 0);
        $this->actingAs($this->staff)->getJson('/api/requests/stock')->assertJsonCount(0, 'data');
        $this->actingAs($this->staff)->getJson('/api/requests/products')->assertJsonCount(0, 'data');
    }

    // ------------------------------------------------------------------ lists

    public function test_a_queue_shows_what_is_waiting_oldest_first_and_keeps_the_history(): void
    {
        [, $client] = $this->fulfilmentClient('Acme Trading');
        [, $other] = $this->fulfilmentClient('Zed Stores');
        $first = $this->stockRequest($this->makeProduct($client, ['name' => 'Blue Mug']), 300);
        $first->forceFill(['created_at' => now()->subDays(3)])->save();
        $second = $this->stockRequest($this->makeProduct($other, ['name' => 'Red Plate']), 50);
        $done = $this->stockRequest($this->makeProduct($client), 10, attributes: ['status' => 'rejected', 'rejection_reason' => 'Never arrived']);

        $this->actingAs($this->staff)->getJson('/api/requests/stock')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.change', 200)
            ->assertJsonPath('data.0.client.company_name', 'Acme Trading')
            ->assertJsonPath('data.1.id', $second->id);

        $this->actingAs($this->staff)->getJson('/api/requests/stock?status=rejected')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $done->id);

        $this->actingAs($this->staff)->getJson("/api/requests/stock?client_id={$other->id}")->assertJsonCount(1, 'data');
        $this->actingAs($this->staff)->getJson('/api/requests/stock?search=plate')->assertJsonCount(1, 'data');
        $this->actingAs($this->staff)->getJson('/api/requests/stock?search=acme')->assertJsonCount(1, 'data');
    }

    // ----------------------------------------------------------- new products

    public function test_a_new_product_is_verified_with_the_count_received(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client, ['verification_status' => 'pending', 'quantity' => 500]);

        $this->actingAs($this->staff)->getJson('/api/requests/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client.company_name', 'Acme Trading');

        $this->actingAs($this->staff)
            ->postJson("/api/requests/products/{$product->id}/review", ['action' => 'verify', 'received_quantity' => 480])
            ->assertOk();

        $product->refresh();
        $this->assertSame('verified', $product->verification_status);
        $this->assertSame(480, $product->quantity);

        $this->actingAs($this->staff)->getJson('/api/requests/products')->assertJsonCount(0, 'data');
        $this->actingAs($this->staff)->getJson('/api/requests/products?status=verified')->assertJsonCount(1, 'data');
    }

    // ------------------------------------------------------------------- bulk

    public function test_several_stock_updates_are_approved_as_requested_and_the_rest_reported(): void
    {
        [, $client] = $this->fulfilmentClient();
        $mug = $this->makeProduct($client);
        $plate = $this->makeProduct($client, ['name' => 'Red Plate', 'sku' => 'PLATE', 'quantity' => 10]);
        $more = $this->stockRequest($mug, 150);
        $tooFew = $this->stockRequest($plate, 0, shown: 40);
        $done = $this->stockRequest($mug, 900, attributes: ['status' => 'approved']);
        $details = $this->detailsRequest($mug, ['name' => 'Navy Mug']);

        $response = $this->actingAs($this->staff)
            ->postJson('/api/requests/stock/bulk-approve', ['ids' => [$more->id, $tooFew->id, $done->id, $details->id]])
            ->assertOk()
            ->assertJsonPath('done', 1);

        $this->assertSame(150, $mug->fresh()->quantity);
        // Asked to take 40 off a product with 10 left.
        $this->assertSame(10, $plate->fresh()->quantity);
        $this->assertEqualsCanonicalizing([$tooFew->id, $done->id, $details->id], array_column($response->json('skipped'), 'id'));
        $this->assertSame('pending', $details->fresh()->status);
    }

    public function test_several_details_changes_are_rejected_with_one_reason(): void
    {
        [, $client] = $this->fulfilmentClient();
        $one = $this->detailsRequest($this->makeProduct($client), ['name' => 'A']);
        $two = $this->detailsRequest($this->makeProduct($client), ['name' => 'B']);

        $this->actingAs($this->staff)->postJson('/api/requests/details/bulk-reject', ['ids' => [$one->id, $two->id]])->assertStatus(422);

        $this->actingAs($this->staff)
            ->postJson('/api/requests/details/bulk-reject', ['ids' => [$one->id, $two->id], 'reason' => 'Send photos first'])
            ->assertOk()
            ->assertJsonPath('done', 2);

        $this->assertSame(['rejected', 'rejected'], [$one->fresh()->status, $two->fresh()->status]);
        $this->assertSame('Send photos first', $two->fresh()->rejection_reason);

        $this->actingAs($this->staff)
            ->postJson('/api/requests/details/bulk-approve', ['ids' => [$one->id]])
            ->assertOk()
            ->assertJsonPath('done', 0);
    }

    // ---------------------------------------------------------- notifications

    public function test_the_client_hears_what_was_decided(): void
    {
        Notification::fake();

        [$user, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $stock = $this->stockRequest($product, 300);
        $details = $this->detailsRequest($product, ['name' => 'Navy Mug']);

        $this->actingAs($this->staff)->postJson("/api/requests/stock/{$stock->id}/approve", ['units' => 180])->assertOk();
        $this->actingAs($this->staff)->postJson("/api/requests/details/{$details->id}/reject", ['reason' => 'Name already used'])->assertOk();

        Notification::assertSentToTimes($user, InventoryRequestReviewedNotification::class, 2);

        $messages = Notification::sent($user, InventoryRequestReviewedNotification::class)
            ->map(fn ($notification) => $notification->toArray($user)['message']);

        $this->assertStringContainsString('180 units were received', $messages[0]);
        $this->assertStringContainsString('Stock is now 280', $messages[0]);
        $this->assertStringContainsString('Name already used', $messages[1]);
    }
}
