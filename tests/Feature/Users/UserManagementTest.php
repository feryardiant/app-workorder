<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Events\UserRoleChanged;
use App\Livewire\Users\Edit;
use App\Livewire\Users\Index;
use App\Livewire\Users\Show;
use App\Models\User;
use App\Notifications\UserRoleChangedNotification;
use App\Notifications\VerifyEmailForNewUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

test('guests are redirected from the users page', function () {
    get(route('users.index'))->assertRedirect(route('login'));
});

test('the users page lists all users', function () {
    actingAs(User::factory()->create(['name' => 'Current User']));
    User::factory()->create(['name' => 'Other User']);

    get(route('users.index'))
        ->assertOk()
        ->assertSee('Current User')
        ->assertSee('Other User');
});

test('the users table shows role labels and verification status', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    User::factory()->withRole(Role::Owner)->create(['name' => 'Verified Owner']);
    User::factory()->withRole(Role::Member)->unverified()->create(['name' => 'Unverified Member']);

    Livewire::test(Index::class)
        ->assertSee('Pemilik')
        ->assertSee('Terverifikasi')
        ->assertSee('Menunggu');
});

test('the users table is paginated', function () {
    actingAs(User::factory()->create());
    User::factory()->count(14)->create();

    Livewire::test(Index::class)
        ->assertViewHas('users', fn ($users) => $users->count() === 10 && $users->total() === 15)
        ->call('gotoPage', 2)
        ->assertViewHas('users', fn ($users) => $users->count() === 5);
});

test('a user can be viewed', function () {
    actingAs(User::factory()->create());
    $user = User::factory()->create(['name' => 'Other User', 'email' => 'other@example.com']);

    get(route('users.show', $user))
        ->assertOk()
        ->assertSee('Other User')
        ->assertSee('other@example.com');
});

test('the edit user modal opens from the users table', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->create();

    Livewire::test(Index::class)
        ->call('openEdit', $user->id)
        ->assertDispatched('edit-user');

    Livewire::test(Edit::class)
        ->dispatch('edit-user', userId: $user->id)
        ->assertSet('showModal', true)
        ->assertSet('name', $user->name)
        ->assertSet('email', $user->email);
});

test('an admin can edit a user', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->create();

    Livewire::test(Edit::class, ['user' => $user])
        ->set('name', 'Updated Name')
        ->set('email', 'updated@example.com')
        ->call('update')
        ->assertHasNoErrors()
        ->assertSet('showModal', false)
        ->assertDispatched('user-updated');

    expect($user->refresh())
        ->name->toBe('Updated Name')
        ->email->toBe('updated@example.com')
        ->email_verified_at->toBeNull();

    Notification::assertSentTo($user, VerifyEmailForNewUser::class);
});

test('an admin can edit a user handle', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->create();

    Livewire::test(Edit::class, ['user' => $user])
        ->set('handle', 'updatedhandle')
        ->call('update')
        ->assertHasNoErrors();

    expect($user->refresh()->handle)->toBe('updatedhandle');
});

test('editing a handle from the detail page redirects to the new handle', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->create();

    Livewire::test(Edit::class, ['user' => $user])
        ->set('handle', 'updatedhandle')
        ->call('update')
        ->assertRedirect(route('users.show', $user->refresh()));
});

test('editing a handle from the index stays on the index', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->create();

    Livewire::test(Edit::class)
        ->call('editUser', $user->id)
        ->set('handle', 'updatedhandle')
        ->call('update')
        ->assertNoRedirect();
});

test('an admin cannot assign another users handle', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    User::factory()->create(['handle' => 'takenhandle']);
    $user = User::factory()->create();

    Livewire::test(Edit::class, ['user' => $user])
        ->set('handle', 'takenhandle')
        ->call('update')
        ->assertHasErrors(['handle']);

    expect($user->refresh()->handle)->not->toBe('takenhandle');
});

