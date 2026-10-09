<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureRecycleBinUnlocked;
use App\Models\Rider;
use App\Models\User;
use App\Models\ShipmentEvent;
use App\Services\Riders\RiderPhoto;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * Removed riders land in the recycle bin's Riders tab. Restoring brings them
 * back signed out; a permanent delete is refused for anyone with delivery
 * history, since it would null out who delivered each parcel.
 */
class RiderRecycleBinTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    private const BIN_PERMISSIONS = ['view recycle bin', 'restore recycle bin', 'purge recycle bin'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
        Permission::findOrCreate('delete orders');
        foreach (self::BIN_PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }

        $this->withSession([EnsureRecycleBinUnlocked::SESSION_KEY => now()]);
    }

    public function test_a_removed_rider_is_listed_in_the_bin_with_who_removed_them(): void
    {
        $admin = $this->binStaff();
        $rider = $this->makeRider(['name' => 'Ahmed Khan', 'phone' => '+966551234567']);

        $this->actingAs($admin)->deleteJson("/api/riders/{$rider->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Ahmed Khan was moved to the recycle bin.');

        $this->actingAs($admin)->getJson('/api/recycle-bin/counts')->assertJsonPath('riders', 1);

        $this->actingAs($admin)->getJson('/api/recycle-bin/riders?search=0551234567')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rider->id)
            ->assertJsonPath('data.0.phone_local', '0551234567')
            ->assertJsonPath('data.0.deleted_by.name', $admin->name);
    }

    public function test_restoring_brings_the_rider_back_signed_out(): void
    {
        $admin = $this->binStaff();
        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);

        $this->actingAs($admin)->deleteJson("/api/riders/{$rider->id}")->assertOk();

        $this->actingAs($admin)->postJson('/api/recycle-bin/riders/restore', ['ids' => [$rider->id]])
            ->assertOk()
            ->assertJsonPath('restored_count', 1);

        $this->assertNotSoftDeleted($rider);
        $this->assertNull($rider->fresh()->deleted_by);
        $this->asRider($token)->getJson('/rider/api/me')->assertStatus(401);
    }

    public function test_purge_removes_a_rider_with_no_history_and_their_photo(): void
    {
        Storage::fake(RiderPhoto::DISK);
        $admin = $this->binStaff();
        $rider = $this->makeRider();
        RiderPhoto::replace($rider, UploadedFile::fake()->image('me.jpg'));
        $path = $rider->fresh()->photo_path;

        $this->actingAs($admin)->deleteJson("/api/riders/{$rider->id}")->assertOk();

        $this->actingAs($admin)->postJson('/api/recycle-bin/riders/purge', ['ids' => [$rider->id]])
            ->assertOk()
            ->assertJsonPath('purged_count', 1)
            ->assertJsonPath('blocked', []);

        $this->assertNull(Rider::withTrashed()->find($rider->id));
        Storage::disk(RiderPhoto::DISK)->assertMissing($path);
    }

    public function test_a_rider_with_delivery_history_cannot_be_purged(): void
    {
        $admin = $this->binStaff();
        $rider = $this->makeRider(['name' => 'Bilal']);
        $shipment = $this->ksaShipment(['status' => ShipmentStatus::DELIVERED->value]);
        ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'rider_id' => $rider->id,
            'action' => 'delivered',
            'status_after' => ShipmentStatus::DELIVERED->value,
            'occurred_at' => now(),
            'client_uuid' => (string) Str::uuid(),
        ]);
        $rider->delete();
        $clean = $this->makeRider();
        $clean->delete();

        $this->actingAs($admin)->getJson('/api/recycle-bin/riders')
            ->assertJson(fn ($json) => $json->where('data', fn ($rows) => collect($rows)->firstWhere('id', $rider->id)['parcels_count'] === 1)->etc());

        $this->actingAs($admin)->postJson('/api/recycle-bin/riders/purge-all')
            ->assertOk()
            ->assertJsonPath('purged_count', 1)
            ->assertJsonPath('blocked.0.name', 'Bilal');

        $this->assertSoftDeleted($rider);
        $this->assertNull(Rider::withTrashed()->find($clean->id));
    }

    public function test_the_riders_tab_needs_manage_riders(): void
    {
        $rider = $this->makeRider();
        $rider->delete();

        $this->actingAs($this->binStaff(['delete orders']))
            ->getJson('/api/recycle-bin/riders')
            ->assertForbidden();

        $this->actingAs($this->binStaff(['delete orders']))
            ->getJson('/api/recycle-bin/counts')
            ->assertJsonPath('riders', 0);

        $this->withoutVite()
            ->actingAs($this->binStaff(['view riders', 'manage riders']))
            ->get('/recycle-bin')
            ->assertOk();
    }

    public function test_an_edit_cannot_take_a_removed_riders_phone(): void
    {
        $admin = $this->staff();
        $this->makeRider(['phone' => '+966551234567'])->delete();
        $rider = $this->makeRider();

        $this->actingAs($admin)->putJson("/api/riders/{$rider->id}", ['name' => 'X', 'phone' => '0551234567'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    /** staff() plus full use of the bin, so the tests turn on the rider gate alone. */
    private function binStaff(?array $permissions = null): User
    {
        // One role holding both: a bin tab opens for someone who has the bin
        // and could have deleted what is in it.
        return $this->staff([
            ...($permissions ?? ['view riders', 'manage riders', 'manage rider payments', 'view orders', 'edit orders']),
            ...self::BIN_PERMISSIONS,
        ]);
    }
}
