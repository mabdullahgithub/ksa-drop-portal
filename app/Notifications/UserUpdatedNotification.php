<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $updatedUser,
        public User $updatedBy,
        public array $changes = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'User Updated',
            'message' => "User '{$this->updatedUser->name}' was updated by {$this->updatedBy->name}",
            'type' => 'user_updated',
            'user_id' => $this->updatedUser->id,
            'user_name' => $this->updatedUser->name,
            'updated_by' => $this->updatedBy->name,
            'changes' => $this->changes,
            'action_url' => route('team-management.users'),
            'icon' => 'user-check',
        ];
    }
}