test('a created user receives a valid generated handle', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Owner)->create());

    Livewire::test(Index::class)
        ->set('name', 'Budi Santoso')
        ->set('email', 'budi.santoso@example.com')
        ->set('role', Role::Member->value)
        ->call('createUser')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'budi.santoso@example.com')->firstOrFail();

    expect($user->handle)->toMatch('/^[a-z0-9]{4,15}$/');
});

test('generated handles stay unique when email local parts collide', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Owner)->create());
    User::factory()->create(['handle' => 'collision']);

    Livewire::test(Index::class)
        ->set('name', 'Collision User')
        ->set('email', 'collision@example.test')
        ->set('role', Role::Member->value)
        ->call('createUser')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'collision@example.test')->firstOrFail();

    expect($user->handle)->toMatch('/^[a-z0-9]{4,15}$/')
        ->and($user->handle)->not->toBe('collision');
});

test('user routes bind and generate by handle', function () {
    actingAs(User::factory()->create());
    $user = User::factory()->create();

    expect(route('users.show', $user))->toEndWith('/users/'.$user->handle);

    get(route('users.show', $user))
        ->assertOk()
        ->assertSee($user->name);

    get('/users/'.$user->id)->assertNotFound();
});

test('an admin can resend verification to an unverified user', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->unverified()->create();

    Livewire::test(Index::class)
        ->call('resendVerification', $user->id)
        ->assertHasNoErrors();

    Notification::assertSentTo($user, VerifyEmailForNewUser::class);
});

test('verification cannot be resent to a verified user', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->create();

    Livewire::test(Index::class)
        ->call('resendVerification', $user->id)
        ->assertStatus(422);

    Notification::assertNotSentTo($user, VerifyEmailForNewUser::class);
});

test('an owner can resend verification to a manageable user', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Owner)->create());
    $user = User::factory()->unverified()->create();

    Livewire::test(Index::class)
        ->call('resendVerification', $user->id)
        ->assertHasNoErrors();

    Notification::assertSentTo($user, VerifyEmailForNewUser::class);
});

test('an admin cannot resend verification to an owner', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Admin)->create());
    $owner = User::factory()->withRole(Role::Owner)->unverified()->create();

    Livewire::test(Index::class)
        ->call('resendVerification', $owner->id)
        ->assertForbidden();

    Notification::assertNotSentTo($owner, VerifyEmailForNewUser::class);
});

test('a member cannot resend verification', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Member)->create());
    $user = User::factory()->unverified()->create();

    Livewire::test(Index::class)
        ->call('resendVerification', $user->id)
        ->assertForbidden();

    Notification::assertNotSentTo($user, VerifyEmailForNewUser::class);
});

test('the resend verification action only appears for unverified users to managers', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());

    User::factory()->unverified()->create();
    User::factory()->withRole(Role::Owner)->unverified()->create();
    User::factory()->create();

    expect(substr_count(Livewire::test(Index::class)->html(), __('Resend verification')))->toBe(1);

    actingAs(User::factory()->withRole(Role::Member)->create(), 'web');

    expect(substr_count(Livewire::test(Index::class)->html(), __('Resend verification')))->toBe(0);
});

test('editing to a mixed-case duplicate email fails validation', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    Livewire::test(Edit::class, ['user' => $user])
        ->set('email', 'Taken@Example.com')
        ->call('update')
        ->assertHasErrors(['email']);
});

test('the user view shows the role and verification status', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->withRole(Role::Owner)->create([
        'name' => 'Owner User',
        'email' => 'owner@example.com',
    ]);

    get(route('users.show', $user))
        ->assertOk()
        ->assertSee('Owner User')
        ->assertSee('Pemilik')
        ->assertSee('owner@example.com')
        ->assertSee('Terverifikasi');
});

