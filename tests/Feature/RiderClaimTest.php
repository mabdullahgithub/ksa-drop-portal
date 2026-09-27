<?php

namespace Tests\Feature;

use App\Models\ShipmentEvent;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * Scanning a parcel picks it up: first scan assigns it to that rider and
 * sends it out for delivery. Batch pick-up is the same call, one per scan.
 */
class RiderClaimTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
    }

    private function claim(string $token, string $code, ?string $uuid = null)
    {
        return $this->asRider($token)->postJson('/rider/api/claim', [
            'code' => $code,
            'client_uuid' => $uuid ?? (string) Str::uuid(),
            'entry_method' => 'camera',
        ]);
    }

    public function test_first_scan_assigns_the_parcel_and_sends_it_out(): void
    {
        $rider = $this->makeRider(['name' => 'Ahmed Khan']);
        $token = $this->signedInDevice($rider);
        $shipment = $this->ksaShipment();

        $this->claim($token, $shipment->tracking_number)
            ->assertOk()
            ->assertJsonPath('result', 'claimed')
            ->assertJsonPath('parcel.status', 'out_for_delivery')
            ->assertJsonPath('parcel.held_by_me', true)
            ->assertJsonPath('parcel.allowed_actions', ['delivered', 'attempt_failed']);

        $shipment->refresh();
        $this->assertSame($rider->id, $shipment->rider_id);
        $this->assertSame(ShipmentStatus::OUT_FOR_DELIVERY->value, $shipment->status);
        $this->assertSame('Out for delivery with Ahmed', $shipment->tracking_history[0]['description']);
        $this->assertSame('camera', ShipmentEvent::firstOrFail()->entry_method);
    }

    public function test_scanning_a_parcel_you_already_hold_changes_nothing(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $shipment = $this->ksaShipment();

        $this->claim($token, $shipment->tracking_number)->assertJsonPath('result', 'claimed');
        $this->claim($token, $shipment->tracking_number)->assertOk()->assertJsonPath('result', 'already_mine');

        $this->assertSame(1, ShipmentEvent::count());
    }

    public function test_the_second_rider_to_scan_gets_nothing(): void
    {
        $shipment = $this->ksaShipment();
        $this->claim($this->signedInDevice($this->makeRider(['name' => 'Ahmed Khan'])), $shipment->tracking_number)->assertJsonPath('result', 'claimed');

        $this->claim($this->signedInDevice($this->makeRider(['name' => 'Bilal'])), $shipment->tracking_number)
            ->assertOk()
            ->assertJsonPath('result', 'held_by_other')
            ->assertJsonPath('parcel.held_by', 'Ahmed Khan');

        $this->assertSame(1, ShipmentEvent::count());
    }

    public function test_finished_and_other_courier_parcels_are_not_picked_up(): void
    {
        $token = $this->signedInDevice($this->makeRider());

        $cancelled = $this->ksaShipment(['status' => ShipmentStatus::CANCELLED->value]);
        $this->claim($token, $cancelled->tracking_number)->assertOk()->assertJsonPath('result', 'finished');

        $jnt = $this->ksaShipment(['courier' => 'jnt_express', 'tracking_number' => 'JT0001']);
        $this->claim($token, 'JT0001')->assertOk()->assertJsonPath('result', 'not_ksa_express');

        $this->claim($token, 'NOPE-1')->assertNotFound()->assertJsonPath('result', 'not_found');

        $this->assertSame(0, ShipmentEvent::count());
        $this->assertNull($cancelled->fresh()->rider_id);
        $this->assertNull($jnt->fresh()->rider_id);
    }

    public function test_a_parcel_the_admin_unassigned_goes_to_whoever_scans_it_next(): void
    {
        $shipment = $this->ksaShipment(['status' => ShipmentStatus::ATTEMPT_FAIL->value, 'rider_id' => null]);
        $rider = $this->makeRider();

        $this->claim($this->signedInDevice($rider), $shipment->tracking_number)->assertJsonPath('result', 'claimed');

        $this->assertSame($rider->id, $shipment->fresh()->rider_id);
        $this->assertSame(ShipmentStatus::OUT_FOR_DELIVERY->value, $shipment->fresh()->status);
    }

    public function test_a_retried_scan_is_a_claim_once(): void
    {
        $token = $this->signedInDevice($this->makeRider());
        $shipment = $this->ksaShipment();
        $uuid = (string) Str::uuid();

        $this->claim($token, $shipment->tracking_number, $uuid)->assertJsonPath('result', 'claimed');
        // The response was lost on a weak signal; the app sends it again.
        $this->claim($token, $shipment->tracking_number, $uuid)->assertJsonPath('result', 'claimed');

        $this->assertSame(1, ShipmentEvent::count());
    }

    public function test_batch_pick_up_assigns_every_scanned_parcel(): void
    {
        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);
        $parcels = [$this->ksaShipment(), $this->ksaShipment(), $this->ksaShipment()];

        foreach ($parcels as $parcel) {
            $this->claim($token, $parcel->tracking_number)->assertJsonPath('result', 'claimed');
        }

        // The label's QR code and the order number work too.
        $qr = $this->ksaShipment();
        $this->claim($token, "{$this->appOrigin()}/track?q={$qr->tracking_number}")->assertJsonPath('result', 'claimed');

        $this->asRider($token)->getJson('/rider/api/parcels')->assertOk()->assertJsonCount(4, 'parcels');
        $this->asRider($token)->getJson('/rider/api/me')->assertJsonPath('today.held', 4);
    }
}
