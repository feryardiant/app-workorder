<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: int
{
    case Owner = 0;
    case Admin = 1;
    case Member = 2;

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isMember(): bool
    {
        return $this === self::Member;
    }

    public function canManageUsers(): bool
    {
        return $this->isOwner() || $this->isAdmin();
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Admin'),
            self::Member => __('Member'),
        };
    }
}
