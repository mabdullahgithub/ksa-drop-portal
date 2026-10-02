<?php

namespace App\Services\Shipping\Enums;

/**
 * Why a KSA Express delivery attempt failed. The rider app shows its own
 * English/Arabic wording for these codes; label() is what the tracking
 * history and the portal show.
 */
enum FailedAttemptReason: string
{
    case NO_ANSWER = 'no_answer';
    case REFUSED = 'refused';
    case WRONG_ADDRESS = 'wrong_address';
    case RESCHEDULE = 'reschedule';
    case NO_CASH = 'no_cash';
    case CLOSED = 'closed';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NO_ANSWER => 'Customer not answering',
            self::REFUSED => 'Customer refused the parcel',
            self::WRONG_ADDRESS => 'Wrong or incomplete address',
            self::RESCHEDULE => 'Customer asked to reschedule',
            self::NO_CASH => 'Customer did not have the cash',
            self::CLOSED => 'Location closed',
            self::OTHER => 'Other',
        };
    }
}
