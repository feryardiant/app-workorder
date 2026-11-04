<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRoleChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public User $actor,
        public Role $previousRole,
        public Role $newRole,
    ) {}
}
