<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Users\Edit;
use App\Livewire\Users\Index;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;

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

test('a user can be viewed', function () {
    actingAs(User::factory()->create());
    $user = User::factory()->create(['name' => 'Other User', 'email' => 'other@example.com']);

    get(route('users.show', $user))
        ->assertOk()
        ->assertSee('Other User')
        ->assertSee('other@example.com');
});

test('an admin can edit a user', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $user = User::factory()->create();

    Livewire::test(Edit::class, ['user' => $user])
        ->set('name', 'Updated Name')
        ->set('email', 'updated@example.com')
        ->call('update')
        ->assertHasNoErrors()
        ->assertRedirect(route('users.index'));

    expect($user->refresh())
        ->name->toBe('Updated Name')
        ->email->toBe('updated@example.com')
        ->email_verified_at->toBeNull();
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

test('a member cannot edit or remove users', function () {
    actingAs(User::factory()->withRole(Role::Member)->create());
    $user = User::factory()->create();

    Livewire::test(Edit::class, ['user' => $user])->assertForbidden();

    Livewire::test(Index::class)
        ->call('deleteUser', $user->id)
        ->assertForbidden();

    expect($user->fresh())->not->toBeNull();
});

test('an admin cannot edit or remove an owner', function () {
    actingAs(User::factory()->withRole(Role::Admin)->create());
    $owner = User::factory()->withRole(Role::Owner)->create();

    Livewire::test(Edit::class, ['user' => $owner])->assertForbidden();

    Livewire::test(Index::class)
        ->call('deleteUser', $owner->id)
        ->assertForbidden();

    expect($owner->fresh())->not->toBeNull();
});

test('the add user button is only visible to admins and owners', function () {
    actingAs(User::factory()->withRole(Role::Member)->create());
    get(route('users.index'))
        ->assertDontSee('Add User')
        ->assertDontSee('Edit User');

    actingAs(User::factory()->withRole(Role::Admin)->create(), 'web');
    get(route('users.index'))->assertSee('Add User');

    actingAs(User::factory()->withRole(Role::Owner)->create(), 'web');
    get(route('users.index'))->assertSee('Add User');
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
