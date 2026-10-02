<?php

namespace Tests\Feature;

use App\Models\Rider;
use App\Models\RiderPayment;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * What a rider owes KSA Drop: the COD they collected in cash, minus the
 * payments staff recorded as they handed it in.
 */
class RiderCashTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
        Storage::fake('local');
        Notification::fake();
    }

    private function deliver(string $token, float $cod, string $method = 'cash'): Shipment
    {
        $shipment = $this->ksaShipment([], $cod);

        $this->asRider($token)->postJson("/rider/api/shipments/{$shipment->id}/events", [
            'action' => 'delivered',
            'cod_amount' => $cod,
            'payment_method' => $method,
            'client_uuid' => (string) Str::uuid(),
        ])->assertOk();

        return $shipment;
    }

    private function pay(Rider $rider, array $data = [])
    {
        return $this->actingAs($this->staff())->postJson("/api/riders/{$rider->id}/payments", $data + [
            'amount' => 100,
            'method' => 'cash',
            'client_uuid' => (string) Str::uuid(),
        ]);
    }

    public function test_a_rider_owes_the_cash_they_collected_but_not_card_or_transfer(): void
    {
        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);

        $this->deliver($token, 150);
        $this->deliver($token, 250.5);
        $this->deliver($token, 80, 'card');
        $this->deliver($token, 20, 'transfer');

        $this->asRider($token)->getJson('/rider/api/me')
            ->assertOk()
            ->assertJsonPath('cash.collected', 400.5)
            ->assertJsonPath('cash.direct', 100)
            ->assertJsonPath('cash.paid', 0)
            ->assertJsonPath('cash.balance', 400.5)
            ->assertJsonPath('today.cod_collected', 500.5)
            ->assertJsonPath('today.cash_collected', 400.5);

        $this->actingAs($this->staff(['view riders']))->getJson('/api/riders')
            ->assertOk()
            ->assertJsonPath('riders.0.cash.balance', 400.5)
            ->assertJsonPath('riders.0.stats.cash_collected', 400.5);
    }

    public function test_recording_a_payment_brings_down_what_the_rider_owes(): void
    {
        $rider = $this->makeRider(['name' => 'Ahmed Khan']);
        $token = $this->signedInDevice($rider);
        $this->deliver($token, 500);

        $this->pay($rider, ['amount' => '300.00', 'method' => 'cash', 'note' => 'End of shift'])
            ->assertCreated()
            ->assertJsonPath('payment.amount', 300)
            ->assertJsonPath('payment.method', 'cash')
            ->assertJsonPath('payment.note', 'End of shift')
            ->assertJsonPath('cash.paid', 300)
            ->assertJsonPath('cash.balance', 200)
            ->assertJsonPath('message', 'Payment of SAR 300.00 recorded for Ahmed Khan.');

        $this->pay($rider, ['amount' => 150, 'method' => 'bank_transfer', 'reference' => 'TRX-991'])->assertCreated();

        $this->actingAs($this->staff(['view riders']))->getJson("/api/riders/{$rider->id}/payments")
            ->assertOk()
            ->assertJsonCount(2, 'payments')
            ->assertJsonPath('payments.0.reference', 'TRX-991')
            ->assertJsonPath('cash.collected', 500)
            ->assertJsonPath('cash.paid', 450)
            ->assertJsonPath('cash.balance', 50)
            ->assertJsonPath('next_page', null);

        // The rider sees the same numbers and their payments, newest first.
        $this->asRider($token)->getJson('/rider/api/cash')
            ->assertOk()
            ->assertJsonPath('cash.balance', 50)
            ->assertJsonCount(2, 'payments')
            ->assertJsonPath('payments.0.amount', 150)
            ->assertJsonPath('payments.0.method', 'bank_transfer')
            ->assertJsonPath('payments.1.amount', 300)
            ->assertJsonMissingPath('payments.0.note');
    }

    public function test_a_rider_who_hands_in_more_than_they_collected_is_in_credit(): void
    {
        $rider = $this->makeRider();
        $this->deliver($this->signedInDevice($rider), 100);

        $this->pay($rider, ['amount' => 120])->assertCreated()->assertJsonPath('cash.balance', -20);
    }

    public function test_a_voided_payment_no_longer_counts_and_the_rider_does_not_see_it(): void
    {
        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);
        $this->deliver($token, 500);

        $id = $this->pay($rider, ['amount' => 500])->assertCreated()->json('payment.id');
        $admin = $this->staff();

        $this->actingAs($admin)->postJson("/api/riders/{$rider->id}/payments/{$id}/void", [])->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->actingAs($admin)->postJson("/api/riders/{$rider->id}/payments/{$id}/void", ['reason' => 'Entered for the wrong rider'])
            ->assertOk()
            ->assertJsonPath('payment.void_reason', 'Entered for the wrong rider')
            ->assertJsonPath('payment.voided_by', $admin->name)
            ->assertJsonPath('cash.paid', 0)
            ->assertJsonPath('cash.balance', 500);

        $this->actingAs($admin)->postJson("/api/riders/{$rider->id}/payments/{$id}/void", ['reason' => 'Again'])->assertStatus(422);

        // Kept on the portal as a record; gone from the rider's app.
        $this->actingAs($admin)->getJson("/api/riders/{$rider->id}/payments")->assertJsonCount(1, 'payments');
        $this->asRider($token)->getJson('/rider/api/cash')->assertJsonCount(0, 'payments')->assertJsonPath('cash.balance', 500);
    }

    public function test_a_retried_payment_is_recorded_once(): void
    {
        $rider = $this->makeRider();
        $uuid = (string) Str::uuid();

        $first = $this->pay($rider, ['client_uuid' => $uuid])->assertCreated();
        $second = $this->pay($rider, ['client_uuid' => $uuid])->assertCreated();

        $this->assertSame($first->json('payment.id'), $second->json('payment.id'));
        $this->assertSame(1, RiderPayment::count());
        $second->assertJsonPath('cash.paid', 100);
    }

    public function test_a_payment_needs_a_real_amount_a_known_method_and_a_date_that_has_happened(): void
    {
        $rider = $this->makeRider();

        $this->pay($rider, ['amount' => 0])->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->pay($rider, ['amount' => -5])->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->pay($rider, ['method' => 'cheque'])->assertStatus(422)->assertJsonValidationErrors('method');
        $this->pay($rider, ['received_at' => now()->addDay()->toIso8601String()])->assertStatus(422)->assertJsonValidationErrors('received_at');
        $this->pay($rider, ['client_uuid' => null])->assertStatus(422)->assertJsonValidationErrors('client_uuid');

        $this->pay($rider, ['received_at' => now()->subDay()->toIso8601String()])->assertCreated();
        $this->assertSame(1, RiderPayment::count());
    }

    public function test_recording_and_voiding_payments_needs_its_own_permission(): void
    {
        $rider = $this->makeRider();
        $id = $this->pay($rider)->assertCreated()->json('payment.id');

        // Editing riders isn't enough to take money.
        $manager = $this->staff(['view riders', 'manage riders']);
        $body = ['amount' => 50, 'method' => 'cash', 'client_uuid' => (string) Str::uuid()];

        $this->actingAs($manager)->postJson("/api/riders/{$rider->id}/payments", $body)->assertForbidden();
        $this->actingAs($manager)->postJson("/api/riders/{$rider->id}/payments/{$id}/void", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($manager)->getJson("/api/riders/{$rider->id}/payments")->assertOk();

        $this->actingAs($this->staff(['view orders']))->getJson("/api/riders/{$rider->id}/payments")->assertForbidden();

        $this->assertSame(1, RiderPayment::counted()->count());
    }

    public function test_a_payment_can_only_be_voided_under_its_own_rider(): void
    {
        $rider = $this->makeRider();
        $other = $this->makeRider();
        $id = $this->pay($rider)->assertCreated()->json('payment.id');

        $this->actingAs($this->staff())->postJson("/api/riders/{$other->id}/payments/{$id}/void", ['reason' => 'x'])->assertNotFound();

        $this->assertNull(RiderPayment::findOrFail($id)->voided_at);
    }

    public function test_one_riders_cash_is_never_shown_to_another(): void
    {
        $ahmed = $this->makeRider();
        $bilal = $this->makeRider();
        $this->deliver($this->signedInDevice($ahmed), 300);
        $this->pay($ahmed, ['amount' => 100])->assertCreated();

        $this->asRider($this->signedInDevice($bilal))->getJson('/rider/api/cash')
            ->assertOk()
            ->assertJsonPath('cash.collected', 0)
            ->assertJsonPath('cash.balance', 0)
            ->assertJsonCount(0, 'payments');
    }

    public function test_the_cash_screen_requires_sign_in(): void
    {
        $this->getJson('/rider/api/cash')->assertStatus(401)->assertJsonPath('code', 'signed_out');
    }
}
