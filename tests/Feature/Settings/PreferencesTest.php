<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Livewire\Settings\Preferences;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patchJson;

test('preferences page is displayed', function () {
    actingAs($user = User::factory()->create(['locale' => Locale::English, 'timezone' => 'Asia/Jakarta']));

    get(route('preferences.edit'))
        ->assertOk()
        ->assertSee('Preferences')
        ->assertSee('Bahasa Indonesia')
        ->assertSee('Asia/Jakarta');

    Livewire::test(Preferences::class)
        ->assertSet('locale', Locale::English->value)
        ->assertSet('timezone', 'Asia/Jakarta');
});

test('guests are redirected from the preferences page', function () {
    get(route('preferences.edit'))->assertRedirect(route('login'));
});

test('unverified users are redirected from the preferences page', function () {
    actingAs(User::factory()->unverified()->create());

    get(route('preferences.edit'))->assertRedirect(route('verification.notice'));
});

test('locale can be updated without changing the timezone', function () {
    actingAs($user = User::factory()->create([
        'locale' => Locale::Indonesian,
        'timezone' => 'Asia/Jakarta',
    ]));

    patchJson(route('preferences.update'), ['locale' => Locale::English->value])
        ->assertOk()
        ->assertJsonPath('locale', Locale::English->value)
        ->assertJsonPath('timezone', 'Asia/Jakarta');

    expect($user->refresh())
        ->locale->toBe(Locale::English)
        ->timezone->toBe('Asia/Jakarta');
});

test('timezone can be updated without changing the locale', function () {
    actingAs($user = User::factory()->create([
        'locale' => Locale::English,
        'timezone' => null,
    ]));

    patchJson(route('preferences.update'), ['timezone' => 'America/New_York'])
        ->assertOk()
        ->assertJsonPath('locale', Locale::English->value)
        ->assertJsonPath('timezone', 'America/New_York');

    expect($user->refresh())
        ->locale->toBe(Locale::English)
        ->timezone->toBe('America/New_York');
});

test('invalid preferences are rejected', function () {
    actingAs($user = User::factory()->create([
        'locale' => Locale::Indonesian,
        'timezone' => 'Asia/Jakarta',
    ]));

    patchJson(route('preferences.update'), ['locale' => 'fr'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('locale');

    patchJson(route('preferences.update'), ['timezone' => 'Mars/Phobos'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('timezone');

    expect($user->refresh())
        ->locale->toBe(Locale::Indonesian)
        ->timezone->toBe('Asia/Jakarta');
});

test('an empty preferences payload is rejected', function () {
    actingAs(User::factory()->create());

    patchJson(route('preferences.update'), [])
        ->assertUnprocessable();
});

test('guests cannot update preferences', function () {
    patchJson(route('preferences.update'), ['locale' => Locale::English->value])
        ->assertUnauthorized();
});

test('unverified users cannot update preferences', function () {
    actingAs(User::factory()->unverified()->create());

    patchJson(route('preferences.update'), ['locale' => Locale::English->value])
        ->assertForbidden();
});
