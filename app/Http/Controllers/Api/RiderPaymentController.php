<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Models\RiderPayment;
use App\Services\Riders\RiderCash;
use App\Services\Riders\RiderPay;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The money side of a rider on the portal's Riders page, both ways: the COD
 * cash they owe and hand in (`in`), and what KSA Drop owes and pays them for
 * their deliveries (`out`).
 */
class RiderPaymentController extends Controller
{
    /** Payments per page of a rider's list. */
    private const PER_PAGE = 25;

    public function index(Request $request, Rider $rider): JsonResponse
    {
        $validated = $request->validate([
            'direction' => ['nullable', Rule::in(RiderPayment::DIRECTIONS)],
            'page' => 'nullable|integer|min:1|max:1000',
            // One KSA day: its figures, and the payments recorded for it.
            'date' => 'nullable|date_format:Y-m-d',
        ]);
        $day = $validated['date'] ?? null;
        $page = (int) ($validated['page'] ?? 1);

        $payments = $rider->payments()
            ->where('direction', $validated['direction'] ?? RiderPayment::DIRECTION_IN)
            ->forDay($day)
            ->with(['recordedBy:id,name', 'voidedBy:id,name'])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->offset(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE + 1)
            ->get();

        return response()->json($this->balances($rider) + [
            'day' => $day ? ['cash' => RiderCash::forRider($rider->id, $day), 'pay' => RiderPay::forRider($rider->id, $day)] : null,
            'payments' => $payments->take(self::PER_PAGE)->map(fn (RiderPayment $payment) => $this->row($payment))->values(),
            'next_page' => $payments->count() > self::PER_PAGE ? $page + 1 : null,
        ]);
    }

    public function store(Request $request, Rider $rider): JsonResponse
    {
        $validated = $request->validate([
            'direction' => ['nullable', Rule::in(RiderPayment::DIRECTIONS)],
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'method' => ['required', Rule::in(RiderPayment::METHODS)],
            // From the rider: which of their COD it settles. Cash unless said.
            'cod_method' => ['nullable', Rule::in(RiderPayment::COD_METHODS)],
            // To the rider: which of their earnings it settles.
            'pay_source' => ['nullable', Rule::in(RiderPay::SOURCES)],
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
            // Yesterday's hand-in entered today is fine; a date ahead isn't.
            'received_at' => 'nullable|date|before_or_equal:now|after:-1 year',
            // The KSA day it settles, when recorded while looking at one day.
            'for_date' => 'nullable|date_format:Y-m-d|before_or_equal:tomorrow|after:-1 year',
            'client_uuid' => 'required|uuid',
        ], [
            'received_at.before_or_equal' => 'The date received can\'t be in the future.',
            'received_at.after' => 'The date received is too far back.',
        ]);

        $direction = $validated['direction'] ?? RiderPayment::DIRECTION_IN;

        try {
            $payment = $this->alreadyRecorded($rider, $validated['client_uuid']) ?? $rider->payments()->create([
                'direction' => $direction,
                'amount' => round((float) $validated['amount'], 2),
                'method' => $validated['method'],
                'cod_method' => $direction === RiderPayment::DIRECTION_IN ? ($validated['cod_method'] ?? 'cash') : null,
                'pay_source' => $direction === RiderPayment::DIRECTION_OUT ? ($validated['pay_source'] ?? null) : null,
                'reference' => filled($validated['reference'] ?? null) ? trim($validated['reference']) : null,
                'note' => filled($validated['note'] ?? null) ? trim($validated['note']) : null,
                'received_at' => $validated['received_at'] ?? now(),
                'for_date' => $validated['for_date'] ?? null,
                'recorded_by' => $request->user()->id,
                'client_uuid' => $validated['client_uuid'],
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // The same entry raced in twice; the other request recorded it.
            $payment = $this->alreadyRecorded($rider, $validated['client_uuid']) ?? throw $e;
        }

        $amount = 'SAR ' . number_format((float) $payment->amount, 2);

        return response()->json($this->balances($rider) + [
            'payment' => $this->row($payment),
            'message' => $payment->direction === RiderPayment::DIRECTION_OUT
                ? "Payment of {$amount} to {$rider->name} recorded."
                : "Payment of {$amount} recorded for {$rider->name}.",
        ], 201);
    }

    /**
     * Take a wrong entry out of the balance. It stays in the list, struck
     * through, with who voided it and why.
     */
    public function void(Request $request, Rider $rider, RiderPayment $payment): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:255']);

        if ($payment->isVoided()) {
            return response()->json(['message' => 'This payment was already voided.'], 422);
        }

        $payment->update([
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => trim($validated['reason']),
        ]);

        return response()->json($this->balances($rider) + [
            'payment' => $this->row($payment),
            'message' => $payment->direction === RiderPayment::DIRECTION_OUT
                ? 'Payment voided. It no longer counts towards what the rider has been paid.'
                : 'Payment voided. It no longer counts towards what the rider has paid.',
        ]);
    }

    /**
     * A retry of an entry that already went through (double click, lost
     * response) must not take the money off the balance twice.
     */
    private function alreadyRecorded(Rider $rider, string $clientUuid): ?RiderPayment
    {
        return $rider->payments()->where('client_uuid', $clientUuid)->first();
    }

    /**
     * Both of the rider's balances: the cash they owe, and the pay they're owed.
     */
    private function balances(Rider $rider): array
    {
        return ['cash' => RiderCash::forRider($rider->id), 'pay' => RiderPay::forRider($rider->id)];
    }

    private function row(RiderPayment $payment): array
    {
        $payment->loadMissing(['recordedBy:id,name', 'voidedBy:id,name']);

        return [
            'id' => $payment->id,
            'direction' => $payment->direction,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'cod_method' => $payment->cod_method,
            'pay_source' => $payment->pay_source,
            'reference' => $payment->reference,
            'note' => $payment->note,
            'received_at' => $payment->received_at->toIso8601String(),
            'for_date' => $payment->for_date?->toDateString(),
            'recorded_by' => $payment->recordedBy?->name,
            'created_at' => $payment->created_at?->toIso8601String(),
            'voided_at' => $payment->voided_at?->toIso8601String(),
            'voided_by' => $payment->voidedBy?->name,
            'void_reason' => $payment->void_reason,
        ];
    }
}
