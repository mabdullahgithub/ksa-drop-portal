<?php

namespace Tests\Feature;

use App\Models\RiderDevice;
use App\Services\Riders\RiderAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * The green "online" dot on the Riders page, fed by the app's check-ins,
 * and the rider logging themselves out.
 */
class RiderPresenceTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
    }

    public function test_a_check_in_shows_the_rider_online(): void
    {
        $rider = $this->makeRider();
        $other = $this->makeRider(['name' => 'Bilal']);
        $token = $this->signedInDevice($rider);

        $viewer = $this->staff(['view riders']);
        $this->actingAs($viewer)->getJson('/api/riders/presence')->assertOk()->assertExactJson(['online' => []]);

        $this->asRider($token)->postJson('/rider/api/presence')->assertNoContent();

        $this->actingAs($viewer)->getJson('/api/riders/presence')->assertOk()->assertExactJson(['online' => [$rider->id]]);

        $riders = collect($this->actingAs($viewer)->getJson('/api/riders')->assertOk()->json('riders'))->keyBy('id');
        $this->assertTrue($riders[$rider->id]['online']);
        $this->assertFalse($riders[$other->id]['online']);
    }

    public function test_the_rider_drops_off_when_the_check_ins_stop(): void
    {
        $rider = $this->makeRider();
        $this->asRider($this->signedInDevice($rider))->postJson('/rider/api/presence')->assertNoContent();

        $this->travel(119)->seconds();
        $this->actingAs($this->staff())->getJson('/api/riders/presence')->assertExactJson(['online' => [$rider->id]]);

        $this->travel(2)->seconds();
        $this->actingAs($this->staff())->getJson('/api/riders/presence')->assertExactJson(['online' => []]);
    }

    public function test_signing_out_or_suspending_takes_the_rider_offline_at_once(): void
    {
        $admin = $this->staff();

        $signedOut = $this->makeRider();
        $this->asRider($this->signedInDevice($signedOut))->postJson('/rider/api/presence')->assertNoContent();
        $this->actingAs($admin)->postJson("/api/riders/{$signedOut->id}/sign-out")->assertOk()->assertJsonPath('rider.online', false);

        $suspended = $this->makeRider();
        $this->asRider($this->signedInDevice($suspended))->postJson('/rider/api/presence')->assertNoContent();
        $this->actingAs($admin)->postJson("/api/riders/{$suspended->id}/status", ['status' => 'suspended'])->assertOk()->assertJsonPath('rider.online', false);

        $this->actingAs($admin)->getJson('/api/riders/presence')->assertExactJson(['online' => []]);
    }

    public function test_checking_in_needs_a_signed_in_phone(): void
    {
        $this->withHeader('Origin', $this->appOrigin())->postJson('/rider/api/presence')->assertStatus(401);
    }

    public function test_the_rider_logs_out_of_this_phone(): void
    {
        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);
        $this->asRider($token)->postJson('/rider/api/presence')->assertNoContent();

        $this->asRider($token)->postJson('/rider/api/logout')
            ->assertOk()
            ->assertJsonPath('redirect', '/rider/app')
            ->assertCookieExpired(RiderAuthService::COOKIE);

        $this->assertDatabaseHas('rider_devices', ['rider_id' => $rider->id, 'revoked_reason' => RiderDevice::REVOKED_BY_RIDER]);
        $this->asRider($token)->getJson('/rider/api/me')->assertStatus(401);
        $this->actingAs($this->staff())->getJson('/api/riders/presence')->assertExactJson(['online' => []]);
    }
}
