<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $createdUser,
        public User $createdBy
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New User Created',
            'message' => "A new user '{$this->createdUser->name}' was created by {$this->createdBy->name}",
            'type' => 'user_created',
            'user_id' => $this->createdUser->id,
            'user_name' => $this->createdUser->name,
            'user_email' => $this->createdUser->email,
            'created_by' => $this->createdBy->name,
            'action_url' => route('team-management.users.show', $this->createdUser->id),
            'icon' => 'user-plus',
        ];
    }
}
