<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\UserRoleChangedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('the locale middleware uses the authenticated users locale', function () {
    actingAs(User::factory()->create(['locale' => Locale::English]));

    get(route('profile.edit'))->assertOk();

    expect(app()->getLocale())->toBe(Locale::English->value)
        ->and(Date::getLocale())->toBe(Locale::English->value);
});

test('the locale middleware falls back to the app default locale', function () {
    actingAs(User::factory()->create(['locale' => Locale::English]));

    get(route('profile.edit'))->assertOk();

    actingAs(User::factory()->create(['locale' => null]), 'web');

    get(route('profile.edit'))->assertOk();

    expect(app()->getLocale())->toBe((string) config('app.default_locale'))
        ->and(Date::getLocale())->toBe((string) config('app.default_locale'));
});

test('notifications use the recipients preferred locale', function () {
    Mail::fake();

    $recipient = User::factory()->create(['locale' => Locale::English]);
    $actor = User::factory()->create();

    $recipient->notify(new UserRoleChangedNotification($actor, Role::Admin));

    $data = $recipient->notifications()->firstOrFail()->data;

    expect($data['title'])->toBe('Your role at '.config('app.name').' has changed')
        ->and($data['description'])->toContain('changed your role');
});

test('preferred locale is null when the user has no locale preference', function () {
    expect(User::factory()->create(['locale' => null])->preferredLocale())->toBeNull();
});

test('dates are displayed in the users timezone while stored in UTC', function () {
    Mail::fake();

    $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
    $actor = User::factory()->create();

    actingAs($user);

    $user->notify(new UserRoleChangedNotification($actor, Role::Admin));

    $notification = $user->notifications()->firstOrFail();
    $notification->forceFill(['created_at' => CarbonImmutable::parse('2026-01-01 18:00:00', 'UTC')])->save();

    get(route('notifications.index'))
        ->assertOk()
        ->assertSee('02 Jan 2026');

    expect(config('app.timezone'))->toBe('UTC')
        ->and(DB::table('notifications')->where('id', $notification->id)->value('created_at'))
        ->toContain('2026-01-01 18:00:00');
});

test('dates fall back to UTC when the user has no timezone preference', function () {
    Mail::fake();

    $user = User::factory()->create(['timezone' => null]);
    $actor = User::factory()->create();

    actingAs($user);

    $user->notify(new UserRoleChangedNotification($actor, Role::Admin));

    $notification = $user->notifications()->firstOrFail();
    $notification->forceFill(['created_at' => CarbonImmutable::parse('2026-01-01 18:00:00', 'UTC')])->save();

    get(route('notifications.index'))
        ->assertOk()
        ->assertSee('01 Jan 2026');

    expect(config('app.timezone'))->toBe('UTC');
});

test('local time renders a placeholder for a null date', function () {
    actingAs(User::factory()->create());

    expect(Blade::render('<x-local-time :date="$date" />', ['date' => null]))
        ->toContain('—');
});