test('an admin can promote a member to admin', function () {
    Notification::fake();

    $actor = User::factory()->withRole(Role::Admin)->create();
    $member = User::factory()->withRole(Role::Member)->create();

    actingAs($actor);

    Livewire::test(Index::class)
        ->call('confirmPromote', $member->id)
        ->call('changeRoleConfirmed')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'success');

    expect($member->refresh()->role)->toBe(Role::Admin);

    Notification::assertSentTo($member, UserRoleChangedNotification::class, function ($notification) use ($member, $actor) {
        $message = $notification->toMail($member);

        return str_contains((string) $message->introLines[0], $actor->name)
            && str_contains((string) $message->introLines[0], 'mengubah peran');
    });
});

test('an admin can demote an admin to member', function () {
    Notification::fake();

    $actor = User::factory()->withRole(Role::Admin)->create();
    $admin = User::factory()->withRole(Role::Admin)->create();

    actingAs($actor);

    Livewire::test(Index::class)
        ->call('confirmDemote', $admin->id)
        ->call('changeRoleConfirmed')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'success');

    expect($admin->refresh()->role)->toBe(Role::Member);

    Notification::assertSentTo($admin, UserRoleChangedNotification::class);
});

test('a role change requires confirmation', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Admin)->create());
    $member = User::factory()->withRole(Role::Member)->create();

    Livewire::test(Index::class)
        ->call('confirmPromote', $member->id)
        ->assertSet('showRoleModal', true)
        ->assertSet('roleChangeTo', Role::Admin->value);

    expect($member->refresh()->role)->toBe(Role::Member);

    Livewire::test(Index::class)
        ->call('confirmPromote', $member->id)
        ->call('changeRoleConfirmed')
        ->assertSet('showRoleModal', false);

    expect($member->refresh()->role)->toBe(Role::Admin);

    Livewire::test(Index::class)
        ->call('confirmDemote', $member->id)
        ->call('changeRoleConfirmed');

    expect($member->refresh()->role)->toBe(Role::Member);
});

test('a role change dispatches the user role changed event', function () {
    Event::fake([UserRoleChanged::class]);

    actingAs(User::factory()->withRole(Role::Admin)->create());
    $member = User::factory()->withRole(Role::Member)->create();

    Livewire::test(Index::class)
        ->call('confirmPromote', $member->id)
        ->call('changeRoleConfirmed');

    Event::assertDispatched(
        UserRoleChanged::class,
        fn (UserRoleChanged $event) => $event->user->is($member)
            && $event->previousRole === Role::Member
            && $event->newRole === Role::Admin,
    );
});

test('a member cannot change roles', function () {
    actingAs(User::factory()->withRole(Role::Member)->create());
    $member = User::factory()->withRole(Role::Member)->create();

    Livewire::test(Index::class)
        ->call('confirmPromote', $member->id)
        ->call('changeRoleConfirmed')
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');

    expect($member->refresh()->role)->toBe(Role::Member);
});

test('role changes only apply to the expected current role', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());

    $admin = User::factory()->withRole(Role::Admin)->create();
    $member = User::factory()->withRole(Role::Member)->create();

    Livewire::test(Index::class)
        ->call('confirmPromote', $admin->id)
        ->call('changeRoleConfirmed')
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');

    Livewire::test(Index::class)
        ->call('confirmDemote', $member->id)
        ->call('changeRoleConfirmed')
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');

    expect($admin->refresh()->role)->toBe(Role::Admin)
        ->and($member->refresh()->role)->toBe(Role::Member);
});

test('an admin can promote a member from the user view', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Admin)->create());
    $member = User::factory()->withRole(Role::Member)->create();

    Livewire::test(Show::class, ['user' => $member])
        ->call('confirmPromote')
        ->assertSet('showRoleModal', true)
        ->call('changeRoleConfirmed')
        ->assertHasNoErrors();

    expect($member->refresh()->role)->toBe(Role::Admin);

    Notification::assertSentTo($member, UserRoleChangedNotification::class);
});

test('an admin can remove a user', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->create();

    Livewire::test(Index::class)
        ->call('deleteUser', $user->id)
        ->assertHasNoErrors();

    expect($user->fresh())->toBeNull();
});

