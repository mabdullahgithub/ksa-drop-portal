<?php

namespace App\Services\Riders;

use App\Models\Rider;
use App\Models\RiderActivation;
use App\Models\RiderDevice;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * How a rider gets signed in to the rider app, and stays signed in.
 *
 * Two ways in, both ending in the same long-lived device cookie:
 *  - an activation link the admin sends (the normal way — no typing), and
 *  - phone + 6-digit PIN the admin gives them, for when the link can't be
 *    used (expired, opened in the wrong place, new phone).
 *
 * One phone per rider: signing in anywhere signs every other phone out.
 *
 * The rider app never touches Laravel sessions — they expire after
 * SESSION_LIFETIME minutes idle, and "signed in until the app is removed"
 * is the whole point. The cookie lasts 400 days (the most browsers keep one)
 * and is renewed each time the app opens.
 */
class RiderAuthService
{
    public const COOKIE = 'ksa_rider';

    public const COOKIE_MINUTES = 400 * 24 * 60;

    public const ACTIVATION_DAYS = 7;

    private const ACTIVATION_TOKEN_LENGTH = 48;

    private const DEVICE_TOKEN_LENGTH = 64;

    /**
     * Mint a fresh activation link token, voiding any the rider hasn't used.
     */
    public function issueActivation(Rider $rider, ?User $by = null): array
    {
        return DB::transaction(function () use ($rider, $by) {
            $rider->activations()
                ->whereNull('claimed_at')
                ->whereNull('voided_at')
                ->update(['voided_at' => now()]);

            $token = Str::random(self::ACTIVATION_TOKEN_LENGTH);

            $activation = $rider->activations()->create([
                'token_hash' => $this->hash($token),
                'expires_at' => now()->addDays(self::ACTIVATION_DAYS),
                'created_by' => $by?->id,
            ]);

            return [$token, $activation];
        });
    }

    public function isActivationTokenShaped(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{' . self::ACTIVATION_TOKEN_LENGTH . '}$/', $token);
    }

    public function findActivation(string $token): ?RiderActivation
    {
        if (! $this->isActivationTokenShaped($token)) {
            return null;
        }

        return RiderActivation::with('rider')->where('token_hash', $this->hash($token))->first();
    }

    /**
     * Why a link can't be used, or null when it can.
     */
    public function activationProblem(?RiderActivation $activation): ?string
    {
        return match (true) {
            $activation === null || $activation->rider === null => 'link_invalid',
            $activation->claimed_at !== null => 'link_used',
            $activation->voided_at !== null => 'link_replaced',
            $activation->expires_at->isPast() => 'link_expired',
            ! $activation->rider->isActive() => 'suspended',
            default => null,
        };
    }

    /**
     * Use up an activation link and sign this phone in.
     *
     * @return array{0: RiderDevice, 1: string} the device and its plain cookie token
     *
     * @throws RiderSignInRefused
     */
    public function claimActivation(string $token, Request $request): array
    {
        return DB::transaction(function () use ($token, $request) {
            $activation = $this->isActivationTokenShaped($token)
                ? RiderActivation::with('rider')->where('token_hash', $this->hash($token))->lockForUpdate()->first()
                : null;

            if ($problem = $this->activationProblem($activation)) {
                throw new RiderSignInRefused($problem, $this->message($problem));
            }

            [$device, $plain] = $this->startDevice($activation->rider, 'activation', $request);

            $activation->update(['claimed_at' => now(), 'rider_device_id' => $device->id]);

            return [$device, $plain];
        });
    }

