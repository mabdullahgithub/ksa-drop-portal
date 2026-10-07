<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\RiderDevice;
use App\Services\Riders\RiderAuthService;
use App\Services\Riders\RiderPresence;
use App\Services\Riders\RiderSignInRefused;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderAuthController extends Controller
{
    public function __construct(private RiderAuthService $auth) {}

    /**
     * Use the activation link. Called by the installed app on first launch
     * (or by the browser tab, if the rider chose not to install).
     */
    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|max:100',
            'standalone' => 'boolean',
        ]);

        try {
            [, $plain] = $this->auth->claimActivation($validated['token'], $request);
        } catch (RiderSignInRefused $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason], 422);
        }

        return $this->signedIn($plain, $request);
    }

    /**
     * Phone + PIN, for when the link can't be used.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            'pin' => 'required|string|max:12',
            'standalone' => 'boolean',
        ]);

        try {
            [, $plain] = $this->auth->signInWithPin($validated['phone'], $validated['pin'], $request);
        } catch (RiderSignInRefused $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason], 422);
        }

        return $this->signedIn($plain, $request);
    }

    /**
     * Log out from the app: this phone only. Getting back in takes the PIN
     * or a new link, same as after the office signs a phone out.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var RiderDevice $device */
        $device = $request->attributes->get('rider_device');
        $device->forceFill(['revoked_at' => now(), 'revoked_reason' => RiderDevice::REVOKED_BY_RIDER])->save();
        RiderPresence::forget($device->rider);

        $response = response()->json(['redirect' => RiderAppController::START_URL]);

        return $this->auth->isNativeClient($request) ? $response : $response->withCookie($this->auth->forgetCookie());
    }

    /**
     * The web app gets the device token as an HttpOnly cookie its scripts
     * can't read. The phone app has no cookie, so it gets the token itself.
     */
    private function signedIn(string $plain, Request $request): JsonResponse
    {
        if ($this->auth->isNativeClient($request)) {
            return response()->json(['token' => $plain]);
        }

        return response()
            ->json(['redirect' => RiderAppController::START_URL])
            ->withCookie($this->auth->cookie($plain, $request));
    }
}