test('the current user cannot be removed', function () {
    actingAs($current = User::factory()->withRole(Role::Admin)->create());

    Livewire::test(Index::class)
        ->call('deleteUser', $current->id)
        ->assertForbidden();

    expect($current->fresh())->not->toBeNull();
});

test('the delete confirmation modal removes the user', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->create();

    Livewire::test(Index::class)
        ->call('confirmDelete', $user->id)
        ->assertSet('showDeleteModal', true)
        ->assertSet('deletingUserId', $user->id)
        ->call('deleteConfirmed')
        ->assertHasNoErrors()
        ->assertSet('showDeleteModal', false)
        ->assertSet('deletingUserId', null);

    expect($user->fresh())->toBeNull();
});

test('a member cannot remove a user through the confirmation modal', function () {
    actingAs(User::factory()->withRole(Role::Member)->create());
    $user = User::factory()->create();

    Livewire::test(Index::class)
        ->call('confirmDelete', $user->id)
        ->call('deleteConfirmed')
        ->assertForbidden();

    expect($user->fresh())->not->toBeNull();
});

test('an admin can remove the selected users', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $users = User::factory()->count(3)->create();

    Livewire::test(Index::class)
        ->set('selected', $users->pluck('id')->all())
        ->call('confirmDeleteSelected')
        ->assertSet('showBatchDeleteModal', true)
        ->call('deleteSelectedConfirmed')
        ->assertHasNoErrors()
        ->assertSet('showBatchDeleteModal', false)
        ->assertSet('selected', [])
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'success'
            && ($params['slots']['text'] ?? null) === __(':count users deleted successfully.', ['count' => 3]));

    expect(User::query()->whereKey($users->pluck('id'))->count())->toBe(0);
});

test('the user table refreshes after bulk deletion', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $deleted = User::factory()->create(['name' => 'Deleted User']);
    $kept = User::factory()->create(['name' => 'Kept User']);

    Livewire::test(Index::class)
        ->set('selected', [$deleted->id])
        ->call('deleteSelected')
        ->assertHasNoErrors()
        ->assertDontSee('Deleted User')
        ->assertSee('Kept User');
});

test('deleting selected users reports a failure when nothing was deleted', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());

    Livewire::test(Index::class)
        ->set('selected', [999999])
        ->call('deleteSelected')
        ->assertHasNoErrors()
        ->assertSet('selected', [999999])
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');
});

test('bulk deletion ignores selected users outside the current page', function () {
    $actor = User::factory()->withRole(Role::Admin)->create();
    $olderMember = User::factory()->withRole(Role::Member)->create(['created_at' => now()->subDay()]);
    User::factory()->count(10)->withRole(Role::Member)->create(['created_at' => now()]);

    actingAs($actor);

    Livewire::test(Index::class)
        ->set('selected', [$olderMember->id])
        ->call('deleteSelected')
        ->assertHasNoErrors()
        ->assertSet('selected', [$olderMember->id])
        ->assertDispatched('toast-show', fn (string $name, array $params) => ($params['dataset']['variant'] ?? null) === 'danger');

    expect($olderMember->fresh())->not->toBeNull();
});

test('the batch action dropdown only appears when rows are selected', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->create();

    Livewire::test(Index::class)
        ->assertDontSee(__('Delete selected users'))
        ->set('selected', [$user->id])
        ->assertSee(__('Delete selected users'));
});

test('a member cannot remove selected users', function () {
    actingAs(User::factory()->withRole(Role::Member)->create());
    $users = User::factory()->count(2)->create();

    Livewire::test(Index::class)
        ->set('selected', $users->pluck('id')->all())
        ->call('deleteSelected')
        ->assertForbidden();

    expect(User::query()->whereKey($users->pluck('id'))->count())->toBe(2);
});

