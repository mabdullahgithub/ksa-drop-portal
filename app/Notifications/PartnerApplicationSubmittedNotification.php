<?php

namespace App\Notifications;

use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PartnerApplicationSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(private Partner $partner) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New partner application',
            'message' => "{$this->partner->display_name} applied as " . ucfirst($this->partner->type) . " partner (code {$this->partner->code}).",
            'icon' => 'handshake',
            'action_url' => "/partners-admin?tab=applications&partner={$this->partner->id}",
        ];
    }
}
