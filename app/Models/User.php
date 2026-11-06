<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property Role $role
 * @property string $email
 * @property Locale|null $locale
 * @property string|null $timezone
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'handle', 'email', 'password', 'role', 'locale', 'timezone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[RouteKey('handle')]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->handle)) {
                $user->handle = static::generateHandle($user->email);
            }
        });

        static::deleting(function (User $user): void {
            $user->notifications()->delete();
        });
    }

    /**
     * Generate a valid, unique handle from the given seed.
     */
    public static function generateHandle(string $seed): string
    {
        $base = Str::substr(
            Str::lower(preg_replace('/[^a-z0-9]+/i', '', Str::before($seed, '@')) ?? ''),
            0,
            12,
        );

        if (Str::length($base) < 4) {
            $base .= Str::lower(Str::random(4 - Str::length($base)));
        }

        $handle = $base;

        while (static::query()->where('handle', $handle)->exists()) {
            $handle = Str::substr($base, 0, 10).Str::lower(Str::random(5));
        }

        return $handle;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'locale' => Locale::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's preferred locale for notifications and mail.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale?->value;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