test('an admin cannot bulk delete themselves or an owner', function () {
    $admin = User::factory()->withRole(Role::Admin)->create();
    $owner = User::factory()->withRole(Role::Owner)->create();

    actingAs($admin);

    Livewire::test(Index::class)
        ->set('selected', [$admin->id])
        ->call('deleteSelected')
        ->assertForbidden();

    expect($admin->fresh())->not->toBeNull();

    Livewire::test(Index::class)
        ->set('selected', [$owner->id])
        ->call('deleteSelected')
        ->assertForbidden();

    expect($owner->fresh())->not->toBeNull();
});

test('the table exposes selectable rows to managers only', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    User::factory()->create(['name' => 'Selectable User']);

    Livewire::test(Index::class)
        ->assertSeeHtml('aria-label="Pilih semua pengguna"')
        ->assertSeeHtml('aria-label="Pilih Selectable User"');

    actingAs(User::factory()->withRole(Role::Member)->create(), 'web');

    Livewire::test(Index::class)
        ->assertDontSee('Pilih semua pengguna')
        ->assertDontSee('Pilih Selectable User');
});

test('the header selection state reflects the selectable rows', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $users = User::factory()->count(2)->create();

    Livewire::test(Index::class)
        ->assertSet('allSelected', false)
        ->assertSet('someSelected', false)
        ->set('selected', [$users->first()->id])
        ->assertSet('allSelected', false)
        ->assertSet('someSelected', true)
        ->set('selected', $users->pluck('id')->all())
        ->assertSet('allSelected', true)
        ->assertSet('someSelected', false);
});

test('the select all checkbox toggles the selectable rows', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $users = User::factory()->count(2)->create();

    Livewire::test(Index::class)
        ->call('toggleSelectAll')
        ->assertSet('selected', $users->pluck('id')->all())
        ->assertSet('allSelected', true)
        ->assertSet('someSelected', false)
        ->call('toggleSelectAll')
        ->assertSet('selected', [])
        ->assertSet('allSelected', false)
        ->assertSet('someSelected', false);
});

test('selecting a row is cleared when the page changes', function () {
    actingAs(User::factory()->create());
    User::factory()->count(14)->create();

    Livewire::test(Index::class)
        ->set('selected', [1])
        ->call('gotoPage', 2)
        ->assertSet('selected', []);
});

test('users are ordered by creation date newest first', function () {
    $actor = User::factory()->create(['created_at' => now()->subDays(5)]);
    $older = User::factory()->create(['created_at' => now()->subDay()]);
    $newer = User::factory()->create(['created_at' => now()]);

    actingAs($actor);

    Livewire::test(Index::class)
        ->assertSet('sortDirection', 'desc')
        ->assertViewHas('users', fn ($users) => $users->pluck('id')->all() === [$newer->id, $older->id, $actor->id]);
});

test('the created at column can be sorted', function () {
    $actor = User::factory()->create(['created_at' => now()->subDays(5)]);
    $older = User::factory()->create(['created_at' => now()->subDay()]);
    $newer = User::factory()->create(['created_at' => now()]);

    actingAs($actor);

    Livewire::test(Index::class)
        ->call('sortByCreatedAt')
        ->assertSet('sortDirection', 'asc')
        ->assertViewHas('users', fn ($users) => $users->pluck('id')->all() === [$actor->id, $older->id, $newer->id])
        ->call('sortByCreatedAt')
        ->assertSet('sortDirection', 'desc')
        ->assertViewHas('users', fn ($users) => $users->pluck('id')->all() === [$newer->id, $older->id, $actor->id]);
});

test('sorting returns to the first page', function () {
    actingAs(User::factory()->create());
    User::factory()->count(14)->create();

    Livewire::test(Index::class)
        ->call('gotoPage', 2)
        ->call('sortByCreatedAt')
        ->assertViewHas('users', fn ($users) => $users->currentPage() === 1);
});

test('the edit user modal is a floating right-side flyout with a bottom footer', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->create();

    Livewire::test(Edit::class, ['user' => $user])
        ->assertSeeHtml('data-flux-flyout')
        ->assertSeeHtml('ml-auto')
        ->assertSeeHtml('rounded-xl')
        ->assertSeeHtml('min-h-[calc(100dvh-5rem)]')
        ->assertSeeHtml('mt-auto');
});

