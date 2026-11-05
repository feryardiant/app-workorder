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
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your role at :app has changed', ['app' => config('app.name')]))
            ->greeting(__('Hi :name', ['name' => $notifiable->name]))
            ->line($this->description())
            ->salutation(__('Best regards'));
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Your role has changed'),
            'description' => $this->description(),
        ];
    }

    private function description(): string
    {
        return __(':actor just changed your role to :role at :app.', [
            'actor' => $this->actor->name,
            'role' => $this->newRole->label(),
            'app' => config('app.name'),
        ]);
    }
}
