<?php

namespace Tests\Feature;

use App\Http\Controllers\Rider\RiderAppController;
use App\Models\Rider;
use App\Services\Riders\RiderAuthService;
use App\Services\Riders\RiderSignInRefused;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * The activation link signs the installed app in for good; phone + PIN is
 * the fallback. Either way one phone per rider.
 */
class RiderSignInTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
        $this->withoutVite();
    }

    private function issueLink(Rider $rider): string
    {
        $url = $this->actingAs($this->staff())
            ->postJson("/api/riders/{$rider->id}/activation-link")
            ->assertOk()
            ->json('url');

        $this->assertMatchesRegularExpression('#/rider/activate/[A-Za-z0-9]{48}$#', $url);

        return substr($url, strrpos($url, '/') + 1);
    }

    private function claim(string $token)
    {
        // A fresh "phone": no cookies carried over from earlier requests.
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        return $this->withHeader('Origin', $this->appOrigin())
            ->postJson('/rider/api/activate', ['token' => $token, 'standalone' => true]);
    }

    private function cookieFrom($response): string
    {
        return $response->getCookie(RiderAuthService::COOKIE, true)->getValue();
    }

    public function test_the_admin_gets_a_whatsapp_ready_link(): void
    {
        $rider = $this->makeRider(['phone' => '+966551234567']);

        $this->actingAs($this->staff())
            ->postJson("/api/riders/{$rider->id}/activation-link")
            ->assertOk()
            ->assertJsonPath('rider.has_pending_link', true)
            ->assertJson(fn ($json) => $json
                ->where('whatsapp_url', fn ($url) => str_starts_with($url, 'https://wa.me/966551234567?text='))
                ->etc());
    }

    public function test_the_link_page_offers_install_with_a_manifest_that_starts_on_the_link(): void
    {
        $rider = $this->makeRider();
        $token = $this->issueLink($rider);

        $this->get("/rider/activate/{$token}")
            ->assertOk()
            ->assertSee("/rider/activate/{$token}/manifest.webmanifest", false)
            ->assertSee('"mode":"activate"', false);

        $this->get("/rider/activate/{$token}/manifest.webmanifest")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('start_url', "/rider/activate/{$token}")
            ->assertJsonPath('scope', '/rider/')
            ->assertJsonPath('display', 'standalone');
    }

    public function test_opening_the_link_in_the_app_signs_in_for_400_days(): void
    {
        $rider = $this->makeRider();
        $token = $this->issueLink($rider);

        $response = $this->claim($token)->assertOk()->assertJsonPath('redirect', '/rider/app');

        $cookie = $response->getCookie(RiderAuthService::COOKIE, true);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('/rider', $cookie->getPath());
        $this->assertGreaterThan(now()->addDays(399)->timestamp, $cookie->getExpiresTime());

        $this->asRider($cookie->getValue())->getJson('/rider/api/me')
            ->assertOk()
            ->assertJsonPath('rider.name', $rider->name);

        $this->assertDatabaseHas('rider_devices', ['rider_id' => $rider->id, 'sign_in_method' => 'activation', 'standalone' => true, 'revoked_at' => null]);
    }

    public function test_a_link_works_once(): void
    {
        $token = $this->issueLink($this->makeRider());

        $this->claim($token)->assertOk();
        $this->claim($token)->assertStatus(422)->assertJsonPath('code', 'link_used');

        $this->get("/rider/activate/{$token}")->assertOk()->assertSee('"reason":"link_used"', false);
    }

    public function test_a_link_expires_after_seven_days(): void
    {
        $token = $this->issueLink($this->makeRider());

        $this->travel(RiderAuthService::ACTIVATION_DAYS)->days();
        $this->travel(1)->minutes();

        $this->claim($token)->assertStatus(422)->assertJsonPath('code', 'link_expired');
    }

    public function test_a_new_link_replaces_the_unused_one(): void
    {
        $rider = $this->makeRider();
        $old = $this->issueLink($rider);
        $new = $this->issueLink($rider);

        $this->claim($old)->assertStatus(422)->assertJsonPath('code', 'link_replaced');
        $this->claim($new)->assertOk();
    }

    public function test_signing_in_on_a_second_phone_signs_the_first_one_out(): void
    {
        $rider = $this->makeRider();

        $first = $this->cookieFrom($this->claim($this->issueLink($rider)));
        $second = $this->cookieFrom($this->claim($this->issueLink($rider)));

        $this->asRider($first)->getJson('/rider/api/me')->assertStatus(401)->assertJsonPath('code', 'replaced');
        $this->asRider($second)->getJson('/rider/api/me')->assertOk();
    }

    public function test_an_installed_app_reopening_its_link_goes_straight_to_the_app(): void
    {
        $token = $this->issueLink($this->makeRider());
        $cookie = $this->cookieFrom($this->claim($token));

        $this->withCookie(RiderAuthService::COOKIE, $cookie)
            ->get("/rider/activate/{$token}")
            ->assertRedirect('/rider/app')
            ->assertCookie(RiderAuthService::COOKIE, $cookie);

        $this->withCookie(RiderAuthService::COOKIE, $cookie)
            ->get('/rider/app')
            ->assertOk()
            ->assertSee('"mode":"app"', false)
            ->assertCookie(RiderAuthService::COOKIE, $cookie);
    }

    /**
     * An app left open keeps running the build it started with. The page and
     * /me both say which build is live, so the app can tell and reload.
     */
    public function test_the_app_is_told_which_build_is_live(): void
    {
        $build = RiderAppController::build();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', (string) $build);

        $this->get('/rider/app')->assertOk()->assertSee('"build":"' . $build . '"', false);

        $this->asRider($this->signedInDevice($this->makeRider()))
            ->getJson('/rider/api/me')
            ->assertOk()
            ->assertJsonPath('build', $build);
    }

    public function test_the_app_asks_for_sign_in_without_a_cookie(): void
    {
        $this->get('/rider/app')->assertOk()->assertSee('"mode":"sign_in"', false);
        $this->get('/rider')->assertRedirect('/rider/app');
    }

    public function test_writes_from_another_site_are_refused(): void
    {
        $token = $this->issueLink($this->makeRider());

        $this->withHeader('Origin', 'https://evil.example')
            ->postJson('/rider/api/activate', ['token' => $token])
            ->assertForbidden()
            ->assertJsonPath('code', 'bad_origin');

        $this->postJson('/rider/api/activate', ['token' => $token])->assertForbidden();
    }

    public function test_the_admin_gives_a_pin_and_the_rider_signs_in_with_phone_and_pin(): void
    {
        $rider = $this->makeRider(['phone' => '+966551234567']);

        $pin = $this->actingAs($this->staff())
            ->postJson("/api/riders/{$rider->id}/pin")
            ->assertOk()
            ->assertJsonPath('phone', '0551234567')
            ->assertJsonPath('rider.has_pin', true)
            ->json('pin');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $pin);
        $this->assertNotSame($pin, $rider->fresh()->pin, 'PIN must be stored hashed.');

        $response = $this->withHeader('Origin', $this->appOrigin())
            ->postJson('/rider/api/login', ['phone' => '055 123 4567', 'pin' => $pin])
            ->assertOk();

        $this->asRider($this->cookieFrom($response))->getJson('/rider/api/me')->assertOk();
        $this->assertDatabaseHas('rider_devices', ['rider_id' => $rider->id, 'sign_in_method' => 'pin']);
    }

    public function test_a_wrong_pin_is_refused_and_too_many_lock_pin_sign_in(): void
    {
        $rider = $this->makeRider(['phone' => '+966551234567']);
        $auth = app(RiderAuthService::class);
        $pin = $auth->resetPin($rider);
        $wrong = $pin === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= Rider::MAX_PIN_ATTEMPTS; $i++) {
            try {
                $auth->signInWithPin('0551234567', $wrong, Request::create('/'));
                $this->fail('A wrong PIN signed in.');
            } catch (RiderSignInRefused $e) {
                $this->assertSame($i < Rider::MAX_PIN_ATTEMPTS ? 'invalid_credentials' : 'pin_locked', $e->reason);
            }
        }

        try {
            $auth->signInWithPin('0551234567', $pin, Request::create('/'));
            $this->fail('A locked PIN signed in.');
        } catch (RiderSignInRefused $e) {
            $this->assertSame('pin_locked', $e->reason);
        }

        // A new PIN from the admin clears the lock.
        $newPin = $auth->resetPin($rider->fresh());
        [$device] = $auth->signInWithPin('0551234567', $newPin, Request::create('/'));
        $this->assertSame($rider->id, $device->rider_id);
    }

    public function test_pin_sign_in_is_rate_limited_per_phone(): void
    {
        $rider = $this->makeRider(['phone' => '+966551234567']);
        app(RiderAuthService::class)->resetPin($rider);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('Origin', $this->appOrigin())
                ->postJson('/rider/api/login', ['phone' => '0551234567', 'pin' => '999999'])
                ->assertStatus(422);
        }

        $this->withHeader('Origin', $this->appOrigin())
            ->postJson('/rider/api/login', ['phone' => '0551234567', 'pin' => '999999'])
            ->assertStatus(429);
    }

    public function test_a_rider_without_a_pin_cannot_sign_in_with_one(): void
    {
        $this->makeRider(['phone' => '+966551234567']);

        $this->withHeader('Origin', $this->appOrigin())
            ->postJson('/rider/api/login', ['phone' => '0551234567', 'pin' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_credentials');
    }

    public function test_the_admin_can_sign_a_lost_phone_out(): void
    {
        $rider = $this->makeRider();
        $token = $this->signedInDevice($rider);

        $this->actingAs($this->staff())->postJson("/api/riders/{$rider->id}/sign-out")->assertOk();

        $this->asRider($token)->getJson('/rider/api/me')->assertStatus(401)->assertJsonPath('code', 'revoked');
    }

    public function test_a_suspended_rider_gets_no_link_and_cannot_use_one(): void
    {
        $rider = $this->makeRider();
        $token = $this->issueLink($rider);
        $rider->update(['status' => Rider::STATUS_SUSPENDED]);

        $this->claim($token)->assertStatus(422)->assertJsonPath('code', 'suspended');
        $this->actingAs($this->staff())->postJson("/api/riders/{$rider->id}/activation-link")->assertStatus(422);
    }
}
