<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserRoleChanged;
use App\Notifications\UserRoleChangedNotification;

class SendUserRoleChangedNotification
{
    public function handle(UserRoleChanged $event): void
    {
        $event->user->notify(new UserRoleChangedNotification(
            $event->actor,
            $event->newRole,
        ));
    }
}
