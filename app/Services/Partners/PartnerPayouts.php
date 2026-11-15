<?php

namespace App\Services\Partners;

use App\Models\Partner;
use App\Models\PartnerPayoutRequest as PayoutRequest;
use App\Models\PartnerPointTransaction as Transaction;
use App\Models\PartnerSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Redeeming points. A request holds the points until it is decided:
 * approving takes them off the balance, rejecting an approved request puts
 * them back, and paying records what was actually transferred.
 */
class PartnerPayouts
{
    public const DISK = 'local';

    public function __construct(private PartnerPoints $points) {}

    public function request(Partner $partner, int $points, ?string $note = null): PayoutRequest
    {
        $min = (int) PartnerSetting::get('min_redeem_points');

        if ($points < $min) {
            throw ValidationException::withMessages(['points' => "The minimum redemption is {$min} points."]);
        }

        if (! $partner->payout_method || empty($partner->payout_method['type'])) {
            throw ValidationException::withMessages(['points' => 'Add a payout method to your profile first.']);
        }

        if ($partner->payoutRequests()->whereIn('status', [PayoutRequest::STATUS_REQUESTED, PayoutRequest::STATUS_APPROVED])->exists()) {
            throw ValidationException::withMessages(['points' => 'You already have a payout request in progress.']);
        }

        if ($points > $this->available($partner)) {
            throw ValidationException::withMessages(['points' => 'You do not have that many points.']);
        }

        return $partner->payoutRequests()->create([
            'points' => $points,
            'status' => PayoutRequest::STATUS_REQUESTED,
            'payout_method' => $partner->payout_method,
            'partner_note' => $note,
        ]);
    }

    /** The balance less what is held by an open request. */
    public function available(Partner $partner): int
    {
        $held = (int) $partner->payoutRequests()->where('status', PayoutRequest::STATUS_REQUESTED)->sum('points');

        return max(0, $partner->points_balance - $held);
    }

    public function approve(PayoutRequest $request, User $by, ?string $note = null): PayoutRequest
    {
        abort_unless($request->status === PayoutRequest::STATUS_REQUESTED, 422, 'Only a requested payout can be approved.');

        return DB::transaction(function () use ($request, $by, $note) {
            $this->points->award(
                $request->partner,
                Transaction::TYPE_PAYOUT,
                -$request->points,
                "Payout request #{$request->id} approved",
                $request,
                by: $by,
            );

            $request->forceFill([
                'status' => PayoutRequest::STATUS_APPROVED,
                'admin_note' => $note ?? $request->admin_note,
                'handled_by' => $by->id,
            ])->save();

            return $request;
        });
    }

    public function pay(PayoutRequest $request, User $by, float $amount, string $currency, ?UploadedFile $proof, ?string $note = null): PayoutRequest
    {
        abort_unless($request->status === PayoutRequest::STATUS_APPROVED, 422, 'Approve the payout before marking it paid.');

        $path = $request->proof_path;

        if ($proof) {
            $path = $proof->storeAs(
                'partners/' . $request->partner_id . '/payouts',
                Str::uuid() . '.' . ($proof->guessExtension() ?: 'bin'),
                self::DISK,
            );
        }

        $request->forceFill([
            'status' => PayoutRequest::STATUS_PAID,
            'amount_paid' => $amount,
            'currency' => strtoupper($currency),
            'proof_path' => $path,
            'admin_note' => $note ?? $request->admin_note,
            'paid_at' => now(),
            'handled_by' => $by->id,
        ])->save();

        return $request;
    }

    public function reject(PayoutRequest $request, User $by, string $reason): PayoutRequest
    {
        abort_unless($request->isOpen(), 422, 'This payout request is already closed.');

        return DB::transaction(function () use ($request, $by, $reason) {
            if ($request->status === PayoutRequest::STATUS_APPROVED) {
                $this->points->award(
                    $request->partner,
                    Transaction::TYPE_PAYOUT_REVERSAL,
                    $request->points,
                    "Payout request #{$request->id} rejected, points returned",
                    $request,
                    by: $by,
                );
            }

            $request->forceFill([
                'status' => PayoutRequest::STATUS_REJECTED,
                'admin_note' => $reason,
                'handled_by' => $by->id,
            ])->save();

            return $request;
        });
    }

    public function proofResponse(PayoutRequest $request): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);

        abort_unless($request->proof_path && $disk->exists($request->proof_path), 404);

        return $disk->response($request->proof_path, null, ['Cache-Control' => 'private, no-store']);
    }
}