    /**
     * @return array{0: RiderDevice, 1: string} the device and its plain cookie token
     *
     * @throws RiderSignInRefused
     */
    public function signInWithPin(string $phone, string $pin, Request $request): array
    {
        $normalized = PhoneNumber::normalize($phone);
        $rider = $normalized ? Rider::where('phone', $normalized)->first() : null;

        if (! $rider || ! $rider->hasPin()) {
            throw new RiderSignInRefused('invalid_credentials', $this->message('invalid_credentials'));
        }

        if ($rider->isPinLocked()) {
            throw new RiderSignInRefused('pin_locked', $this->message('pin_locked'));
        }

        if (! Hash::check($pin, $rider->pin)) {
            $rider->increment('pin_failed_attempts');
            $reason = $rider->isPinLocked() ? 'pin_locked' : 'invalid_credentials';

            throw new RiderSignInRefused($reason, $this->message($reason));
        }

        if (! $rider->isActive()) {
            throw new RiderSignInRefused('suspended', $this->message('suspended'));
        }

        return DB::transaction(function () use ($rider, $request) {
            $rider->forceFill(['pin_failed_attempts' => 0])->save();

            return $this->startDevice($rider, 'pin', $request);
        });
    }

    /**
     * Generate a new 6-digit PIN, replacing the old one and clearing a lock.
     * The admin sees it once, to pass on to the rider.
     */
    public function resetPin(Rider $rider): string
    {
        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $rider->forceFill([
            'pin' => Hash::make($pin),
            'pin_set_at' => now(),
            'pin_failed_attempts' => 0,
        ])->save();

        return $pin;
    }

    /**
     * Sign every phone out, e.g. a lost phone.
     */
    public function signOutEverywhere(Rider $rider, string $reason = RiderDevice::REVOKED_BY_ADMIN): int
    {
        return $rider->devices()->active()->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
    }

    /**
     * The device a cookie token belongs to, revoked or not.
     */
    public function deviceForToken(?string $plain): ?RiderDevice
    {
        if (! is_string($plain) || strlen($plain) !== self::DEVICE_TOKEN_LENGTH) {
            return null;
        }

        return RiderDevice::with('rider')->where('token_hash', $this->hash($plain))->first();
    }

    /**
     * Why this device can't be used right now, or null when it can.
     */
    public function deviceProblem(?RiderDevice $device): ?string
    {
        return match (true) {
            $device === null || $device->rider === null => 'signed_out',
            $device->revoked_at !== null => $device->revoked_reason === RiderDevice::REVOKED_REPLACED ? 'replaced' : 'revoked',
            $device->rider->trashed() => 'revoked',
            ! $device->rider->isActive() => 'suspended',
            default => null,
        };
    }

    public function cookie(string $plain, Request $request): Cookie
    {
        return cookie(
            self::COOKIE,
            $plain,
            self::COOKIE_MINUTES,
            '/rider',
            null,
            $request->isSecure() || str_starts_with((string) config('app.url'), 'https://'),
            true,
            false,
            'lax',
        );
    }

    public function forgetCookie(): Cookie
    {
        return cookie()->forget(self::COOKIE, '/rider');
    }

    /**
     * @return array{0: RiderDevice, 1: string}
     */
    private function startDevice(Rider $rider, string $method, Request $request): array
    {
        $this->signOutEverywhere($rider, RiderDevice::REVOKED_REPLACED);

        $plain = Str::random(self::DEVICE_TOKEN_LENGTH);
        $userAgent = (string) $request->userAgent();

        $device = $rider->devices()->create([
            'token_hash' => $this->hash($plain),
            'sign_in_method' => $method,
            'platform' => $this->platform($userAgent),
            'standalone' => $request->boolean('standalone'),
            'user_agent' => Str::limit($userAgent, 497),
            'last_ip' => $request->ip(),
            'last_seen_at' => now(),
        ]);

        $rider->forceFill(['last_seen_at' => now()])->save();

        return [$device, $plain];
    }

    private function platform(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Android') => 'android',
            (bool) preg_match('/iPhone|iPad|iPod/', $userAgent) => 'ios',
            default => 'other',
        };
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function message(string $reason): string
    {
        return match ($reason) {
            'link_invalid' => 'This sign-in link is not valid.',
            'link_used' => 'This sign-in link has already been used.',
            'link_replaced' => 'A newer sign-in link was sent. Use the latest one.',
            'link_expired' => 'This sign-in link has expired.',
            'invalid_credentials' => 'Phone number or PIN is wrong.',
            'pin_locked' => 'Too many wrong PINs. Ask your supervisor for a new PIN.',
            'suspended' => 'Your account is suspended. Contact your supervisor.',
            default => 'Could not sign you in.',
        };
    }
}
