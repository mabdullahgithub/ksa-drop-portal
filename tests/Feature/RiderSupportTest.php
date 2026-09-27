<?php

namespace Tests\Feature;

use App\Services\Riders\RiderSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * The admin sets a WhatsApp support contact on the Riders page; riders see
 * it on their Profile screen.
 */
class RiderSupportTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
    }

    public function test_the_admin_sets_the_contact_and_riders_see_it(): void
    {
        $token = $this->signedInDevice($this->makeRider());

        $this->asRider($token)->getJson('/rider/api/me')->assertOk()->assertJsonPath('support', null);

        $this->actingAs($this->staff())
            ->putJson('/api/riders/support', ['whatsapp' => '055 111 2222', 'name' => 'KSA Express Support'])
            ->assertOk()
            ->assertJsonPath('support.whatsapp', '+966551112222')
            ->assertJsonPath('support.whatsapp_local', '0551112222')
            ->assertJsonPath('support.name', 'KSA Express Support');

        $this->asRider($token)->getJson('/rider/api/me')
            ->assertOk()
            ->assertJsonPath('support.name', 'KSA Express Support')
            ->assertJsonPath('support.whatsapp', '0551112222')
            ->assertJsonPath('support.whatsapp_digits', '966551112222');
    }

    public function test_the_riders_page_carries_the_contact(): void
    {
        RiderSupport::set('+923001234567', null);

        $this->withoutVite()
            ->actingAs($this->staff(['view riders']))
            ->get('/riders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('support.whatsapp', '+923001234567')->where('support.name', null));
    }

    public function test_pakistani_and_international_numbers_are_accepted(): void
    {
        $admin = $this->staff();

        $this->actingAs($admin)->putJson('/api/riders/support', ['whatsapp' => '0300-1234567'])
            ->assertOk()->assertJsonPath('support.whatsapp', '+923001234567');

        $this->actingAs($admin)->putJson('/api/riders/support', ['whatsapp' => '+971 50 123 4567'])
            ->assertOk()->assertJsonPath('support.whatsapp', '+971501234567');

        $this->actingAs($admin)->putJson('/api/riders/support', ['whatsapp' => '12345'])
            ->assertStatus(422)->assertJsonValidationErrors('whatsapp');
    }

    public function test_clearing_the_number_removes_the_contact(): void
    {
        RiderSupport::set('+966551112222', 'Support');

        $this->actingAs($this->staff())->putJson('/api/riders/support', ['whatsapp' => '', 'name' => 'Support'])
            ->assertOk()->assertJsonPath('support', null);

        $this->assertNull(RiderSupport::get());
    }

    public function test_only_rider_managers_can_change_it(): void
    {
        $this->actingAs($this->staff(['view riders']))
            ->putJson('/api/riders/support', ['whatsapp' => '0551112222'])
            ->assertForbidden();
    }
}
