<?php

namespace Tests\Feature;

use App\Models\Connector;
use App\Models\ConnectorSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * ConnectorSettingsController::show() deliberately masks encrypted values
 * (they must never round-trip to the browser on a normal page load) —
 * reveal() is the separate, audit-logged, on-demand path for an authorized
 * user to fetch the real decrypted value. See ImileSettings.tsx's eye icon.
 */
class ConnectorSettingsRevealTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase resets the DB per test via a rolled-back transaction,
        // but Spatie's permission-registrar cache is process-level and survives
        // that rollback — without clearing it, a stale cached role/permission
        // mapping from a previous test causes a FK violation here.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $editApps = Permission::findOrCreate('edit apps');
        $adminRole = Role::findOrCreate('admin');
        $adminRole->givePermissionTo($editApps);
    }

    private function makeAuthorizedUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function makeConnectorWithApiKey(string $rawApiKey = 'super-secret-key-123'): Connector
    {
        // The 2026_08_05_000001_add_imile_connector migration already seeds
        // this row — reuse it rather than colliding on the unique `key`.
        $connector = Connector::firstOrCreate(
            ['key' => 'imile'],
            ['name' => 'iMile', 'description' => 'iMile courier', 'enabled' => true],
        );

        $setting = new ConnectorSetting(['connector_id' => $connector->id, 'key' => 'api_key', 'is_encrypted' => true]);
        $setting->value = $rawApiKey; // triggers ConnectorSetting::setValueAttribute() encryption
        $setting->save();

        return $connector;
    }

    public function test_show_never_returns_the_real_encrypted_value(): void
    {
        $connector = $this->makeConnectorWithApiKey('super-secret-key-123');

        $response = $this->actingAs($this->makeAuthorizedUser())
            ->getJson("/api/connectors/{$connector->id}/settings")
            ->assertOk();

        $this->assertSame('••••••••', $response->json('settings.api_key.value'));
    }

    public function test_reveal_returns_the_real_decrypted_value_for_an_authorized_user(): void
    {
        $connector = $this->makeConnectorWithApiKey('super-secret-key-123');

        $this->actingAs($this->makeAuthorizedUser())
            ->postJson("/api/connectors/{$connector->id}/settings/reveal", ['key' => 'api_key'])
            ->assertOk()
            ->assertJson(['value' => 'super-secret-key-123']);
    }

    public function test_reveal_is_denied_without_edit_apps_permission(): void
    {
        $connector = $this->makeConnectorWithApiKey();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/connectors/{$connector->id}/settings/reveal", ['key' => 'api_key'])
            ->assertForbidden();
    }

    public function test_reveal_404s_for_an_unknown_key(): void
    {
        $connector = $this->makeConnectorWithApiKey();

        $this->actingAs($this->makeAuthorizedUser())
            ->postJson("/api/connectors/{$connector->id}/settings/reveal", ['key' => 'does_not_exist'])
            ->assertNotFound();
    }

    public function test_reveal_404s_for_a_non_encrypted_key(): void
    {
        $connector = $this->makeConnectorWithApiKey();
        ConnectorSetting::create([
            'connector_id' => $connector->id,
            'key' => 'base_url',
            'value' => 'https://openapi.imile.com',
            'is_encrypted' => false,
        ]);

        $this->actingAs($this->makeAuthorizedUser())
            ->postJson("/api/connectors/{$connector->id}/settings/reveal", ['key' => 'base_url'])
            ->assertNotFound();
    }
}
