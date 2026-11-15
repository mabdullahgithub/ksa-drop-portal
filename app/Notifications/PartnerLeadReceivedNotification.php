<?php

namespace App\Notifications;

use App\Models\PartnerLead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PartnerLeadReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(private PartnerLead $lead) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $partner = $this->lead->partner;

        return [
            'title' => 'New referral lead',
            'message' => "{$this->lead->name} ({$this->lead->phone}) wants an account, referred by {$partner->display_name} ({$partner->code}).",
            'icon' => 'user-plus',
            'action_url' => '/partners-admin?tab=leads',
        ];
    }
}
