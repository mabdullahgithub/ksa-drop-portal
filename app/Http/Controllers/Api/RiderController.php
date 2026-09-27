<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Rider\RiderAppController;
use App\Models\Rider;
use App\Models\RiderDevice;
use App\Models\Warehouse;
use App\Services\Riders\RiderAuthService;
use App\Services\Riders\RiderDayStats;
use App\Services\Riders\RiderPresence;
use App\Services\Riders\RiderPhoto;
use App\Services\Riders\RiderSupport;
use App\Support\ClientDevice;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portal side of KSA Express riders: the Riders page and its actions.
 */
class RiderController extends Controller
{
    public function __construct(private RiderAuthService $auth) {}

    public function page(Request $request): Response
    {
        return Inertia::render('Riders', [
            'riders' => $this->rows($request),
            'warehouses' => Warehouse::active()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']),
            'support' => RiderSupport::get(),
        ]);
    }

    /**
     * The WhatsApp contact riders see on their Profile screen. Send an empty
     * number to remove it.
     */
    public function updateSupport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp' => 'nullable|string|max:30',
            'name' => 'nullable|string|max:100',
        ]);

        $phone = null;
        if (filled($validated['whatsapp'] ?? null)) {
            $phone = RiderSupport::normalize($validated['whatsapp']);

            if (! $phone) {
                throw ValidationException::withMessages([
                    'whatsapp' => 'Enter a Saudi (05…) or Pakistani (03…) mobile, or any number starting with + and the country code.',
                ]);
            }
        }

        RiderSupport::set($phone, filled($validated['name'] ?? null) ? trim($validated['name']) : null);

        return response()->json([
            'support' => RiderSupport::get(),
            'message' => $phone ? 'Rider support contact saved.' : 'Rider support contact removed.',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['riders' => $this->rows($request)]);
    }

    /**
     * Ids of the riders with the app open right now. The Riders page polls
     * this to keep its green dots current.
     */
    public function presence(): JsonResponse
    {
        return response()->json(['online' => RiderPresence::online(Rider::pluck('id')->all())]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        // A removed rider coming back keeps their history, rather than the
        // phone number being stuck on a deleted row.
        $trashed = Rider::onlyTrashed()->where('phone', $validated['phone'])->first();

        if ($trashed) {
            $trashed->restore();
            $trashed->update($validated + ['status' => Rider::STATUS_ACTIVE]);

            return response()->json([
                'rider' => $this->row($trashed->fresh(), $request),
                'message' => 'This phone belonged to a removed rider — their account has been restored with these details.',
            ], 201);
        }

        $rider = Rider::create($validated + [
            'warehouse_id' => $validated['warehouse_id'] ?? Warehouse::getDefault()?->id,
            'status' => Rider::STATUS_ACTIVE,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['rider' => $this->row($rider->fresh(), $request), 'message' => 'Rider added.'], 201);
    }

    public function update(Request $request, Rider $rider): JsonResponse
    {
        $rider->update($this->validated($request, $rider));

        return response()->json(['rider' => $this->row($rider->fresh(), $request), 'message' => 'Rider updated.']);
    }

    public function status(Request $request, Rider $rider): JsonResponse
    {
        $validated = $request->validate(['status' => 'required|in:active,suspended']);

        $rider->update(['status' => $validated['status']]);
        if ($validated['status'] === Rider::STATUS_SUSPENDED) {
            RiderPresence::forget($rider);
        }

        return response()->json([
            'rider' => $this->row($rider->fresh(), $request),
            'message' => $validated['status'] === Rider::STATUS_ACTIVE ? 'Rider reactivated.' : 'Rider suspended. Their app stops working immediately.',
        ]);
    }

    /**
     * A new one-time link. Voids any earlier unused one.
     */
    public function activationLink(Request $request, Rider $rider): JsonResponse
    {
        if (! $rider->isActive()) {
            return response()->json(['message' => 'Reactivate the rider before sending a link.'], 422);
        }

        [$token, $activation] = $this->auth->issueActivation($rider, $request->user());

        $url = route('rider.activate', $token);
        $expires = $activation->expires_at->setTimezone(config('app.business_timezone', 'Asia/Riyadh'));

        $text = "Hi {$rider->publicName()}, this is your KSA Express Rider app.\n"
            . "Open this link in Chrome (Android) or Safari (iPhone) and install the app:\n{$url}\n\n"
            . "The link works once and expires on {$expires->format('d M Y')}.\n\n"
            . "مرحباً {$rider->publicName()}، هذا رابط تطبيق مندوب KSA Express. افتح الرابط في Chrome أو Safari وثبّت التطبيق.";

        return response()->json([
            'url' => $url,
            'expires_at' => $activation->expires_at->toIso8601String(),
            'whatsapp_url' => $this->whatsAppUrl($rider, $text),
            'message_text' => $text,
            'rider' => $this->row($rider->fresh(), $request),
        ]);
    }

    /**
     * A new PIN for phone + PIN sign-in. Shown to the admin once; only the
     * hash is kept.
     */
    public function resetPin(Request $request, Rider $rider): JsonResponse
    {
        $pin = $this->auth->resetPin($rider);
        $phone = PhoneNumber::local($rider->phone);
        $appUrl = url(RiderAppController::START_URL);

        $text = "KSA Express Rider sign-in\nPhone: {$phone}\nPIN: {$pin}\n\n"
            . "Open {$appUrl} and sign in with your phone and PIN.\n"
            . "Don't share your PIN with anyone.";

        return response()->json([
            'pin' => $pin,
            'phone' => $phone,
            'app_url' => $appUrl,
            'whatsapp_url' => $this->whatsAppUrl($rider, $text),
            'message_text' => $text,
            'rider' => $this->row($rider->fresh(), $request),
        ]);
    }

    public function signOut(Request $request, Rider $rider): JsonResponse
    {
        $count = $this->auth->signOutEverywhere($rider);
        RiderPresence::forget($rider);

        return response()->json([
            'rider' => $this->row($rider->fresh(), $request),
            'message' => $count ? 'The rider\'s phone was signed out.' : 'The rider was not signed in on any phone.',
        ]);
    }

    public function photo(Rider $rider): StreamedResponse
    {
        return RiderPhoto::response($rider);
    }

    public function uploadPhoto(Request $request, Rider $rider): JsonResponse
    {
        $request->validate(['photo' => RiderPhoto::RULES]);

        RiderPhoto::replace($rider, $request->file('photo'));

        return response()->json(['rider' => $this->row($rider->fresh(), $request), 'message' => 'Photo updated.']);
    }

    public function removePhoto(Request $request, Rider $rider): JsonResponse
    {
        RiderPhoto::remove($rider);

        return response()->json(['rider' => $this->row($rider->fresh(), $request), 'message' => 'Photo removed.']);
    }

    public function destroy(Rider $rider): JsonResponse
    {
        $held = $rider->heldShipments()->count();

        if ($held > 0) {
            return response()->json([
                'message' => "{$rider->name} still has {$held} parcel(s). Finish or unassign them before removing the rider.",
            ], 422);
        }

        DB::transaction(function () use ($rider) {
            $this->auth->signOutEverywhere($rider, RiderDevice::REVOKED_RIDER_DELETED);
            RiderPresence::forget($rider);
            $rider->activations()->whereNull('claimed_at')->whereNull('voided_at')->update(['voided_at' => now()]);
            $rider->delete();
        });

        return response()->json(['message' => "{$rider->name} was moved to the recycle bin."]);
    }

    /**
     * Name and phone are all a rider needs; everything else is optional.
     */
    private function validated(Request $request, ?Rider $rider = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'name_ar' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|max:30',
            'nationality' => 'nullable|string|max:100',
            'vehicle_type' => 'nullable|in:motorcycle,car,van,bicycle,other',
            'vehicle_plate' => 'nullable|string|max:50',
            'license_number' => 'nullable|string|max:100',
            'license_expiry' => 'nullable|date',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'city' => 'nullable|string|max:100',
            'employment_type' => 'nullable|in:staff,freelancer,agency',
            'iban' => 'nullable|string|max:34',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:1000',
        ]);

        $phone = PhoneNumber::normalize($validated['phone']);

        if (! $phone) {
            throw ValidationException::withMessages(['phone' => PhoneNumber::HINT]);
        }

        $clash = Rider::withTrashed()->where('phone', $phone)->when($rider, fn ($q) => $q->whereKeyNot($rider->id))->first();

        if ($clash && ! $clash->trashed()) {
            throw ValidationException::withMessages(['phone' => 'Another rider already uses this phone number.']);
        }

        // Adding a removed rider's phone restores them (see store()); an edit
        // can't take it over, since the unique index still counts their row.
        if ($clash && $rider) {
            throw ValidationException::withMessages(['phone' => 'This phone belongs to a removed rider. Restore them from the recycle bin instead.']);
        }

        $validated['phone'] = $phone;

        if (isset($validated['iban'])) {
            $validated['iban'] = strtoupper(preg_replace('/\s+/', '', $validated['iban']));
        }

        return $validated;
    }

    private function rows(Request $request): array
    {
        $riders = Rider::with(['warehouse:id,name', 'activeDevice'])
            ->withExists(['activations as has_pending_link' => fn ($q) => $q
                ->whereNull('claimed_at')
                ->whereNull('voided_at')
                ->where('expires_at', '>', now())])
            ->orderBy('name')
            ->get();

        $stats = RiderDayStats::forRiders($riders->pluck('id')->all());
        $online = array_flip(RiderPresence::online($riders->pluck('id')->all()));

        return $riders->map(fn (Rider $rider) => $this->row($rider, $request, $stats[$rider->id], isset($online[$rider->id])))->all();
    }

    private function row(Rider $rider, Request $request, ?array $stats = null, ?bool $online = null): array
    {
        $rider->loadMissing(['warehouse:id,name', 'activeDevice']);
        $device = $rider->activeDevice;

        // Iqama and IBAN only for people who can edit riders.
        $canManage = $request->user()?->can('manage riders') ?? false;

        return [
            'id' => $rider->id,
            'name' => $rider->name,
            'name_ar' => $rider->name_ar,
            'photo_url' => $rider->hasPhoto() ? route('api.riders.photo', ['rider' => $rider, 'v' => RiderPhoto::version($rider)]) : null,
            'phone' => $rider->phone,
            'phone_local' => PhoneNumber::local($rider->phone),
            'national_id' => $canManage ? $rider->national_id : null,
            'nationality' => $rider->nationality,
            'vehicle_type' => $rider->vehicle_type,
            'vehicle_plate' => $rider->vehicle_plate,
            'license_number' => $rider->license_number,
            'license_expiry' => $rider->license_expiry?->toDateString(),
            'warehouse_id' => $rider->warehouse_id,
            'warehouse_name' => $rider->warehouse?->name,
            'city' => $rider->city,
            'employment_type' => $rider->employment_type,
            'iban' => $canManage ? $rider->iban : null,
            'emergency_contact_name' => $rider->emergency_contact_name,
            'emergency_contact_phone' => $rider->emergency_contact_phone,
            'notes' => $rider->notes,
            'status' => $rider->status,
            'has_pin' => $rider->hasPin(),
            'pin_locked' => $rider->isPinLocked(),
            'has_pending_link' => (bool) ($rider->has_pending_link
                ?? $rider->activations()->whereNull('claimed_at')->whereNull('voided_at')->where('expires_at', '>', now())->exists()),
            'device' => $device ? [
                'platform' => $device->platform,
                'label' => ClientDevice::describe($device->user_agent),
                'sign_in_method' => $device->sign_in_method,
                'standalone' => $device->standalone,
                'signed_in_at' => $device->created_at?->toIso8601String(),
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            ] : null,
            'last_seen_at' => $rider->last_seen_at?->toIso8601String(),
            'online' => $online ?? RiderPresence::isOnline($rider),
            'stats' => $stats ?? RiderDayStats::forRiders([$rider->id])[$rider->id],
            'created_at' => $rider->created_at?->toIso8601String(),
        ];
    }

    private function whatsAppUrl(Rider $rider, string $text): string
    {
        return 'https://wa.me/' . PhoneNumber::forWhatsApp($rider->phone) . '?text=' . rawurlencode($text);
    }
}
