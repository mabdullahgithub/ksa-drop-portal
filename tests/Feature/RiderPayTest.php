<?php

namespace Tests\Feature;

use App\Models\Rider;
use App\Models\RiderEarning;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * What KSA Drop pays a rider per visit: the delivery rate for a delivery,
 * the attempt rate each time they went and the customer didn't take it
 * (shown by a photo of the place), nothing for an order cancelled before
 * they went.
 */
class RiderPayTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
        Storage::fake('local');
        Notification::fake();
    }

    /** A rider paid 10 per delivery and 4 per attempt, unless told otherwise. */
    private function paidRider(array $attributes = []): Rider
    {
        return $this->makeRider($attributes + ['delivery_rate' => 10, 'attempt_rate' => 4]);
    }

    private function update(string $token, Shipment $shipment, array $data)
    {
        return $this->asRider($token)->post("/rider/api/shipments/{$shipment->id}/events", $this->withProof($data) + [
            'client_uuid' => (string) Str::uuid(),
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

    /** Scan it out, as a rider does before anything else. */
    private function out(string $token, ?Shipment $shipment = null): Shipment
    {
        $shipment ??= $this->ksaShipment();
        $this->update($token, $shipment, ['action' => 'out_for_delivery'])->assertOk();

        return $shipment;
    }

    private function deliver(string $token, Shipment $shipment)
    {
        return $this->update($token, $shipment, ['action' => 'delivered', 'cod_amount' => 100, 'payment_method' => 'cash'])->assertOk();
    }

    private function pay(string $token)
    {
        return $this->asRider($token)->getJson('/rider/api/me')->assertOk();
    }

    public function test_a_delivered_order_earns_the_delivery_rate(): void
    {
        $token = $this->signedInDevice($this->paidRider());

        $this->deliver($token, $this->out($token));
        $this->deliver($token, $this->out($token));

        $this->pay($token)
            ->assertJsonPath('pay.earned', 20)
            ->assertJsonPath('pay.delivered', 2)
            ->assertJsonPath('pay.attempted', 0)
            ->assertJsonPath('pay.paid', 0)
            ->assertJsonPath('pay.balance', 20)
            ->assertJsonPath('pay.rates.delivery', 10)
            ->assertJsonPath('pay.rates.attempt', 4);
    }

    public function test_every_visit_the_customer_did_not_take_the_order_on_earns_the_attempt_rate(): void
    {
        $token = $this->signedInDevice($this->paidRider());
        $shipment = $this->out($token);

        // The same order, failed twice.
        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'no_answer'])->assertOk();
        $this->update($token, $shipment, ['action' => 'out_for_delivery'])->assertOk();
        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'closed'])->assertOk();

        // Returning it afterwards is a separate step and earns nothing more.
        $this->update($token, $shipment, ['action' => 'returned', 'reason' => 'refused'])->assertOk();
        $this->update($token, $this->out($token), ['action' => 'returned', 'reason' => 'refused'])->assertOk();

        $this->pay($token)
            ->assertJsonPath('pay.earned', 8)
            ->assertJsonPath('pay.attempted', 2)
            ->assertJsonPath('pay.delivered', 0);

        $this->assertSame(2, RiderEarning::count());
    }

    public function test_an_attempted_order_delivered_on_a_later_visit_earns_the_delivery_rate_as_well(): void
    {
        $token = $this->signedInDevice($this->paidRider());
        $shipment = $this->out($token);

        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'no_answer'])->assertOk();
        $this->pay($token)->assertJsonPath('pay.earned', 4)->assertJsonPath('pay.attempted', 1);

        $this->deliver($token, $shipment);

        // The attempt at the attempt rate, the delivery at the delivery rate.
        $this->pay($token)
            ->assertJsonPath('pay.earned', 14)
            ->assertJsonPath('pay.delivered', 1)
            ->assertJsonPath('pay.attempted', 1);
    }

    public function test_an_attempt_needs_a_photo_of_the_place_and_takes_the_location_when_there_is_one(): void
    {
        $token = $this->signedInDevice($this->paidRider());
        $shipment = $this->out($token);

        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'no_answer', 'photo' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo' => 'Take a photo of the place.']);

        $this->pay($token)->assertJsonPath('pay.earned', 0);

        // No location (GPS off, permission refused) doesn't hold the rider up.
        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'no_answer'])->assertOk();

        $other = $this->out($token);
        $this->update($token, $other, ['action' => 'attempt_failed', 'reason' => 'closed', 'lat' => 24.7136, 'lng' => 46.6753, 'accuracy_m' => 9])->assertOk();

        $event = ShipmentEvent::where('shipment_id', $other->id)->where('action', 'attempt_failed')->firstOrFail();
        Storage::disk('local')->assertExists($event->photo_path);
        $this->assertEqualsWithDelta(24.7136, (float) $event->lat, 0.0001);

        $this->pay($token)->assertJsonPath('pay.earned', 8)->assertJsonPath('pay.attempted', 2);
    }

    public function test_an_attempt_the_customer_put_off_needs_the_day_they_asked_for(): void
    {
        $token = $this->signedInDevice($this->paidRider());
        $shipment = $this->out($token);
        $today = now('Asia/Riyadh');

        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'reschedule'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reschedule_date' => 'Choose the day the customer asked for.']);
        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'reschedule', 'reschedule_date' => $today->copy()->subDay()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('reschedule_date');
        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'reschedule', 'reschedule_date' => $today->copy()->addYear()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('reschedule_date');

        $day = $today->copy()->addDays(3)->toDateString();
        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'reschedule', 'reschedule_date' => $day])
            ->assertOk()
            ->assertJsonPath('parcel.last_event.reschedule_date', $day);

        $this->assertStringContainsString($today->copy()->addDays(3)->format('j M Y'), $shipment->fresh()->courier_status_description);
        $this->pay($token)->assertJsonPath('pay.attempted', 1);

        // Any other reason has no day, even when one is sent.
        $other = $this->out($token);
        $this->update($token, $other, ['action' => 'attempt_failed', 'reason' => 'no_answer', 'reschedule_date' => $day])->assertOk();
        $this->assertNull(ShipmentEvent::where('shipment_id', $other->id)->where('action', 'attempt_failed')->firstOrFail()->reschedule_date);
    }

    public function test_an_order_cancelled_before_the_rider_went_is_not_paid(): void
    {
        $token = $this->signedInDevice($this->paidRider());

        // The customer cancelled on the phone.
        $this->update($token, $this->out($token), ['action' => 'cancelled', 'reason' => 'refused'])->assertOk();

        // An admin cancelled it while it was out.
        $byAdmin = $this->out($token);
        $this->actingAs($this->staff())->postJson("/api/shipments/{$byAdmin->id}/cancel", ['reason' => 'Customer called'])->assertOk();

        // Only scanned out: nothing yet.
        $this->out($token);

        $this->pay($token)->assertJsonPath('pay.earned', 0)->assertJsonPath('pay.attempted', 0);
        $this->assertSame(0, RiderEarning::count());
    }

    public function test_an_attempt_already_made_is_still_paid_when_the_order_is_cancelled_afterwards(): void
    {
        $token = $this->signedInDevice($this->paidRider());
        $shipment = $this->out($token);

        $this->update($token, $shipment, ['action' => 'attempt_failed', 'reason' => 'no_answer'])->assertOk();
        $this->update($token, $shipment, ['action' => 'cancelled', 'reason' => 'refused'])->assertOk();

        $this->pay($token)->assertJsonPath('pay.earned', 4)->assertJsonPath('pay.attempted', 1);
    }

    public function test_each_rider_is_paid_for_what_they_did_on_a_parcel_that_changed_hands(): void
    {
        $ahmed = $this->signedInDevice($this->paidRider());
        $bilal = $this->signedInDevice($this->paidRider());
        $shipment = $this->out($ahmed);

        $this->update($ahmed, $shipment, ['action' => 'attempt_failed', 'reason' => 'no_answer'])->assertOk();
        $this->actingAs($this->staff())->postJson("/api/shipments/{$shipment->id}/unassign-rider")->assertOk();
        $this->deliver($bilal, $this->out($bilal, $shipment));

        $this->pay($ahmed)->assertJsonPath('pay.earned', 4);
        $this->pay($bilal)->assertJsonPath('pay.earned', 10);
    }

    public function test_each_rider_is_paid_at_their_own_rates(): void
    {
        $ahmed = $this->signedInDevice($this->paidRider());
        $bilal = $this->signedInDevice($this->paidRider(['delivery_rate' => 15, 'attempt_rate' => 6.5]));

        foreach ([$ahmed, $bilal] as $token) {
            $this->deliver($token, $this->out($token));
            $this->update($token, $this->out($token), ['action' => 'attempt_failed', 'reason' => 'closed'])->assertOk();
        }

        $this->pay($ahmed)
            ->assertJsonPath('pay.earned', 14)
            ->assertJsonPath('pay.rates.delivery', 10)
            ->assertJsonPath('pay.rates.attempt', 4);
        $this->pay($bilal)
            ->assertJsonPath('pay.earned', 21.5)
            ->assertJsonPath('pay.rates.delivery', 15)
            ->assertJsonPath('pay.rates.attempt', 6.5);
    }

    public function test_a_rider_earns_nothing_until_the_admin_sets_their_rates(): void
    {
        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);
        $admin = $this->staff();

        $this->deliver($token, $this->out($token));
        $this->pay($token)->assertJsonPath('pay.earned', 0)->assertJsonPath('pay.rates.delivery', 0);

        $this->actingAs($admin)->putJson("/api/riders/{$rider->id}", ['name' => $rider->name, 'phone' => $rider->phone, 'delivery_rate' => 12.5, 'attempt_rate' => 5])
            ->assertOk()
            ->assertJsonPath('rider.delivery_rate', 12.5)
            ->assertJsonPath('rider.attempt_rate', 5);

        $this->deliver($token, $this->out($token));
        $this->update($token, $this->out($token), ['action' => 'attempt_failed', 'reason' => 'closed'])->assertOk();

        $this->pay($token)->assertJsonPath('pay.earned', 17.5)->assertJsonPath('pay.delivered', 2)->assertJsonPath('pay.attempted', 1);
    }

    public function test_changing_a_riders_rates_does_not_change_what_they_already_earned(): void
    {
        $rider = $this->paidRider();
        $token = $this->signedInDevice($rider);
        $this->deliver($token, $this->out($token));

        $this->actingAs($this->staff())->putJson("/api/riders/{$rider->id}", ['name' => $rider->name, 'phone' => $rider->phone, 'delivery_rate' => 20, 'attempt_rate' => 4])
            ->assertOk();

        $this->deliver($token, $this->out($token));

        // 10 at the old rate, 20 at the new one.
        $this->pay($token)->assertJsonPath('pay.earned', 30)->assertJsonPath('pay.rates.delivery', 20);
    }

    public function test_setting_a_riders_rates_needs_real_amounts_and_permission(): void
    {
        $rider = $this->paidRider();
        $body = ['name' => $rider->name, 'phone' => $rider->phone];

        $this->actingAs($this->staff())->putJson("/api/riders/{$rider->id}", $body + ['delivery_rate' => -1, 'attempt_rate' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['delivery_rate', 'attempt_rate']);

        $this->actingAs($this->staff(['view riders']))->putJson("/api/riders/{$rider->id}", $body + ['delivery_rate' => 99])->assertForbidden();

        $this->assertSame('10.00', (string) $rider->fresh()->delivery_rate);
    }

    public function test_paying_the_rider_brings_down_what_ksa_drop_owes_them_and_leaves_their_cash_alone(): void
    {
        $rider = $this->paidRider(['name' => 'Ahmed Khan']);
        $token = $this->signedInDevice($rider);
        $this->deliver($token, $this->out($token));
        $this->deliver($token, $this->out($token));
        $admin = $this->staff();

        $payout = fn (array $data) => $this->actingAs($admin)->postJson("/api/riders/{$rider->id}/payments", $data + [
            'direction' => 'out',
            'method' => 'bank_transfer',
            'client_uuid' => (string) Str::uuid(),
        ]);

        $id = $payout(['amount' => 15, 'reference' => 'PAY-1'])
            ->assertCreated()
            ->assertJsonPath('payment.direction', 'out')
            ->assertJsonPath('pay.earned', 20)
            ->assertJsonPath('pay.paid', 15)
            ->assertJsonPath('pay.balance', 5)
            // The cash the rider owes is a separate account.
            ->assertJsonPath('cash.collected', 200)
            ->assertJsonPath('cash.paid', 0)
            ->assertJsonPath('cash.balance', 200)
            ->assertJsonPath('message', 'Payment of SAR 15.00 to Ahmed Khan recorded.')
            ->json('payment.id');

        // Cash handed in doesn't count as pay, and each list holds its own.
        $this->actingAs($admin)->postJson("/api/riders/{$rider->id}/payments", ['amount' => 200, 'method' => 'cash', 'client_uuid' => (string) Str::uuid()])
            ->assertCreated()
            ->assertJsonPath('payment.direction', 'in')
            ->assertJsonPath('cash.balance', 0)
            ->assertJsonPath('pay.balance', 5);

        $this->actingAs($admin)->getJson("/api/riders/{$rider->id}/payments?direction=out")
            ->assertOk()->assertJsonCount(1, 'payments')->assertJsonPath('payments.0.reference', 'PAY-1');
        $this->actingAs($admin)->getJson("/api/riders/{$rider->id}/payments")
            ->assertOk()->assertJsonCount(1, 'payments')->assertJsonPath('payments.0.direction', 'in');

        $this->actingAs($admin)->getJson('/api/riders')
            ->assertJsonPath('riders.0.pay.balance', 5)
            ->assertJsonPath('riders.0.cash.balance', 0);

        $this->asRider($token)->getJson('/rider/api/cash')
            ->assertOk()
            ->assertJsonPath('pay.balance', 5)
            ->assertJsonCount(1, 'payouts')
            ->assertJsonPath('payouts.0.amount', 15)
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.amount', 200);

        // A voided payout is owed again.
        $this->actingAs($admin)->postJson("/api/riders/{$rider->id}/payments/{$id}/void", ['reason' => 'Transfer bounced'])
            ->assertOk()
            ->assertJsonPath('pay.paid', 0)
            ->assertJsonPath('pay.balance', 20);
    }

    public function test_a_retried_update_does_not_pay_twice(): void
    {
        $token = $this->signedInDevice($this->paidRider());
        $shipment = $this->out($token);
        $uuid = (string) Str::uuid();

        foreach ([1, 2] as $_) {
            $this->update($token, $shipment, ['action' => 'delivered', 'cod_amount' => 100, 'payment_method' => 'cash', 'client_uuid' => $uuid])->assertOk();
        }

        $this->pay($token)->assertJsonPath('pay.earned', 10)->assertJsonPath('pay.delivered', 1);
    }
}