test('a member cannot edit or remove users', function () {
    actingAs(User::factory()->withRole(Role::Member)->create());
    $user = User::factory()->create();

    Livewire::test(Edit::class)->call('editUser', $user->id)->assertForbidden();

    Livewire::test(Index::class)
        ->call('deleteUser', $user->id)
        ->assertForbidden();

    expect($user->fresh())->not->toBeNull();
});

test('an admin cannot edit or remove an owner', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $owner = User::factory()->withRole(Role::Owner)->create();

    Livewire::test(Edit::class)->call('editUser', $owner->id)->assertForbidden();

    Livewire::test(Index::class)
        ->call('deleteUser', $owner->id)
        ->assertForbidden();

    expect($owner->fresh())->not->toBeNull();
});

test('the add user button is only visible to admins and owners', function () {
    $addButton = 'wire:click="$set(\'showCreateModal\', true)"';

    actingAs(User::factory()->withRole(Role::Member)->create());
    get(route('users.index'))->assertDontSee($addButton, false);

    actingAs(User::factory()->withRole(Role::Admin)->create(), 'web');
    get(route('users.index'))->assertSee($addButton, false);

    actingAs(User::factory()->withRole(Role::Owner)->create(), 'web');
    get(route('users.index'))->assertSee($addButton, false);
});

test('an admin can create a user and a verification email is sent', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Admin)->create());

    Livewire::test(Index::class)
        ->set('name', 'New User')
        ->set('email', 'new@example.com')
        ->set('role', Role::Member->value)
        ->call('createUser')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();

    expect($user)
        ->name->toBe('New User')
        ->role->toBe(Role::Member)
        ->email_verified_at->toBeNull();

    Notification::assertSentTo($user, VerifyEmailForNewUser::class);
});

test('created emails are stored in lowercase', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Admin)->create());

    Livewire::test(Index::class)
        ->set('name', 'Mixed Case')
        ->set('email', 'Mixed.Case@Example.COM')
        ->set('role', Role::Member->value)
        ->call('createUser')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'mixed.case@example.com')->exists())->toBeTrue();
});

test('a mixed-case duplicate email fails validation', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Admin)->create());
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(Index::class)
        ->set('name', 'Duplicate')
        ->set('email', 'Taken@Example.com')
        ->set('role', Role::Member->value)
        ->call('createUser')
        ->assertHasErrors(['email']);

    expect(User::query()->where('email', 'taken@example.com')->count())->toBe(1);
});

test('the created password uses a 16 character random string', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Admin)->create());

    $lengths = [];

    Str::createRandomStringsUsing(function (int $length) use (&$lengths) {
        $lengths[] = $length;

        return 'known-password';
    });

    try {
        Livewire::test(Index::class)
            ->set('name', 'Known Password')
            ->set('email', 'known@example.com')
            ->set('role', Role::Member->value)
            ->call('createUser')
            ->assertHasNoErrors();
    } finally {
        Str::createRandomStringsNormally();
    }

    $user = User::query()->where('email', 'known@example.com')->firstOrFail();

    expect($lengths)->toContain(16)
        ->and(Hash::check('known-password', $user->password))->toBeTrue();
});

test('a member cannot create a user', function () {
    actingAs(User::factory()->withRole(Role::Member)->create());

    Livewire::test(Index::class)
        ->set('name', 'New User')
        ->set('email', 'new@example.com')
        ->set('role', Role::Member->value)
        ->call('createUser')
        ->assertForbidden();

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
});

test('an admin cannot create an owner', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());

    Livewire::test(Index::class)
        ->set('name', 'New Owner')
        ->set('email', 'owner@example.com')
        ->set('role', Role::Owner->value)
        ->call('createUser')
        ->assertForbidden();

    expect(User::query()->where('email', 'owner@example.com')->exists())->toBeFalse();
});

