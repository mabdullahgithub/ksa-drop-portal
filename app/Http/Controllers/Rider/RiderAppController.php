<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Services\Riders\RiderAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The rider app's HTML shell and web app manifests.
 *
 * Everything lives under /rider/ — the installed app's scope. The shell is
 * /rider/app rather than /rider/ because the server 301s trailing slashes
 * away, and /rider on its own is outside a /rider/ scope.
 */
class RiderAppController extends Controller
{
    public const START_URL = '/rider/app';

    public function __construct(private RiderAuthService $auth) {}

    public function app(Request $request): Response
    {
        $token = $request->cookie(RiderAuthService::COOKIE);
        $device = $this->auth->deviceForToken($token);
        $problem = $this->auth->deviceProblem($device);

        $response = $this->shell([
            'mode' => $problem ? 'sign_in' : 'app',
            'reason' => $problem === 'signed_out' ? null : $problem,
            'rider' => $problem ? null : ['name' => $device->rider->name],
        ], route('rider.manifest'));

        if (! $problem) {
            // Renewed on every open, so an app in regular use never reaches
            // the cookie's expiry.
            return $response->withCookie($this->auth->cookie($token, $request));
        }

        return $problem === 'suspended' || ! $device ? $response : $response->withCookie($this->auth->forgetCookie());
    }

    /**
     * The link the admin sends. An installed app opens here every time (it is
     * the manifest's start_url), so a phone that's already signed in just
     * goes on to the app.
     */
    public function activate(Request $request, string $token): Response|RedirectResponse
    {
        $cookieToken = $request->cookie(RiderAuthService::COOKIE);

        if (! $this->auth->deviceProblem($this->auth->deviceForToken($cookieToken))) {
            return redirect(self::START_URL)->withCookie($this->auth->cookie($cookieToken, $request));
        }

        $activation = $this->auth->findActivation($token);
        $problem = $this->auth->activationProblem($activation);

        return $this->shell([
            'mode' => $problem ? 'sign_in' : 'activate',
            'reason' => $problem,
            'token' => $problem ? null : $token,
            'rider' => $problem ? null : ['name' => $activation->rider->publicName()],
        ], $problem ? route('rider.manifest') : route('rider.activate.manifest', $token));
    }

    public function manifest(): JsonResponse
    {
        return $this->manifestResponse(self::START_URL);
    }

    /**
     * Changes with every build. An installed app can stay open for days, so
     * it compares the build it runs with this one and reloads into the new.
     */
    public static function build(): ?string
    {
        $manifest = public_path('build/manifest.json');

        return is_file($manifest) ? substr(md5_file($manifest), 0, 12) : null;
    }

    /**
     * Same app, but starting on the activation link: on iPhone a home screen
     * app has its own cookie jar, separate from Safari's, so it has to use the
     * link itself on first launch.
     */
    public function activationManifest(string $token): JsonResponse
    {
        abort_unless($this->auth->isActivationTokenShaped($token), 404);

        return $this->manifestResponse('/rider/activate/' . $token);
    }

    private function shell(array $boot, string $manifestUrl): Response
    {
        return response()
            ->view('rider', ['boot' => $boot + ['build' => self::build()], 'manifestUrl' => $manifestUrl])
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function manifestResponse(string $startUrl): JsonResponse
    {
        return response()->json([
            'id' => '/rider/',
            'name' => 'KSA Express Rider',
            'short_name' => 'KSA Rider',
            'description' => 'Scan KSA Express parcels and update their delivery status.',
            'start_url' => $startUrl,
            'scope' => '/rider/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f2f2f7',
            'theme_color' => '#fe6414',
            'icons' => [
                ['src' => '/rider-icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/rider-icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/rider-icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES);
    }
}
