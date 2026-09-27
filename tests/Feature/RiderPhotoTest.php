<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * Optional profile photo: the admin can set it, or the rider from the app.
 */
class RiderPhotoTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
        Storage::fake('local');
    }

    public function test_the_admin_can_add_replace_and_remove_a_riders_photo(): void
    {
        $admin = $this->staff();
        $rider = $this->makeRider();

        $this->actingAs($admin)->getJson('/api/riders')->assertJsonPath('riders.0.photo_url', null);

        $first = $this->actingAs($admin)
            ->post("/api/riders/{$rider->id}/photo", ['photo' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('rider.photo_url');

        $this->assertStringContainsString("/api/riders/{$rider->id}/photo?v=", $first);
        $firstPath = $rider->fresh()->photo_path;
        Storage::disk('local')->assertExists($firstPath);

        $second = $this->actingAs($admin)
            ->post("/api/riders/{$rider->id}/photo", ['photo' => UploadedFile::fake()->image('b.png')], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('rider.photo_url');

        $this->assertNotSame($first, $second, 'A new photo must get a new URL so it is not served from cache.');
        Storage::disk('local')->assertMissing($firstPath);

        $this->actingAs($admin)->get("/api/riders/{$rider->id}/photo")->assertOk();

        $this->actingAs($admin)->deleteJson("/api/riders/{$rider->id}/photo")->assertOk()->assertJsonPath('rider.photo_url', null);
        $this->assertNull($rider->fresh()->photo_path);
    }

    public function test_view_only_staff_see_the_photo_but_cannot_change_it(): void
    {
        $rider = $this->makeRider();
        $this->actingAs($this->staff())->post("/api/riders/{$rider->id}/photo", ['photo' => UploadedFile::fake()->image('a.jpg')]);

        $viewer = $this->staff(['view riders']);
        $this->actingAs($viewer)->get("/api/riders/{$rider->id}/photo")->assertOk();
        $this->actingAs($viewer)->deleteJson("/api/riders/{$rider->id}/photo")->assertForbidden();
    }

    public function test_only_images_are_accepted(): void
    {
        $rider = $this->makeRider();

        $this->actingAs($this->staff())
            ->post("/api/riders/{$rider->id}/photo", ['photo' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photo');
    }

    public function test_the_rider_can_set_their_own_photo_from_the_app(): void
    {
        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);

        $this->asRider($token)->getJson('/rider/api/me')->assertJsonPath('rider.photo_url', null);

        $url = $this->asRider($token)
            ->post('/rider/api/me/photo', ['photo' => UploadedFile::fake()->image('me.jpg')])
            ->assertOk()
            ->json('photo_url');

        $this->assertStringContainsString('/rider/api/me/photo?v=', $url);
        $this->asRider($token)->getJson('/rider/api/me')->assertJsonPath('rider.photo_url', $url);
        $this->asRider($token)->get('/rider/api/me/photo')->assertOk();

        // The admin sees the same photo.
        $this->actingAs($this->staff())->get("/api/riders/{$rider->id}/photo")->assertOk();
    }

    public function test_the_app_photo_needs_the_rider_signed_in(): void
    {
        $this->getJson('/rider/api/me/photo')->assertStatus(401);
        $this->withHeader('Origin', $this->appOrigin())
            ->post('/rider/api/me/photo', ['photo' => UploadedFile::fake()->image('me.jpg')], ['Accept' => 'application/json'])
            ->assertStatus(401);
    }
}
