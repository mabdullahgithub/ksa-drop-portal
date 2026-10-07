<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * A parcel that ends returned or cancelled while a rider has it stays in
 * their hands until it is scanned back in at the hub. And a parcel has one
 * status at a time: delivered after a failed attempt is delivered.
 */
class RiderReturnTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
        Storage::fake('local');
        Notification::fake();
    }

    private function update(string $token, Shipment $shipment, array $data)
    {
        return $this->asRider($token)->post("/rider/api/shipments/{$shipment->id}/events", $this->withProof($data) + [
            'client_uuid' => (string) Str::uuid(),
            'entry_method' => 'camera',
        ]);
    }

    /**
     * A failed attempt needs a photo of the place; tests that
     * aren't about that get one unless they pass `photo` themselves.
     */
    private function withProof(array $data): array
    {
        return ($data['action'] ?? null) === 'attempt_failed'
            ? $data + ['photo' => UploadedFile::fake()->image('place.jpg', 800, 600)]
            : $data;
    }

    private function claim(string $token, Shipment $shipment)
    {
        return $this->asRider($token)->postJson('/rider/api/claim', [
            'code' => $shipment->tracking_number,
            'client_uuid' => (string) Str::uuid(),
            'entry_method' => 'camera',
        ]);
    }

    public function test_a_rider_marks_a_parcel_returned_and_it_stays_with_them_until_handed_back(): void
    {
        $rider = $this->makeRider(['name' => 'Ahmed Khan']);
        $token = $this->signedInDevice($rider);
        $shipment = $this->ksaShipment();
        $this->claim($token, $shipment)->assertJsonPath('result', 'claimed');

        $this->update($token, $shipment, ['action' => 'returned'])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->update($token, $shipment, ['action' => 'returned', 'reason' => 'refused'])
            ->assertOk()
            ->assertJsonPath('parcel.status', 'returned')
            ->assertJsonPath('parcel.to_return', true)
            ->assertJsonPath('parcel.blocked', null)
            ->assertJsonPath('parcel.allowed_actions', ['returned_to_hub']);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::RETURNED->value, $shipment->status);
        $this->assertSame('Customer refused the parcel (rider Ahmed)', $shipment->cancel_reason);
        $this->assertNull($shipment->hub_received_at);
        $this->assertSame('cancelled', $shipment->order->fulfillment_status);
        $this->assertSame('Not delivered, returning to sender — Customer refused the parcel', $shipment->tracking_history[0]['description']);

        // Still in the rider's hands: listed, and counted as one to return.
        $this->asRider($token)->getJson('/rider/api/parcels')
            ->assertOk()->assertJsonCount(1, 'parcels')->assertJsonPath('parcels.0.to_return', true);
        $this->asRider($token)->getJson('/rider/api/me')
            ->assertJsonPath('today.held', 1)
            ->assertJsonPath('today.to_return', 1)
            ->assertJsonPath('today.failed', 0);

        // It can't be delivered any more.
        $this->update($token, $shipment, ['action' => 'delivered', 'cod_amount' => 100, 'payment_method' => 'cash'])->assertStatus(409);

        // Scanned back in at the hub.
        $this->claim($token, $shipment)->assertJsonPath('result', 'already_mine');
        $this->update($token, $shipment, ['action' => 'returned_to_hub'])
            ->assertOk()
            ->assertJsonPath('parcel.status', 'returned')
            ->assertJsonPath('parcel.to_return', false)
            ->assertJsonPath('parcel.blocked', 'finished');

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::RETURNED->value, $shipment->status);
        $this->assertNotNull($shipment->hub_received_at);
        $this->assertSame($rider->id, $shipment->rider_id);
        $this->assertSame('Returned to KSA Drop', $shipment->tracking_history[0]['description']);

        $this->asRider($token)->getJson('/rider/api/parcels')->assertJsonCount(0, 'parcels');
        $this->asRider($token)->getJson('/rider/api/me')->assertJsonPath('today.held', 0)->assertJsonPath('today.to_return', 0);
        $this->claim($token, $shipment)->assertJsonPath('result', 'finished');
    }

    public function test_a_rider_marks_a_parcel_cancelled_and_the_order_can_be_booked_again(): void
    {
        $token = $this->signedInDevice($this->makeRider(['name' => 'Ahmed Khan']));
        $shipment = $this->ksaShipment();
        $this->claim($token, $shipment);

        $this->update($token, $shipment, ['action' => 'cancelled', 'reason' => 'other'])->assertStatus(422)->assertJsonValidationErrors('note');

        $this->update($token, $shipment, ['action' => 'cancelled', 'reason' => 'other', 'note' => 'Ordered by mistake'])
            ->assertOk()
            ->assertJsonPath('parcel.status', 'cancelled')
            ->assertJsonPath('parcel.allowed_actions', ['returned_to_hub']);

        $shipment->refresh();
        $this->assertSame('Other — Ordered by mistake (rider Ahmed)', $shipment->cancel_reason);
        $this->assertNotNull($shipment->cancelled_at);
        // Cancelled leaves the order alone, as an admin's cancel does.
        $this->assertNotSame('cancelled', $shipment->order->fulfillment_status);
    }

    public function test_a_parcel_must_be_with_the_rider_before_they_can_end_it(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $shipment = $this->ksaShipment();

        $this->asRider($token)->getJson("/rider/api/scan?code={$shipment->tracking_number}")
            ->assertJsonPath('parcel.allowed_actions', ['out_for_delivery', 'delivered', 'attempt_failed']);

        $this->update($token, $shipment, ['action' => 'returned', 'reason' => 'refused'])->assertStatus(409);
        $this->update($token, $shipment, ['action' => 'returned_to_hub'])->assertStatus(409);

        $this->assertSame(ShipmentStatus::INFO_RECEIVED->value, $shipment->fresh()->status);
    }

    public function test_a_parcel_an_admin_cancels_stays_with_its_rider_to_hand_back(): void
    {
        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);
        $shipment = $this->ksaShipment();
        $this->claim($token, $shipment);

        $this->actingAs($this->staff())->postJson("/api/shipments/{$shipment->id}/cancel", ['reason' => 'Customer called to cancel'])->assertOk();

        $this->asRider($token)->getJson('/rider/api/parcels')
            ->assertJsonCount(1, 'parcels')
            ->assertJsonPath('parcels.0.status', 'cancelled')
            ->assertJsonPath('parcels.0.to_return', true)
            ->assertJsonPath('parcels.0.allowed_actions', ['returned_to_hub']);

        // Another rider can't hand it back for them.
        $other = $this->signedInDevice($this->makeRider(['name' => 'Bilal']));
        $this->asRider($other)->getJson("/rider/api/scan?code={$shipment->tracking_number}")
            ->assertJsonPath('parcel.blocked', 'finished')->assertJsonPath('parcel.to_return', false);
        $this->update($other, $shipment, ['action' => 'returned_to_hub'])->assertStatus(409);

        // A rider with parcels to hand back can't be removed.
        $this->actingAs($this->staff())->deleteJson("/api/riders/{$rider->id}")->assertStatus(422);

        $this->update($token, $shipment, ['action' => 'returned_to_hub'])->assertOk();
        $this->assertNotNull($shipment->fresh()->hub_received_at);
    }

    public function test_an_admin_marks_a_parcel_returned_and_takes_it_back_at_the_hub(): void
    {
        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);
        $shipment = $this->ksaShipment();
        $this->claim($token, $shipment);
        $admin = $this->staff();

        $this->actingAs($admin)->postJson("/api/shipments/{$shipment->id}/return", [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->actingAs($admin)->postJson("/api/shipments/{$shipment->id}/receive-at-hub")->assertStatus(422);

        $this->actingAs($admin)->postJson("/api/shipments/{$shipment->id}/return", ['reason' => 'Customer refused twice'])
            ->assertOk()
            ->assertJsonPath('shipment.status', 'returned');

        $shipment->refresh();
        $this->assertSame('Customer refused twice', $shipment->cancel_reason);
        $this->assertTrue($shipment->awaitsHandBack());
        $this->asRider($token)->getJson('/rider/api/me')->assertJsonPath('today.to_return', 1);

        // Already returned: not twice.
        $this->actingAs($admin)->postJson("/api/shipments/{$shipment->id}/return", ['reason' => 'Again'])->assertStatus(422);

        // The rider's phone is lost; the hub takes the parcel in.
        $this->actingAs($admin)->postJson("/api/shipments/{$shipment->id}/receive-at-hub")
            ->assertOk()
            ->assertJsonPath('shipment.status', 'returned');

        $shipment->refresh();
        $this->assertNotNull($shipment->hub_received_at);
        $this->assertSame($rider->id, $shipment->rider_id);
        $this->asRider($token)->getJson('/rider/api/parcels')->assertJsonCount(0, 'parcels');

        $event = ShipmentEvent::where('action', 'returned_to_hub')->firstOrFail();
        $this->assertSame($admin->id, $event->user_id);
        $this->assertNull($event->rider_id);

        $this->actingAs($admin)->postJson("/api/shipments/{$shipment->id}/receive-at-hub")->assertStatus(422);
    }

    public function test_only_ksa_express_parcels_are_marked_returned_from_the_portal(): void
    {
        $jnt = $this->ksaShipment(['courier' => 'jnt_express', 'tracking_number' => 'JT0002']);

        $this->actingAs($this->staff())->postJson("/api/shipments/{$jnt->id}/return", ['reason' => 'x'])->assertStatus(422);
        $this->actingAs($this->staff(['view orders']))->postJson("/api/shipments/{$jnt->id}/return", ['reason' => 'x'])->assertForbidden();

        $this->assertSame(ShipmentStatus::INFO_RECEIVED->value, $jnt->fresh()->status);
    }

    public function test_a_parcel_delivered_after_a_failed_attempt_is_no_longer_failed(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $retried = $this->ksaShipment();
        $stillFailed = $this->ksaShipment();
        $this->claim($token, $retried);
        $this->claim($token, $stillFailed);

        $this->update($token, $retried, ['action' => 'attempt_failed', 'reason' => 'no_answer'])->assertOk();
        $this->update($token, $stillFailed, ['action' => 'attempt_failed', 'reason' => 'closed'])->assertOk();

        $this->asRider($token)->getJson('/rider/api/me')
            ->assertJsonPath('today.held', 2)
            ->assertJsonPath('today.failed', 2)
            ->assertJsonPath('today.delivered', 0);

        $this->update($token, $retried, ['action' => 'delivered', 'cod_amount' => 100, 'payment_method' => 'cash'])->assertOk();

        $this->asRider($token)->getJson('/rider/api/me')
            ->assertJsonPath('today.held', 1)
            ->assertJsonPath('today.failed', 1)
            ->assertJsonPath('today.delivered', 1);

        // Taken out again: out for delivery, not failed.
        $this->update($token, $stillFailed, ['action' => 'out_for_delivery'])->assertOk();

        $this->asRider($token)->getJson('/rider/api/me')
            ->assertJsonPath('today.held', 1)
            ->assertJsonPath('today.failed', 0)
            ->assertJsonPath('today.delivered', 1);
    }
}
