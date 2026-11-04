<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserRoleChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $actor,
        public Role $newRole,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject(__('Your role at :app has changed', ['app' => $app]))
            ->greeting(__('Hi :name', ['name' => $notifiable->name]))
            ->line(__(':actor just changed your role to :role at :app.', [
                'actor' => $this->actor->name,
                'role' => $this->newRole->label(),
                'app' => $app,
            ]))
            ->salutation(__('Best regards'));
    }
}
