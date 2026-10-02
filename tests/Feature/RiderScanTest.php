<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Notifications\ShipmentDeliveredNotification;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * Scan a label → the update screen → Update (or Cancel, which sends nothing).
 */
class RiderScanTest extends TestCase
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
     * A failed attempt or a return needs a photo of the place; tests that
     * aren't about that get one unless they pass `photo` themselves.
     */
    private function withProof(array $data): array
    {
        return in_array($data['action'] ?? null, ['attempt_failed', 'returned'], true)
            ? $data + ['photo' => UploadedFile::fake()->image('place.jpg', 800, 600)]
            : $data;
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('pod.jpg', 800, 600);
    }

    public function test_scanning_the_waybill_shows_the_parcel_and_what_can_be_done(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $shipment = $this->ksaShipment();

        $this->asRider($token)->getJson('/rider/api/scan?code=' . strtolower($shipment->tracking_number))
            ->assertOk()
            ->assertJsonPath('parcel.id', $shipment->id)
            ->assertJsonPath('parcel.receiver.name', 'Confirmed Receiver')
            ->assertJsonPath('parcel.receiver.phone', '0555555555')
            ->assertJsonPath('parcel.cod_amount', 100)
            ->assertJsonPath('parcel.remark', 'Call before arriving')
            ->assertJsonPath('parcel.allowed_actions', ['out_for_delivery', 'delivered', 'attempt_failed'])
            ->assertJsonPath('parcel.blocked', null);
    }

    public function test_the_label_qr_code_and_the_order_number_find_the_parcel_too(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $shipment = $this->ksaShipment();

        $this->asRider($token)->getJson('/rider/api/scan?code=' . urlencode("http://localhost/track?q={$shipment->tracking_number}"))
            ->assertOk()->assertJsonPath('parcel.id', $shipment->id);

        $this->asRider($token)->getJson('/rider/api/scan?code=' . urlencode($shipment->order->order_number))
            ->assertOk()->assertJsonPath('parcel.id', $shipment->id);

        $this->asRider($token)->getJson('/rider/api/scan?code=NOPE123')
            ->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_out_for_delivery_assigns_the_parcel_to_the_rider(): void
    {
        $rider = $this->makeRider(['name' => 'Ahmed Khan']);
        $token = $this->signedInDevice($rider);
        $shipment = $this->ksaShipment();

        $this->update($token, $shipment, ['action' => 'out_for_delivery'])
            ->assertOk()
            ->assertJsonPath('parcel.status', 'out_for_delivery')
            ->assertJsonPath('parcel.held_by_me', true)
            ->assertJsonPath('parcel.allowed_actions', ['delivered', 'attempt_failed', 'returned', 'cancelled']);

        $shipment->refresh();
        $this->assertSame($rider->id, $shipment->rider_id);
        $this->assertSame('Out for delivery with Ahmed', $shipment->tracking_history[0]['description']);

        $this->asRider($token)->getJson('/rider/api/parcels')
            ->assertOk()->assertJsonCount(1, 'parcels')->assertJsonPath('parcels.0.id', $shipment->id);
    }

    public function test_another_rider_cannot_update_a_parcel_someone_else_holds(): void
    {
        $shipment = $this->ksaShipment();
        $this->update($this->signedInDevice($this->makeRider(['name' => 'Ahmed Khan'])), $shipment, ['action' => 'out_for_delivery'])->assertOk();

        $other = $this->signedInDevice($this->makeRider(['name' => 'Bilal']));

        $this->asRider($other)->getJson("/rider/api/scan?code={$shipment->tracking_number}")
            ->assertOk()
            ->assertJsonPath('parcel.blocked', 'held_by_other')
            ->assertJsonPath('parcel.held_by', 'Ahmed Khan')
            ->assertJsonPath('parcel.allowed_actions', []);

        $this->update($other, $shipment, ['action' => 'delivered', 'photo' => $this->photo(), 'cod_amount' => 100, 'payment_method' => 'cash'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'held_by_other');

        // After the admin unassigns it, the other rider can take it out.
        $this->actingAs($this->staff())->postJson("/api/shipments/{$shipment->id}/unassign-rider")->assertOk();
        $this->update($other, $shipment, ['action' => 'out_for_delivery'])->assertOk();
    }

    public function test_delivered_needs_the_cod_amount(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $shipment = $this->ksaShipment();

        $this->update($token, $shipment, ['action' => 'delivered'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cod_amount', 'payment_method'])
            ->assertJsonMissingValidationErrors('photo');

        $this->assertSame(ShipmentStatus::INFO_RECEIVED->value, $shipment->fresh()->status);
    }

    public function test_delivered_without_a_photo(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $shipment = $this->ksaShipment();

        $this->update($token, $shipment, ['action' => 'delivered', 'cod_amount' => 100, 'payment_method' => 'cash'])->assertOk();

        $this->assertSame(ShipmentStatus::DELIVERED->value, $shipment->fresh()->status);
        $this->assertNull(ShipmentEvent::where('shipment_id', $shipment->id)->where('action', 'delivered')->value('photo_path'));
    }

    public function test_a_different_cod_amount_needs_a_note(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $shipment = $this->ksaShipment();

        $this->update($token, $shipment, ['action' => 'delivered', 'photo' => $this->photo(), 'cod_amount' => 80, 'payment_method' => 'cash'])
            ->assertStatus(422)->assertJsonValidationErrors('note');

        $this->update($token, $shipment, ['action' => 'delivered', 'photo' => $this->photo(), 'cod_amount' => 80, 'payment_method' => 'cash', 'note' => 'Customer paid 20 by transfer'])
            ->assertOk();

        $this->assertSame('80.00', (string) $shipment->order->fresh()->cod_collected_amount);
    }

    public function test_delivering_completes_the_order_records_cod_and_notifies_admins(): void
    {
        // markDelivered() notifies admin + superadmin; Spatie throws if
        // either role is missing.
        Role::findOrCreate('superadmin');
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin'));

        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);
        $shipment = $this->ksaShipment();

        $this->update($token, $shipment, [
            'action' => 'delivered',
            'photo' => $this->photo(),
            'cod_amount' => '100.00',
            'payment_method' => 'cash',
            'recipient_name' => 'Brother',
            'lat' => 24.7136,
            'lng' => 46.6753,
            'accuracy_m' => 12,
        ])->assertOk()->assertJsonPath('parcel.status', 'delivered')->assertJsonPath('parcel.blocked', 'finished');

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::DELIVERED->value, $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame('fulfilled', $shipment->order->fulfillment_status);
        $this->assertSame('100.00', (string) $shipment->order->cod_collected_amount);

        $event = ShipmentEvent::firstOrFail();
        $this->assertSame('cash', $event->payment_method);
        Storage::disk('local')->assertExists($event->photo_path);

        Notification::assertSentTo($admin, ShipmentDeliveredNotification::class);

        // The admin can see the photo; the public tracking page can't see
        // GPS, the photo or the rider's full name.
        $this->actingAs($this->staff())->get("/api/shipment-events/{$event->id}/photo")->assertOk();

        $public = $this->getJson("/api/track/{$shipment->tracking_number}")->assertOk()->getContent();
        $this->assertStringNotContainsString('24.7136', $public);
        $this->assertStringNotContainsString('pod/', $public);
        $this->assertStringNotContainsString($rider->phone, $public);
    }

    public function test_a_prepaid_parcel_is_delivered_without_collecting_cash(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $shipment = $this->ksaShipment([], 0.0, 'Prepaid');

        $this->update($token, $shipment, ['action' => 'delivered', 'photo' => $this->photo()])->assertOk();

        $this->assertNull($shipment->order->fresh()->cod_collected_amount);
    }

    public function test_a_failed_attempt_needs_a_reason_and_counts_as_an_attempt(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $shipment = $this->ksaShipment();
        $this->update($token, $shipment, ['action' => 'out_for_delivery'])->assertOk();

        $this->update($token, $shipment, ['action' => 'attempt_failed'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'other'])->assertStatus(422)->assertJsonValidationErrors('note');
        // The photo of the place shows the rider went.
        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'no_answer', 'photo' => null])
            ->assertStatus(422)->assertJsonValidationErrors('photo');

        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'no_answer'])
            ->assertOk()
            ->assertJsonPath('parcel.status', 'attempt_fail')
            ->assertJsonPath('parcel.attempts', 1)
            ->assertJsonPath('parcel.allowed_actions', ['delivered', 'attempt_failed', 'out_for_delivery', 'returned', 'cancelled']);

        $this->assertSame('Delivery attempt failed — Customer not answering', $shipment->fresh()->tracking_history[0]['description']);
    }

    public function test_a_retried_update_is_recorded_once(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $shipment = $this->ksaShipment();
        $uuid = (string) Str::uuid();

        $first = $this->update($token, $shipment, ['action' => 'out_for_delivery', 'client_uuid' => $uuid])->assertOk();
        $second = $this->update($token, $shipment, ['action' => 'out_for_delivery', 'client_uuid' => $uuid])->assertOk();

        $this->assertSame($first->json('event.id'), $second->json('event.id'));
        $this->assertSame(1, ShipmentEvent::count());
        $this->assertCount(1, $shipment->fresh()->tracking_history);
    }

    public function test_finished_and_other_courier_parcels_cannot_be_updated(): void
    {
        $token = $this->signedInDevice($this->makeRider());

        $cancelled = $this->ksaShipment(['status' => ShipmentStatus::CANCELLED->value]);
        $this->asRider($token)->getJson("/rider/api/scan?code={$cancelled->tracking_number}")
            ->assertOk()->assertJsonPath('parcel.blocked', 'finished');
        $this->update($token, $cancelled, ['action' => 'out_for_delivery'])->assertStatus(409)->assertJsonPath('code', 'finished');

        $jnt = $this->ksaShipment(['courier' => 'jnt_express', 'tracking_number' => 'JT0001']);
        $this->asRider($token)->getJson('/rider/api/scan?code=JT0001')
            ->assertOk()->assertJsonPath('parcel.blocked', 'not_ksa_express');
    }

    public function test_the_rider_app_requires_sign_in(): void
    {
        $shipment = $this->ksaShipment();

        $this->getJson('/rider/api/scan?code=' . $shipment->tracking_number)->assertStatus(401)->assertJsonPath('code', 'signed_out');
        $this->withHeader('Origin', $this->appOrigin())
            ->postJson("/rider/api/shipments/{$shipment->id}/events", ['action' => 'out_for_delivery', 'client_uuid' => (string) Str::uuid()])
            ->assertStatus(401);
    }

    public function test_today_numbers_on_the_home_screen(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $a = $this->ksaShipment();
        $b = $this->ksaShipment();

        $this->update($token, $a, ['action' => 'delivered', 'photo' => $this->photo(), 'cod_amount' => 100, 'payment_method' => 'cash'])->assertOk();
        $this->update($token, $b, ['action' => 'out_for_delivery'])->assertOk();

        $this->asRider($token)->getJson('/rider/api/me')
            ->assertOk()
            ->assertJsonPath('today.held', 1)
            ->assertJsonPath('today.delivered', 1)
            ->assertJsonPath('today.cod_collected', 100);
    }

    public function test_done_today_lists_only_this_riders_updates_from_today(): void
    {
        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);
        $a = $this->ksaShipment();
        $b = $this->ksaShipment();

        // Yesterday's update doesn't count.
        $this->travel(-1)->days();
        $this->update($token, $a, ['action' => 'out_for_delivery'])->assertOk();
        $this->travelBack();

        $this->update($token, $a, ['action' => 'delivered', 'photo' => $this->photo(), 'cod_amount' => 100, 'payment_method' => 'cash'])->assertOk();
        $this->travel(1)->minutes();
        $this->update($token, $b, ['action' => 'out_for_delivery'])->assertOk();

        // Someone else's update doesn't either.
        $this->update($this->signedInDevice($this->makeRider()), $this->ksaShipment(), ['action' => 'out_for_delivery'])->assertOk();

        $this->asRider($token)->getJson('/rider/api/history')
            ->assertOk()
            ->assertJsonCount(2, 'events')
            ->assertJsonPath('events.0.action', 'out_for_delivery')
            ->assertJsonPath('events.0.tracking_number', $b->tracking_number)
            ->assertJsonPath('events.1.action', 'delivered')
            ->assertJsonPath('events.1.cod_amount', 100)
            ->assertJsonPath('events.1.receiver_name', 'Confirmed Receiver');

        // The Delivered tab: only deliveries, with the cash total.
        $this->asRider($token)->getJson('/rider/api/history?action=delivered')
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.tracking_number', $a->tracking_number)
            ->assertJsonPath('summary.count', 1)
            ->assertJsonPath('summary.cod_collected', 100);

        $this->asRider($token)->getJson('/rider/api/history?range=yesterday')
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.action', 'out_for_delivery')
            ->assertJsonPath('events.0.tracking_number', $a->tracking_number);

        $this->asRider($token)->getJson('/rider/api/history?range=week')->assertOk()->assertJsonCount(3, 'events');
        $this->asRider($token)->getJson('/rider/api/history?range=forever')->assertStatus(422);
    }

    public function test_tracking_sync_leaves_ksa_express_alone(): void
    {
        $this->ksaShipment(['status' => ShipmentStatus::OUT_FOR_DELIVERY->value]);

        $this->artisan('shipments:sync-tracking')
            ->expectsOutput('No active shipments to sync.')
            ->assertSuccessful();
    }
}
