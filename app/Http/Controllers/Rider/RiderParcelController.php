<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Models\RiderPayment;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Services\Riders\RiderCash;
use App\Services\Riders\RiderDayStats;
use App\Services\Riders\RiderPay;
use App\Services\Riders\RiderPerformance;
use App\Services\Riders\RiderPhoto;
use App\Services\Riders\RiderSupport;
use App\Services\Riders\RiderParcelPresenter;
use App\Services\Shipping\Drivers\KsaDropExpressDriver;
use App\Services\Shipping\Enums\FailedAttemptReason;
use App\Services\Shipping\Enums\RiderAction;
use App\Services\Shipping\RiderActionRefused;
use App\Services\Shipping\ShipmentEventRecorder;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RiderParcelController extends Controller
{
    public function __construct(
        private ShipmentEventRecorder $recorder,
        private RiderParcelPresenter $presenter,
    ) {}

    public function me(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        return response()->json([
            'rider' => [
                'name' => $rider->name,
                'phone' => PhoneNumber::local($rider->phone),
                'hub' => $rider->warehouse?->name,
                'photo_url' => $rider->hasPhoto() ? route('rider.api.photo', ['v' => RiderPhoto::version($rider)]) : null,
            ],
            'today' => RiderDayStats::forRiders([$rider->id])[$rider->id],
            'cash' => RiderCash::forRider($rider->id),
            'pay' => RiderPay::forRider($rider->id) + ['rates' => RiderPay::ratesFor($rider)],
            // Who to message on WhatsApp for help; null until the admin sets it.
            'support' => ($support = RiderSupport::get()) ? [
                'name' => $support['name'],
                'whatsapp' => $support['whatsapp_local'],
                'whatsapp_digits' => PhoneNumber::forWhatsApp($support['whatsapp']),
            ] : null,
        ]);
    }

    /**
     * The rider's money, both ways: the COD cash they owe KSA Drop with what
     * they handed in, and what KSA Drop owes them with what it paid — newest
     * first. Voided entries never counted, so the rider doesn't see them.
     */
    public function cash(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        $payments = $rider->payments()
            ->counted()
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->groupBy('direction');

        $list = fn (string $direction) => ($payments[$direction] ?? collect())->take(100)->map(fn (RiderPayment $payment) => [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'received_at' => $payment->received_at->toIso8601String(),
        ])->values();

        return response()->json([
            'cash' => RiderCash::forRider($rider->id),
            'payments' => $list(RiderPayment::DIRECTION_IN),
            'pay' => RiderPay::forRider($rider->id) + ['rates' => RiderPay::ratesFor($rider)],
            'payouts' => $list(RiderPayment::DIRECTION_OUT),
        ]);
    }

    /**
     * Parcels in the rider's hands: to deliver, to try again, or to hand
     * back to the hub.
     */
    public function parcels(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        $shipments = $rider->heldShipments()
            ->with(RiderParcelPresenter::RELATIONS)
            ->withCount(RiderParcelPresenter::attemptsCount())
            ->orderBy('updated_at')
            ->get();

        return response()->json([
            'parcels' => $shipments->map(fn (Shipment $shipment) => $this->presenter->present($shipment, $rider))->values(),
        ]);
    }

    /**
     * What the rider has done, newest first: the "Delivered" list (with
     * ?action=delivered) and the total they check their cash against.
     *
     * range: today (default), yesterday, or week (the last 7 days incl. today),
     * in the business timezone. Or from + to: any two dates the rider picks
     * (KSA days, both inclusive), which win over range.
     *
     * The list stops at the newest 500; the summary counts the whole period,
     * so the totals stay right however long it is.
     */
    public function history(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        $validated = $request->validate([
            'action' => ['nullable', Rule::enum(RiderAction::class)],
            'range' => 'nullable|in:today,yesterday,week',
            'from' => 'nullable|required_with:to|date_format:Y-m-d',
            'to' => 'nullable|required_with:from|date_format:Y-m-d|after_or_equal:from',
        ]);

        $today = RiderDayStats::startOfToday();
        [$from, $until] = isset($validated['from'])
            ? $this->dayBounds($validated['from'], $validated['to'])
            : match ($validated['range'] ?? 'today') {
                'yesterday' => [$today->copy()->subDay(), $today],
                'week' => [$today->copy()->subDays(6), null],
                default => [$today, null],
            };

        $inPeriod = ShipmentEvent::where('rider_id', $rider->id)
            ->where('occurred_at', '>=', $from)
            ->when($until, fn ($q) => $q->where('occurred_at', '<', $until))
            ->when($validated['action'] ?? null, fn ($q, $action) => $q->where('action', $action));

        $totals = (clone $inPeriod)
            ->selectRaw(
                'COUNT(*) as total, COALESCE(SUM(cod_amount), 0) as cod, '
                . 'COALESCE(SUM(CASE WHEN payment_method = ? AND cod_amount > 0 THEN 1 ELSE 0 END), 0) as cash_total, '
                . 'COALESCE(SUM(CASE WHEN payment_method = ? THEN cod_amount END), 0) as cash',
                [RiderCash::OWED_METHOD, RiderCash::OWED_METHOD]
            )
            ->toBase()
            ->first();

        $events = $inPeriod
            ->with('shipment.order')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return response()->json([
            'summary' => [
                'count' => (int) $totals->total,
                'cod_collected' => round((float) $totals->cod, 2),
                // Paid in cash: what the rider has to hand in.
                'cash_count' => (int) $totals->cash_total,
                'cash_collected' => round((float) $totals->cash, 2),
            ],
            'events' => $events->map(fn (ShipmentEvent $event) => [
                'id' => $event->id,
                'action' => $event->action,
                'reason' => $event->reason,
                'cod_amount' => $event->cod_amount !== null ? (float) $event->cod_amount : null,
                'payment_method' => $event->payment_method,
                'recipient_name' => $event->recipient_name,
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'tracking_number' => $event->shipment?->tracking_number,
                'order_number' => $event->shipment?->order?->order_number,
                'receiver_name' => $event->shipment?->receiverDetails()['name'],
                'city' => $event->shipment?->receiverDetails()['city'],
                'currency' => $event->shipment?->api_response['cod_currency'] ?? $event->shipment?->order?->currency ?? 'SAR',
            ])->values(),
        ]);
    }

    /**
     * A scan picks the parcel up: if nobody holds it, it's assigned to this
     * rider and goes out for delivery — first scan wins. Used for every
     * camera scan, one at a time or in a batch at the hub.
     *
     * Anything else (already mine, with another rider, finished, not ours)
     * changes nothing and says why in `result`.
     */
    public function claim(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        $validated = $request->validate([
            'code' => 'required|string|max:500',
            'client_uuid' => 'required|uuid',
            'entry_method' => 'nullable|in:camera,manual',
            'occurred_at' => 'nullable|date',
        ]);

        $code = $this->normalizeCode($validated['code']);
        $shipment = $code === '' ? null : $this->findShipment($code);

        if (! $shipment) {
            return response()->json(['result' => 'not_found', 'message' => "No parcel found for {$code}.", 'code' => 'not_found'], 404);
        }

        $options = $this->recorder->optionsFor($shipment, $rider);
        $unclaimed = $shipment->rider_id === null && in_array(RiderAction::OUT_FOR_DELIVERY, $options['actions'], true);

        // A retry of a scan that already went through is still a claim.
        $retry = ShipmentEvent::where('client_uuid', $validated['client_uuid'])->where('rider_id', $rider->id)->exists();

        if ($unclaimed || $retry) {
            try {
                $this->recorder->record($shipment, $rider, RiderAction::OUT_FOR_DELIVERY, $validated);
            } catch (RiderActionRefused $e) {
                // Someone else's scan landed first.
                $fresh = $shipment->fresh();

                return response()->json([
                    'result' => $e->reason,
                    'message' => $e->getMessage(),
                    'parcel' => $this->presenter->present($fresh, $rider),
                ]);
            }

            return response()->json([
                'result' => 'claimed',
                'parcel' => $this->presenter->present($shipment->fresh(), $rider),
            ]);
        }

        // Finished (delivered, or handed back) says so even when this rider
        // is the one who finished it.
        $result = $options['blocked'] ?? 'already_mine';

        return response()->json([
            'result' => $result,
            'parcel' => $this->presenter->present($shipment, $rider),
        ]);
    }

    /**
     * Look up a scanned or typed code: the waybill number first, then the
     * order number (the smaller barcode on the label).
     */
    public function scan(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        $code = $this->normalizeCode((string) $request->query('code', ''));

        if ($code === '') {
            return response()->json(['message' => 'Scan or type a tracking number.', 'code' => 'not_found'], 404);
        }

        $shipment = $this->findShipment($code);

        if (! $shipment) {
            return response()->json(['message' => "No parcel found for {$code}.", 'code' => 'not_found'], 404);
        }

        return response()->json(['parcel' => $this->presenter->present($shipment, $rider)]);
    }

    public function record(Request $request, Shipment $shipment): JsonResponse
    {
        $rider = $this->rider($request);
        $action = RiderAction::tryFrom((string) $request->input('action'));
        $expectedCod = $shipment->expectedCodAmount();
        $isDelivery = $action === RiderAction::DELIVERED;

        $validated = $request->validate([
            'action' => ['required', Rule::enum(RiderAction::class)],
            'client_uuid' => 'required|uuid',
            'reason' => [Rule::requiredIf($action?->needsReason() ?? false), 'nullable', Rule::enum(FailedAttemptReason::class)],
            'note' => [
                Rule::requiredIf(fn () => $request->input('reason') === FailedAttemptReason::OTHER->value
                    || ($isDelivery && $expectedCod > 0 && abs((float) $request->input('cod_amount') - $expectedCod) >= 0.01)),
                'nullable', 'string', 'max:500',
            ],
            'cod_amount' => [Rule::requiredIf($isDelivery && $expectedCod > 0), 'nullable', 'numeric', 'min:0', 'max:999999'],
            'payment_method' => [Rule::requiredIf($isDelivery && $expectedCod > 0), 'nullable', 'in:cash,card,transfer'],
            'recipient_name' => 'nullable|string|max:255',
            // A delivery's photo is asked for, not required. A failed attempt
            // or a return needs one of the place: it shows the rider went.
            'photo' => [Rule::requiredIf($action?->needsProof() ?? false), 'nullable', 'image', 'max:8192'],
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'accuracy_m' => 'nullable|integer|min:0|max:100000',
            'entry_method' => 'nullable|in:camera,manual',
            'occurred_at' => 'nullable|date',
        ], [
            'note.required' => $request->input('reason') === FailedAttemptReason::OTHER->value
                ? 'Write what happened.'
                : 'The amount is different from the parcel\'s COD — write why.',
            'cod_amount.required' => 'Enter the amount collected.',
            'photo.required' => 'Take a photo of the place.',
            'reason.required' => $action === RiderAction::ATTEMPT_FAILED ? 'Choose why the delivery failed.' : 'Choose a reason.',
        ]);

        try {
            $event = $this->recorder->record($shipment, $rider, $action, $validated, $request->file('photo'));
        } catch (RiderActionRefused $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->reason,
                'parcel' => $this->presenter->present($shipment->fresh(), $rider),
            ], 409);
        }

        return response()->json([
            'event' => [
                'id' => $event->id,
                'action' => $event->action,
                'status_after' => $event->status_after,
            ],
            'parcel' => $this->presenter->present($shipment->fresh(), $rider),
        ]);
    }

    private function rider(Request $request): Rider
    {
        return $request->attributes->get('rider');
    }

    /**
     * Two KSA days, both inclusive, as the instants the period starts and
     * stops. No longer than the portal's own rider lists go.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dayBounds(string $from, string $to): array
    {
        $days = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        if ($days > RiderPerformance::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Pick a range of '.RiderPerformance::MAX_DAYS.' days or less.']);
        }

        $timezone = config('app.business_timezone', 'Asia/Riyadh');

        return [
            Carbon::createFromFormat('Y-m-d', $from, $timezone)->startOfDay()->utc(),
            Carbon::createFromFormat('Y-m-d', $to, $timezone)->addDay()->startOfDay()->utc(),
        ];
    }

    /**
     * The label's QR code holds a tracking URL (…/track?q=KSD…); take the
     * number out of it. Hardware scanners may add whitespace.
     */
    private function normalizeCode(string $raw): string
    {
        $code = trim($raw);

        if (preg_match('#^https?://#i', $code)) {
            parse_str((string) parse_url($code, PHP_URL_QUERY), $query);
            $code = trim((string) ($query['q'] ?? ''));
        }

        return mb_substr($code, 0, 100);
    }

    private function findShipment(string $code): ?Shipment
    {
        $upper = strtoupper($code);

        $shipment = Shipment::where('courier', KsaDropExpressDriver::KEY)
            ->where('tracking_number', $upper)
            ->first();

        if ($shipment) {
            return $shipment;
        }

        $orderNumber = ltrim($code, '#');

        $shipment = Shipment::where('courier', KsaDropExpressDriver::KEY)
            ->whereHas('order', fn ($q) => $q->whereIn('order_number', [$orderNumber, '#' . $orderNumber]))
            ->latest('id')
            ->first();

        // Another courier's waybill: return it so the app can say so, rather
        // than "not found".
        return $shipment ?? Shipment::where('tracking_number', $code)->first();
    }
}
