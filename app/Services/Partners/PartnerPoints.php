<?php

namespace App\Services\Partners;

use App\Models\Client;
use App\Models\Partner;
use App\Models\PartnerPointTransaction as Transaction;
use App\Models\PartnerSetting;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Awards points and keeps each partner's balance and tier in step with the
 * ledger. Every award names the event it is for, so replaying the event
 * (a courier webhook sent twice, a job retried) awards nothing the second
 * time: the ledger's unique index refuses the duplicate and we move on.
 */
class PartnerPoints
{
    /**
     * Record points for an event. Returns the ledger line, or null when
     * this event was already awarded.
     */
    public function award(
        Partner $partner,
        string $type,
        int $points,
        ?string $description = null,
        ?object $reference = null,
        ?Client $client = null,
        ?User $by = null,
    ): ?Transaction {
        if ($points === 0) {
            return null;
        }

        try {
            return DB::transaction(function () use ($partner, $type, $points, $description, $reference, $client, $by) {
                $line = $partner->transactions()->create([
                    'type' => $type,
                    'points' => $points,
                    'reference_type' => $reference ? $reference::class : null,
                    'reference_id' => $reference?->getKey(),
                    'client_id' => $client?->id,
                    'description' => $description,
                    'created_by' => $by?->id,
                    'created_at' => now(),
                ]);

                $this->recalculate($partner);

                return $line;
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /** Re-sum the balance and work out the tier from the ledger and referrals. */
    public function recalculate(Partner $partner): void
    {
        $balance = (int) $partner->transactions()->sum('points');
        $tier = $this->tierFor($partner->activeReferralCount());

        $partner->forceFill(['points_balance' => $balance, 'tier' => $tier])->save();
    }

    public function approvalBonus(Partner $partner, ?User $by = null): ?Transaction
    {
        return $this->award(
            $partner,
            Transaction::TYPE_APPROVAL_BONUS,
            PartnerSetting::get('approval_bonus_points'),
            'Welcome bonus for joining the partner program',
            $partner,
            by: $by,
        );
    }

    /**
     * A delivered shipment of a referred client. The first one activates the
     * referral; each one in the earning window is worth the per-delivery
     * points times the partner's tier multiplier; a milestone pays once.
     *
     * @return list<Transaction>
     */
    public function forDelivery(Shipment $shipment): array
    {
        $client = $shipment->order?->client;
        $partner = $client?->partner;

        if (! $client || ! $partner || ! $partner->isApproved()) {
            return [];
        }

        $awarded = [];

        if (! $client->partner_activated_at) {
            $client->forceFill(['partner_activated_at' => $shipment->delivered_at ?? now()])->save();

            if ($line = $this->award(
                $partner,
                Transaction::TYPE_ACTIVATION,
                PartnerSetting::get('activation_points'),
                "{$client->company_name} had their first delivery",
                $client,
                $client,
            )) {
                $awarded[] = $line;
            }

            // One more active seller may lift the tier, which applies to this
            // delivery too (and the bonus may be set to 0, so not via award()).
            $this->recalculate($partner);
            $partner->refresh();
        }

        $window = (int) PartnerSetting::get('delivery_months');
        $activatedAt = $client->partner_activated_at;
        $deliveredAt = $shipment->delivered_at ?? now();

        if ($window > 0 && $activatedAt && $deliveredAt->lte($activatedAt->copy()->addMonths($window))) {
            $points = (int) round(PartnerSetting::get('delivery_points') * $this->multiplierFor($partner->tier));

            if ($line = $this->award(
                $partner,
                Transaction::TYPE_DELIVERY,
                $points,
                "Delivery for {$client->company_name} (" . $shipment->tracking_number . ')',
                $shipment,
                $client,
            )) {
                $awarded[] = $line;
            }
        }

        $threshold = (int) PartnerSetting::get('milestone_deliveries');

        if ($threshold > 0) {
            $delivered = Shipment::whereHas('order', fn ($q) => $q->where('client_id', $client->id))
                ->where('status', 'delivered')
                ->count();

            if ($delivered >= $threshold && $line = $this->award(
                $partner,
                Transaction::TYPE_MILESTONE,
                PartnerSetting::get('milestone_points'),
                "{$client->company_name} reached {$threshold} deliveries",
                $client,
                $client,
            )) {
                $awarded[] = $line;
            }
        }

        return $awarded;
    }

    public function adjust(Partner $partner, int $points, string $note, User $by): ?Transaction
    {
        // No reference: the unique index treats NULLs as distinct, so every
        // adjustment is its own line.
        return $this->award($partner, Transaction::TYPE_ADJUSTMENT, $points, $note, null, null, $by);
    }

    public function tierFor(int $activeClients): string
    {
        if ($activeClients >= (int) PartnerSetting::get('gold_min_active_clients')) {
            return 'gold';
        }

        if ($activeClients >= (int) PartnerSetting::get('silver_min_active_clients')) {
            return 'silver';
        }

        return 'bronze';
    }

    public function multiplierFor(string $tier): float
    {
        return match ($tier) {
            'gold' => (float) PartnerSetting::get('gold_multiplier'),
            'silver' => (float) PartnerSetting::get('silver_multiplier'),
            default => 1.0,
        };
    }
}
