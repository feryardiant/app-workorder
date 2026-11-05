<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Notifications\Index;
use App\Models\User;
use App\Notifications\UserRoleChangedNotification;
use App\Notifications\VerifyEmailForNewUser;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected from the notifications page', function () {
    get(route('notifications.index'))->assertRedirect(route('login'));
});

test('the notifications page lists only the current user notifications', function () {
    $user = User::factory()->create();
    $actor = User::factory()->create();

    $user->notify(new UserRoleChangedNotification($actor, Role::Admin));
    User::factory()->create()->notify(new UserRoleChangedNotification($actor, Role::Admin));

    actingAs($user);

    get(route('notifications.index'))
        ->assertOk()
        ->assertSee(__('Your role has changed'))
        ->assertSee($actor->name);

    Livewire::test(Index::class)
        ->assertViewHas('notifications', fn ($notifications) => $notifications->total() === 1);
});

test('the user menu links to the notifications page and shows the unread count', function () {
    $user = User::factory()->create();

    actingAs($user);

    $user->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Admin));

    $html = get(route('dashboard'))->assertOk()->assertSee(__('Notification'))->getContent();

    expect($html)->toContain(route('notifications.index'))
        ->toContain('top-0 left-0 bg-red-500');
});

test('an unread notification is marked as read when opened', function () {
    $user = User::factory()->create();

    actingAs($user);

    $user->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Admin));

    $notification = $user->notifications()->firstOrFail();

    Livewire::test(Index::class)
        ->call('markAsRead', $notification->id)
        ->assertRedirect(route('notifications.index'));

    expect($notification->refresh()->read_at)->not->toBeNull();
});

test('notifications can be sorted by received date', function () {
    $user = User::factory()->create();
    $actor = User::factory()->create();

    $user->notify(new UserRoleChangedNotification($actor, Role::Admin));
    $older = $user->notifications()->firstOrFail();
    $older->forceFill(['created_at' => now()->subDay()])->save();

    $user->notify(new UserRoleChangedNotification($actor, Role::Member));
    $newer = $user->notifications()->whereKeyNot($older->id)->firstOrFail();

    actingAs($user);

    $component = Livewire::test(Index::class);

    expect(strpos($component->html(), $newer->id))->toBeLessThan(strpos($component->html(), $older->id));

    $component->call('sortByReceivedAt');

    expect(strpos($component->html(), $older->id))->toBeLessThan(strpos($component->html(), $newer->id));
});

test('role change notifications are stored in the database', function () {
    $actor = User::factory()->withRole(Role::Admin)->create();
    $user = User::factory()->create();

    $user->notify(new UserRoleChangedNotification($actor, Role::Admin));

    $notification = $user->notifications()->firstOrFail();

    expect($notification->type)->toBe(UserRoleChangedNotification::class)
        ->and($notification->data['title'])->toBe(__('Your role has changed'))
        ->and($notification->data['description'])->toContain($actor->name);
});

test('email verification notifications are stored in the database', function () {
    $user = User::factory()->unverified()->create();

    $user->notify(new VerifyEmailForNewUser);

    $notification = $user->notifications()->firstOrFail();

    expect($notification->type)->toBe(VerifyEmailForNewUser::class)
        ->and($notification->data['title'])->toBe(__('Email verification'));
});
