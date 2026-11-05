<?php

declare(strict_types=1);

use App\Actions\Users\ChangeUserRole;
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
        ->assertSee(__('Your role at :app has changed', ['app' => config('app.name')]))
        ->assertSee(__('Unread'))
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
        ->toContain('notifications-updated.window')
        ->toContain('bg-red-500');
});

test('opening a notification shows its content in a modal', function () {
    $user = User::factory()->create();
    $actor = User::factory()->create();

    actingAs($user);

    $user->notify(new UserRoleChangedNotification($actor, Role::Admin));

    $notification = $user->notifications()->firstOrFail();

    Livewire::test(Index::class)
        ->call('openNotification', $notification->id)
        ->assertSet('showNotificationModal', true)
        ->assertDispatched('notifications-updated', count: 0)
        ->assertSee(__('Your role at :app has changed', ['app' => config('app.name')]))
        ->assertSee($actor->name);

    expect($notification->refresh()->read_at)->not->toBeNull();
});

test('notifications can be selected individually or all at once', function () {
    $user = User::factory()->create();
    $actor = User::factory()->create();

    actingAs($user);

    $user->notify(new UserRoleChangedNotification($actor, Role::Admin));
    $user->notify(new UserRoleChangedNotification($actor, Role::Member));

    $ids = $user->notifications()->pluck('id')->all();

    $component = Livewire::test(Index::class)
        ->call('toggleSelected', $ids[0])
        ->assertSet('selectedNotifications', [$ids[0]]);

    expect($component->html())->toContain('aria-checked="mixed"');

    $component
        ->call('toggleSelected', $ids[0])
        ->assertSet('selectedNotifications', [])
        ->call('toggleSelectAll')
        ->assertSet('selectedNotifications', fn (array $selected): bool => collect($selected)->sort()->values()->all() === collect($ids)->sort()->values()->all());

    expect($component->html())->not->toContain('aria-checked="mixed"');
});

test('selected notifications can be marked as read or unread', function () {
    $user = User::factory()->create();
    $actor = User::factory()->create();

    actingAs($user);

    $user->notify(new UserRoleChangedNotification($actor, Role::Admin));
    $user->notify(new UserRoleChangedNotification($actor, Role::Member));

    $ids = $user->notifications()->pluck('id')->all();

    Livewire::test(Index::class)
        ->set('selectedNotifications', $ids)
        ->call('markSelectedAsRead')
        ->assertDispatched('notifications-updated', count: 0)
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['slots']['text'] ?? null) === __('Successfully mark :count notification as read', ['count' => 2]));

    expect($user->notifications()->whereNull('read_at')->count())->toBe(0);

    Livewire::test(Index::class)
        ->set('selectedNotifications', $ids)
        ->call('markSelectedAsUnread')
        ->assertDispatched('notifications-updated', count: 2)
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['slots']['text'] ?? null) === __('Successfully mark :count notification as unread', ['count' => 2]));

    expect($user->notifications()->whereNotNull('read_at')->count())->toBe(0);
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

test('a role change stores exactly one notification', function () {
    $actor = User::factory()->withRole(Role::Admin)->create();
    $member = User::factory()->withRole(Role::Member)->create();

    app(ChangeUserRole::class)->promote($member, $actor);

    expect($member->notifications()->count())->toBe(1);
});

test('role change notifications are stored in the database', function () {
    $actor = User::factory()->withRole(Role::Admin)->create();
    $user = User::factory()->create();

    $user->notify(new UserRoleChangedNotification($actor, Role::Admin));

    $notification = $user->notifications()->firstOrFail();

    expect($notification->type)->toBe(UserRoleChangedNotification::class)
        ->and($notification->data['title'])->toBe(__('Your role at :app has changed', ['app' => config('app.name')]))
        ->and($notification->data['description'])->toContain($actor->name);
});

test('email verification notifications are stored in the database', function () {
    $user = User::factory()->unverified()->create();

    $user->notify(new VerifyEmailForNewUser);

    $notification = $user->notifications()->firstOrFail();

    expect($notification->type)->toBe(VerifyEmailForNewUser::class)
        ->and($notification->data['title'])->toBe(__('Verify your email address'))
        ->and($notification->data['greeting'])->toBe(__('Hello!'))
        ->and($notification->data['description'])->toBe(__('Please verify your email address by clicking on the link we just emailed to you.'))
        ->and($notification->data)->not->toHaveKey('action')
        ->and($notification->data['salutation'])->toBe(__('Regards, :app', ['app' => config('app.name')]));
});

test('a user notifications are deleted with the account', function () {
    $user = User::factory()->create();

    $user->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Admin));

    expect($user->notifications()->count())->toBe(1);

    $user->delete();

    expect($user->notifications()->count())->toBe(0);
});