test('an invited user can verify their email, set a password, and reach the dashboard', function () {
    Notification::fake();

    actingAs(User::factory()->withRole(Role::Admin)->create());

    Livewire::test(Index::class)
        ->set('name', 'Invited User')
        ->set('email', 'invited@example.com')
        ->set('role', Role::Member->value)
        ->call('createUser')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'invited@example.com')->firstOrFail();

    Auth::logout();

    Notification::assertSentTo($user, VerifyEmailForNewUser::class, function ($notification) use ($user) {
        $verificationUrl = $notification->toMail($user)->actionUrl;

        $response = $this->get($verificationUrl)->assertRedirect();

        $location = (string) $response->headers->get('Location');
        $token = Str::afterLast((string) parse_url($location, PHP_URL_PATH), '/');

        $this->get($location)->assertOk();

        post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
            ->and(Auth::check())->toBeTrue();

        get(route('dashboard'))->assertOk();

        return true;
    });
});

test('a new user email cannot be verified with an invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify.new',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

describe('database session cleanup on user deletion', function () {
    beforeEach(function () {
        /** @var TestCase $this */
        $this->sessionConnection = config('database.default');
        $this->sessionTable = 'test_sessions';

        config([
            'session.driver' => 'database',
            'session.connection' => $this->sessionConnection,
            'session.table' => $this->sessionTable,
        ]);

        Schema::connection($this->sessionConnection)->dropIfExists($this->sessionTable);

        (require database_path('migrations/0001_01_01_000002_create_sessions_table.php'))->up();
    });

    afterEach(function () {
        /** @var TestCase $this */
        Schema::connection($this->sessionConnection)->dropIfExists($this->sessionTable);
    });

    test('individual user deletion removes their database sessions', function () {
        actingAs(User::factory()->withRole(Role::Admin)->create());
        $member = User::factory()->withRole(Role::Member)->create();

        createTestDatabaseSessionFor($member);

        Livewire::test(Index::class)->call('deleteUser', $member->id);

        /** @var TestCase $this */
        expect($member->fresh())->toBeNull()
            ->and(DB::connection($this->sessionConnection)->table($this->sessionTable)->where('user_id', $member->id)->exists())->toBeFalse();
    });

    test('batch user deletion removes database sessions for every deleted user', function () {
        actingAs(User::factory()->withRole(Role::Admin)->create());
        $members = User::factory()->count(2)->withRole(Role::Member)->create();

        foreach ($members as $member) {
            createTestDatabaseSessionFor($member);
        }

        Livewire::test(Index::class)
            ->set('selected', $members->pluck('id')->all())
            ->call('deleteSelected');

        /** @var TestCase $this */
        expect(User::query()->whereKey($members->pluck('id'))->count())->toBe(0)
            ->and(DB::connection($this->sessionConnection)->table($this->sessionTable)->whereIn('user_id', $members->pluck('id'))->count())->toBe(0);
    });
});

test('an authenticated user sees instructions for a used invitation link', function () {
    $invited = User::factory()->create();
    actingAs(User::factory()->create());

    $url = URL::temporarySignedRoute(
        'verification.verify.new',
        now()->addMinutes(60),
        ['id' => $invited->id, 'hash' => sha1($invited->email)],
    );

    $status = __('This invitation has already been used. Log out, then use the "Forgot your password?" link to set your password.');

    get($url)
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('status', $status);

    get(route('dashboard'))
        ->assertOk()
        ->assertSee($status);
});

test('a verified user cannot get a password reset token from a verification link', function () {
    $user = User::factory()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify.new',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    get($url)
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('This invitation has already been used. Use the "Forgot your password?" link to set your password.'));

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});

function createTestDatabaseSessionFor(User $user): void
{
    DB::connection(config('database.default'))->table(config('session.table'))->insert([
        'id' => Str::random(40),
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->getTimestamp(),
    ]);
}
