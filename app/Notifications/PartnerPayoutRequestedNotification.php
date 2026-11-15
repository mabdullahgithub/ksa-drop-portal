<?php

namespace App\Notifications;

use App\Models\PartnerPayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PartnerPayoutRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(private PartnerPayoutRequest $payout) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $partner = $this->payout->partner;

        return [
            'title' => 'Partner payout requested',
            'message' => "{$partner->display_name} ({$partner->code}) asked to redeem " . number_format($this->payout->points) . ' points.',
            'icon' => 'banknote',
            'action_url' => '/partners-admin?tab=payouts',
        ];
    }
}
