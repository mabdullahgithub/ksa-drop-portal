<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesStaff;
use Tests\TestCase;

/**
 * The green "online" dot on the Users page: whoever used the portal in the
 * last few minutes.
 */
class UserPresenceTest extends TestCase
{
    use MakesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStaff();
    }

    public function test_using_the_portal_shows_the_user_online(): void
    {
        $viewer = $this->staffWith(['view users']);
        $member = $this->staffWith(['view orders']);
        $idle = User::factory()->create();

        $this->actingAs($member)->getJson('/api/orders')->assertOk();

        $this->assertEqualsCanonicalizing([$viewer->id, $member->id], $this->onlineFor($viewer));
        $this->assertNotContains($idle->id, $this->onlineFor($viewer));
    }

    public function test_the_user_drops_off_when_they_stop_using_the_portal(): void
    {
        $viewer = $this->staffWith(['view users']);
        $member = $this->staffWith(['view orders']);

        $this->actingAs($member)->getJson('/api/orders')->assertOk();

        $this->travel(299)->seconds();
        $this->assertContains($member->id, $this->onlineFor($viewer));

        $this->travel(2)->seconds();
        $this->assertNotContains($member->id, $this->onlineFor($viewer));
    }

    public function test_someone_who_keeps_working_stays_online(): void
    {
        $viewer = $this->staffWith(['view users']);
        $member = $this->staffWith(['view orders']);

        $this->actingAs($member)->getJson('/api/orders')->assertOk();
        $this->travel(200)->seconds();
        $this->actingAs($member)->getJson('/api/orders')->assertOk();
        $this->travel(200)->seconds();

        $this->assertContains($member->id, $this->onlineFor($viewer));
    }

    public function test_signing_out_takes_the_user_offline_straight_away(): void
    {
        $viewer = $this->staffWith(['view users']);
        $member = $this->staffWith(['view orders']);

        $this->actingAs($member)->getJson('/api/orders')->assertOk();
        $this->actingAs($member)->post('/logout');

        $this->assertNotContains($member->id, $this->onlineFor($viewer));
    }

    /** @return list<int> */
    private function onlineFor(User $viewer): array
    {
        $online = [];

        $this->actingAs($viewer)->get('/team-management/users')->assertOk()->assertInertia(
            function (Assert $page) use (&$online) {
                $online = $page->toArray()['props']['online'];
            }
        );

        return $online;
    }
}
