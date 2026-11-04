<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Events\UserRoleChanged;
use App\Models\User;

class ChangeUserRole
{
    public function promote(User $user, User $actor): bool
    {
        return $this->change($user, $actor, Role::Member, Role::Admin);
    }

    public function demote(User $user, User $actor): bool
    {
        return $this->change($user, $actor, Role::Admin, Role::Member);
    }

    private function change(User $user, User $actor, Role $expected, Role $newRole): bool
    {
        if ($user->is($actor) || ! $actor->role->canManageUsers() || $user->role !== $expected) {
            return false;
        }

        $previousRole = $user->role;

        $user->update(['role' => $newRole]);

        event(new UserRoleChanged($user, $actor, $previousRole, $newRole));

        return true;
    }
}
