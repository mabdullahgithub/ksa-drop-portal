<?php

namespace Tests\Feature;

use App\Models\Rider;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

class RiderManagementTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
    }

    public function test_a_rider_needs_only_a_name_and_phone(): void
    {
        $hub = $this->warehouse();

        $response = $this->actingAs($this->staff())->postJson('/api/riders', [
            'name' => 'Ahmed Khan',
            'phone' => '055 123 4567',
        ]);

        $response->assertCreated()
            ->assertJsonPath('rider.phone', '+966551234567')
            ->assertJsonPath('rider.phone_local', '0551234567')
            ->assertJsonPath('rider.status', 'active')
            ->assertJsonPath('rider.warehouse_id', $hub->id)
            ->assertJsonPath('rider.has_pin', false);
    }

    public function test_phone_must_be_a_saudi_mobile_and_unique_however_it_is_typed(): void
    {
        $admin = $this->staff();
        $this->makeRider(['phone' => '+966551234567']);

        $this->actingAs($admin)->postJson('/api/riders', ['name' => 'X', 'phone' => '12345'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->actingAs($admin)->postJson('/api/riders', ['name' => 'X', 'phone' => '00966551234567'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->actingAs($admin)->postJson('/api/riders', ['name' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_a_pakistani_number_is_accepted_and_signs_in_with_a_pin(): void
    {
        $response = $this->actingAs($this->staff())->postJson('/api/riders', [
            'name' => 'Bilal Ahmed',
            'phone' => '0300-1234567',
        ]);

        $response->assertCreated()
            ->assertJsonPath('rider.phone', '+923001234567')
            ->assertJsonPath('rider.phone_local', '03001234567');

        $rider = Rider::findOrFail($response->json('rider.id'));
        $pin = $this->actingAs($this->staff())->postJson("/api/riders/{$rider->id}/pin")
            ->assertOk()
            ->assertJsonPath('phone', '03001234567')
            ->assertJson(fn ($json) => $json->where('whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/923001234567?'))->etc())
            ->json('pin');

        $this->withHeader('Origin', $this->appOrigin())
            ->postJson('/rider/api/login', ['phone' => '+92 300 1234567', 'pin' => $pin])
            ->assertOk();
    }

    /**
     * Every way of typing the same number lands on the same rider.
     */
    public function test_phone_numbers_normalise_for_saudi_arabia_and_pakistan(): void
    {
        $cases = [
            '0551234567' => '+966551234567',
            '551234567' => '+966551234567',
            '+966 55 123 4567' => '+966551234567',
            '00966551234567' => '+966551234567',
            '٠٥٥١٢٣٤٥٦٧' => '+966551234567',
            '03001234567' => '+923001234567',
            '3001234567' => '+923001234567',
            '+92 300 1234567' => '+923001234567',
            '0092-300-1234567' => '+923001234567',
            '0212345678' => null,     // Saudi landline
            '0421234567' => null,     // Pakistani landline
            '12345' => null,
        ];

        foreach ($cases as $input => $expected) {
            $this->assertSame($expected, \App\Support\PhoneNumber::normalize($input), (string) $input);
        }
    }

    public function test_optional_details_are_saved_and_iqama_is_encrypted_at_rest(): void
    {
        $response = $this->actingAs($this->staff())->postJson('/api/riders', [
            'name' => 'Ahmed Khan',
            'phone' => '0551234567',
            'national_id' => '2123456789',
            'vehicle_type' => 'motorcycle',
            'iban' => 'sa03 8000 0000 6080 1016 7519',
        ])->assertCreated();

        $rider = Rider::findOrFail($response->json('rider.id'));
        $this->assertSame('2123456789', $rider->national_id);
        $this->assertSame('SA0380000000608010167519', $rider->iban);
        $this->assertNotSame('2123456789', $rider->getRawOriginal('national_id'));
    }

    public function test_view_only_staff_cannot_change_riders_or_see_iqama(): void
    {
        $viewer = $this->staff(['view riders']);
        $rider = $this->makeRider(['national_id' => '2123456789']);

        $this->actingAs($viewer)->postJson('/api/riders', ['name' => 'X', 'phone' => '0551234567'])->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/riders/{$rider->id}/pin")->assertForbidden();

        $this->actingAs($viewer)->getJson('/api/riders')
            ->assertOk()
            ->assertJsonPath('riders.0.national_id', null);
    }

    public function test_staff_without_rider_permissions_cannot_open_riders(): void
    {
        $this->actingAs($this->staff(['view orders']))->getJson('/api/riders')->assertForbidden();
    }

    public function test_suspending_takes_effect_on_the_riders_next_request(): void
    {
        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);

        $this->asRider($token)->getJson('/rider/api/me')->assertOk();

        $this->actingAs($this->staff())->postJson("/api/riders/{$rider->id}/status", ['status' => 'suspended'])->assertOk();

        $this->asRider($token)->getJson('/rider/api/me')->assertStatus(403)->assertJsonPath('code', 'suspended');

        $this->actingAs($this->staff())->postJson("/api/riders/{$rider->id}/status", ['status' => 'active'])->assertOk();

        $this->asRider($token)->getJson('/rider/api/me')->assertOk();
    }

    public function test_a_rider_holding_parcels_cannot_be_removed(): void
    {
        $rider = $this->makeRider();
        $this->ksaShipment(['rider_id' => $rider->id, 'status' => ShipmentStatus::OUT_FOR_DELIVERY->value]);

        $this->actingAs($this->staff())->deleteJson("/api/riders/{$rider->id}")->assertStatus(422);
        $this->assertNotSoftDeleted($rider);
    }

    public function test_removing_signs_the_rider_out_and_re_adding_the_phone_restores_them(): void
    {
        $admin = $this->staff();
        $rider = $this->makeRider(['phone' => '+966551234567']);
        $token = $this->signedInDevice($rider);

        $this->actingAs($admin)->deleteJson("/api/riders/{$rider->id}")->assertOk();
        $this->assertSoftDeleted($rider);
        $this->asRider($token)->getJson('/rider/api/me')->assertStatus(401);

        $this->actingAs($admin)->postJson('/api/riders', ['name' => 'Ahmed K.', 'phone' => '0551234567'])
            ->assertCreated()
            ->assertJsonPath('rider.id', $rider->id)
            ->assertJsonPath('rider.name', 'Ahmed K.');
    }

    public function test_the_riders_page_renders_for_viewers(): void
    {
        $this->makeRider();

        $this->withoutVite()
            ->actingAs($this->staff(['view riders']))
            ->get('/riders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Riders')->has('riders', 1)->has('warehouses'));
    }
}
