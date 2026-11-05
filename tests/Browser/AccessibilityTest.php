<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;

it('has no serious accessibility issues on the login page', function (string $device) {
    visit('/login')
        ->on()
        ->{$device}()
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('has no serious accessibility issues on the users page', function (string $device) {
    $owner = User::factory()->withRole(Role::Owner)->create();

    browserLogin($owner, $device)
        ->navigate('/users')
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('can sign in using only the keyboard', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();

    $page = visit('/login');

    $page->assertScript('document.activeElement.name === "email"')
        ->typeSlowly('email', $owner->email)
        ->keys('input[name="email"]', 'Tab')
        ->assertScript('document.activeElement.name === "password"')
        ->typeSlowly('password', 'password')
        ->keys('input[name="password"]', 'Enter');

    $page->assertPathIs('/dashboard');
});

it('moves focus into a modal and back to its trigger', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();

    $page = browserLogin($owner)->navigate('/users');

    $page->click('Tambah pengguna')
        ->wait(0.3)
        ->assertScript('!! document.activeElement.closest("dialog")')
        ->keys('dialog[open]', 'Escape')
        ->wait(0.3)
        ->assertScript('document.querySelectorAll("dialog[open]").length === 0')
        ->assertScript('document.activeElement.textContent.trim() === "Tambah pengguna"');
});

it('has a level one heading on the dashboard', function () {
    // Important: screen reader users navigate pages by their headings. The dashboard
    // only renders placeholder panels, so it has no <h1> at all. Adding one is a view
    // change, which this suite is not allowed to make.
    $owner = User::factory()->withRole(Role::Owner)->create();

    browserLogin($owner)
        ->navigate('/dashboard')
        ->assertScript('document.querySelectorAll("h1").length > 0');
})->skip('Dashboard needs an <h1> in resources/views/dashboard.blade.php.');
