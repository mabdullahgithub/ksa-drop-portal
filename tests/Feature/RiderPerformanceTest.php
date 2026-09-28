<?php

namespace Tests\Feature;

use App\Models\Rider;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Services\Shipping\Enums\RiderAction;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

class RiderPerformanceTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
    }

    public function test_riders_are_ranked_by_deliveries_with_a_series_per_ksa_day(): void
    {
        $ahmed = $this->makeRider(['name' => 'Ahmed Khan']);
        $bilal = $this->makeRider(['name' => 'Bilal Ahmed']);
        $this->makeRider(['name' => 'Idle Rider']);

        // 23:30 UTC on the 1st is already the 2nd in Riyadh.
        $this->event($ahmed, RiderAction::DELIVERED, '2026-09-01 23:30:00', 150);
        $this->event($ahmed, RiderAction::DELIVERED, '2026-09-02 08:00:00', 50.5);
        $this->event($ahmed, RiderAction::ATTEMPT_FAILED, '2026-09-03 10:00:00');
        $this->event($bilal, RiderAction::DELIVERED, '2026-09-01 10:00:00', 80);
        $this->event($bilal, RiderAction::OUT_FOR_DELIVERY, '2026-09-01 09:00:00');

        // Outside the range, in KSA time: the 31st, and the 4th (21:30 UTC on the 3rd).
        $this->event($bilal, RiderAction::DELIVERED, '2026-08-31 20:00:00', 999);
        $this->event($bilal, RiderAction::DELIVERED, '2026-09-03 21:30:00', 999);

        $staff = $this->staff(['view riders']);

        $response = $this->actingAs($staff)
            ->getJson('/api/riders/performance?from=2026-09-01&to=2026-09-03')
            ->assertOk()
            ->assertJsonPath('days', ['2026-09-01', '2026-09-02', '2026-09-03'])
            ->assertJsonCount(2, 'riders');

        $response->assertJsonPath('riders.0.id', $ahmed->id)
            ->assertJsonPath('riders.0.assigned', 3)
            ->assertJsonPath('riders.0.delivered', 2)
            ->assertJsonPath('riders.0.failed', 1)
            ->assertJsonPath('riders.0.cod_collected', 200.5);

        // Scanning a parcel out counts as assigned, even before any delivery.
        $response->assertJsonPath('riders.1.id', $bilal->id)
            ->assertJsonPath('riders.1.assigned', 2)
            ->assertJsonPath('riders.1.delivered', 1)
            ->assertJsonPath('riders.1.cod_collected', 80);

        // The whole team per day, for the trend's comparison line.
        $response->assertJsonPath('team_daily.delivered', [1, 2, 0])
            ->assertJsonPath('team_daily.failed', [0, 0, 1])
            ->assertJsonPath('team_daily.cod_collected', [80, 200.5, 0])
            ->assertJsonPath('team_daily.riders', [1, 1, 1]);

        // One rider's own days come with their summary.
        $this->actingAs($staff)
            ->getJson("/api/riders/{$ahmed->id}/performance?from=2026-09-01&to=2026-09-03")
            ->assertOk()
            ->assertJsonPath('daily.delivered', [0, 2, 0])
            ->assertJsonPath('daily.failed', [0, 0, 1])
            ->assertJsonPath('daily.cod_collected', [0, 200.5, 0]);
    }

    public function test_a_riders_breakdown_says_where_each_parcel_ended_up_and_why_attempts_failed(): void
    {
        $ahmed = $this->makeRider(['name' => 'Ahmed Khan']);
        $bilal = $this->makeRider(['name' => 'Bilal Ahmed']);

        $delivered = $this->ksaShipment(['rider_id' => $ahmed->id, 'status' => ShipmentStatus::DELIVERED->value]);
        $this->event($ahmed, RiderAction::OUT_FOR_DELIVERY, '2026-09-01 08:00:00', shipment: $delivered);
        $this->event($ahmed, RiderAction::DELIVERED, '2026-09-01 12:00:00', 150, shipment: $delivered);

        $retry = $this->ksaShipment(['rider_id' => $ahmed->id, 'status' => ShipmentStatus::ATTEMPT_FAIL->value]);
        $this->event($ahmed, RiderAction::ATTEMPT_FAILED, '2026-09-01 13:00:00', shipment: $retry, reason: 'refused');
        $this->event($ahmed, RiderAction::ATTEMPT_FAILED, '2026-09-02 13:00:00', shipment: $retry, reason: 'no_answer');

        $cancelled = $this->ksaShipment(['rider_id' => $ahmed->id]);
        $this->event($ahmed, RiderAction::ATTEMPT_FAILED, '2026-09-01 14:00:00', shipment: $cancelled, reason: 'no_answer');
        $cancelled->markCancelled('Customer cancelled the order');

        $unassigned = $this->ksaShipment(['status' => ShipmentStatus::OUT_FOR_DELIVERY->value]);
        $this->event($ahmed, RiderAction::OUT_FOR_DELIVERY, '2026-09-01 08:00:00', shipment: $unassigned);

        $takenOver = $this->ksaShipment(['rider_id' => $bilal->id, 'status' => ShipmentStatus::DELIVERED->value]);
        $this->event($ahmed, RiderAction::OUT_FOR_DELIVERY, '2026-09-01 08:00:00', shipment: $takenOver);

        $outForDelivery = $this->ksaShipment(['rider_id' => $ahmed->id, 'status' => ShipmentStatus::OUT_FOR_DELIVERY->value]);
        $this->event($ahmed, RiderAction::OUT_FOR_DELIVERY, '2026-09-02 08:00:00', shipment: $outForDelivery);

        // Outside the range.
        $this->event($ahmed, RiderAction::DELIVERED, '2026-09-05 08:00:00', 99);

        $staff = $this->staff(['view riders']);

        $this->actingAs($staff)
            ->getJson("/api/riders/{$ahmed->id}/performance?from=2026-09-01&to=2026-09-02")
            ->assertOk()
            ->assertJsonPath('assigned', 6)
            ->assertJsonPath('outcomes', [
                'delivered' => 1,
                'out_for_delivery' => 1,
                'attempt_fail' => 1,
                'cancelled' => 1,
                'returned' => 0,
                'handed_back' => 2,
                'other' => 0,
            ])
            ->assertJsonPath('failed_reasons', [
                ['reason' => 'no_answer', 'label' => 'Customer not answering', 'count' => 2],
                ['reason' => 'refused', 'label' => 'Customer refused the parcel', 'count' => 1],
            ]);

        $response = $this->actingAs($staff)
            ->getJson("/api/riders/{$ahmed->id}/parcels?from=2026-09-01&to=2026-09-02")
            ->assertOk()
            ->assertJsonCount(6, 'parcels')
            ->assertJsonPath('next_page', null);

        $parcels = collect($response->json('parcels'))->keyBy('id');

        $this->assertSame('delivered', $parcels[$delivered->id]['outcome']);
        $this->assertSame(['out_for_delivery', 'delivered'], array_column($parcels[$delivered->id]['events'], 'action'));
        $this->assertEquals(150, $parcels[$delivered->id]['events'][1]['cod_amount']);
        $this->assertSame('Riyadh', $parcels[$delivered->id]['city']);
        $this->assertEquals(100, $parcels[$delivered->id]['cod_amount']);
        $this->assertSame('cancelled', $parcels[$cancelled->id]['outcome']);
        $this->assertSame('Customer cancelled the order', $parcels[$cancelled->id]['cancel_reason']);
        $this->assertSame('handed_back', $parcels[$takenOver->id]['outcome']);
        $this->assertSame('Bilal Ahmed', $parcels[$takenOver->id]['held_by']);
        $this->assertNull($parcels[$unassigned->id]['held_by']);
        $this->assertSame(
            ['attempt_failed', 'attempt_failed'],
            array_column($parcels[$retry->id]['events'], 'action')
        );
        $this->assertSame('Customer not answering', $parcels[$retry->id]['events'][1]['reason']);

        // Newest update first.
        $this->assertSame($retry->id, $response->json('parcels.0.id'));

        // The list filters by outcome and searches in SQL.
        $this->actingAs($staff)
            ->getJson("/api/riders/{$ahmed->id}/parcels?from=2026-09-01&to=2026-09-02&outcome=handed_back")
            ->assertOk()
            ->assertJsonCount(2, 'parcels')
            ->assertJsonPath('parcels.0.outcome', 'handed_back');

        $this->actingAs($staff)
            ->getJson("/api/riders/{$ahmed->id}/parcels?from=2026-09-01&to=2026-09-02&search=" . urlencode($cancelled->tracking_number))
            ->assertOk()
            ->assertJsonCount(1, 'parcels')
            ->assertJsonPath('parcels.0.id', $cancelled->id);

        $this->actingAs($staff)
            ->getJson("/api/riders/{$ahmed->id}/parcels?from=2026-09-01&to=2026-09-02&outcome=nonsense")
            ->assertStatus(422)->assertJsonValidationErrors('outcome');
    }

    public function test_the_parcel_list_pages_through_a_busy_rider(): void
    {
        $rider = $this->makeRider();

        foreach (range(1, 27) as $i) {
            $this->event($rider, RiderAction::OUT_FOR_DELIVERY, sprintf('2026-09-01 08:%02d:00', $i));
        }

        $staff = $this->staff();
        $url = "/api/riders/{$rider->id}/parcels?from=2026-09-01&to=2026-09-01";

        $first = $this->actingAs($staff)->getJson($url)
            ->assertOk()
            ->assertJsonCount(25, 'parcels')
            ->assertJsonPath('next_page', 2);

        $second = $this->actingAs($staff)->getJson("{$url}&page=2")
            ->assertOk()
            ->assertJsonCount(2, 'parcels')
            ->assertJsonPath('next_page', null);

        $ids = array_merge(array_column($first->json('parcels'), 'id'), array_column($second->json('parcels'), 'id'));
        $this->assertCount(27, array_unique($ids));
    }

    public function test_removed_riders_are_left_out(): void
    {
        $rider = $this->makeRider();
        $this->event($rider, RiderAction::DELIVERED, '2026-09-01 10:00:00', 100);
        $rider->delete();

        $this->actingAs($this->staff())
            ->getJson('/api/riders/performance?from=2026-09-01&to=2026-09-01')
            ->assertOk()
            ->assertJsonPath('riders', []);
    }

    public function test_the_range_must_be_valid_and_at_most_92_days(): void
    {
        $staff = $this->staff();
        $rider = $this->makeRider();

        $this->actingAs($staff)->getJson('/api/riders/performance?from=2026-09-05&to=2026-09-01')
            ->assertStatus(422)->assertJsonValidationErrors('to');

        // 93 days, on every endpoint.
        $this->actingAs($staff)->getJson('/api/riders/performance?from=2026-06-01&to=2026-09-01')
            ->assertStatus(422)->assertJsonValidationErrors('to');
        $this->actingAs($staff)->getJson("/api/riders/{$rider->id}/performance?from=2026-06-01&to=2026-09-01")
            ->assertStatus(422)->assertJsonValidationErrors('to');
        $this->actingAs($staff)->getJson("/api/riders/{$rider->id}/parcels?from=2026-06-01&to=2026-09-01")
            ->assertStatus(422)->assertJsonValidationErrors('to');

        $this->actingAs($staff)->getJson('/api/riders/performance?from=01-09-2026&to=2026-09-02')
            ->assertStatus(422)->assertJsonValidationErrors('from');

        $this->actingAs($staff)->getJson('/api/riders/performance?from=2026-06-02&to=2026-09-01')
            ->assertOk();
    }

    public function test_staff_without_rider_permissions_cannot_see_performance(): void
    {
        $this->actingAs($this->staff(['view orders']))
            ->getJson('/api/riders/performance?from=2026-09-01&to=2026-09-01')
            ->assertForbidden();
    }

    private function event(
        Rider $rider,
        RiderAction $action,
        string $utc,
        ?float $cod = null,
        ?Shipment $shipment = null,
        ?string $reason = null,
    ): void {
        ShipmentEvent::create([
            'shipment_id' => ($shipment ?? $this->ksaShipment())->id,
            'rider_id' => $rider->id,
            'action' => $action->value,
            'status_after' => $action->targetStatus()->value,
            'reason' => $reason,
            'cod_amount' => $cod,
            'occurred_at' => Carbon::parse($utc, 'UTC'),
            'client_uuid' => (string) Str::uuid(),
        ]);
    }
}
