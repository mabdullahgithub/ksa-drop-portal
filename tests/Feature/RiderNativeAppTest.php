<?php

namespace Tests\Feature;

use App\Services\Riders\RiderAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * The phone app (rider-native/) runs on its own origin: no cookie, no
 * matching Origin. It gets the device token in the sign-in answer and sends
 * it back as a Bearer token.
 */
class RiderNativeAppTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    private const APP_ORIGIN = 'http://127.0.0.1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
    }

    /** Requests as the phone app makes them. */
    private function asApp(?string $token = null): static
    {
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        $this->withHeader('Origin', self::APP_ORIGIN)
            ->withHeader(RiderAuthService::CLIENT_HEADER, RiderAuthService::CLIENT_NATIVE);

        return $token ? $this->withToken($token) : $this;
    }

    private function pinFor($rider): string
    {
        return app(RiderAuthService::class)->resetPin($rider);
    }

    public function test_signing_in_with_a_pin_hands_the_app_its_token_and_no_cookie(): void
    {
        $rider = $this->makeRider(['phone' => '+966551234567']);

        $response = $this->asApp()
            ->postJson('/rider/api/login', ['phone' => '0551234567', 'pin' => $this->pinFor($rider), 'standalone' => true])
            ->assertOk()
            ->assertCookieMissing(RiderAuthService::COOKIE);

        $token = $response->json('token');
        $this->assertSame(64, strlen($token));

        $this->asApp($token)->getJson('/rider/api/me')->assertOk()->assertJsonPath('rider.name', $rider->name);
    }

    public function test_an_activation_link_hands_the_app_its_token(): void
    {
        $rider = $this->makeRider();
        [$link] = app(RiderAuthService::class)->issueActivation($rider);

        $token = $this->asApp()->postJson('/rider/api/activate', ['token' => $link])->assertOk()->json('token');

        $this->asApp($token)->getJson('/rider/api/me')->assertOk();
    }

    public function test_the_app_can_write_from_its_own_origin(): void
    {
        $token = $this->signedInDevice($this->makeRider());

        $this->asApp($token)->postJson('/rider/api/presence')->assertNoContent();
    }

    public function test_the_app_is_signed_out_without_a_token_or_once_it_logs_out(): void
    {
        $token = $this->signedInDevice($this->makeRider());

        $this->asApp()->getJson('/rider/api/me')->assertStatus(401)->assertJsonPath('code', 'signed_out');

        $this->asApp($token)->postJson('/rider/api/logout')->assertOk();
        $this->asApp($token)->getJson('/rider/api/me')->assertStatus(401)->assertJsonPath('code', 'revoked');
    }

    /**
     * The header that skips the Origin check must not let another site use
     * the cookie a browser adds by itself.
     */
    public function test_a_browser_cookie_does_not_count_for_a_request_claiming_to_be_the_app(): void
    {
        $token = $this->signedInDevice($this->makeRider());

        $this->asApp()
            ->withCredentials()
            ->withHeader('Origin', 'https://evil.example')
            ->withCookie(RiderAuthService::COOKIE, $token)
            ->postJson('/rider/api/presence')
            ->assertStatus(401);
    }

    public function test_a_bearer_token_alone_does_not_skip_the_origin_check(): void
    {
        $token = $this->signedInDevice($this->makeRider());

        $this->flushHeaders();
        $this->withHeader('Origin', 'https://evil.example')
            ->withToken($token)
            ->postJson('/rider/api/presence')
            ->assertForbidden();
    }

    public function test_the_api_answers_the_apps_preflight(): void
    {
        $this->withHeaders([
            'Origin' => 'php://127.0.0.1',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type,x-rider-client',
        ])->options('/rider/api/presence')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
