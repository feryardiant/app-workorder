<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use App\Notifications\UserRoleChangedNotification;

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

it('has no serious accessibility issues on the notifications page', function (string $device) {
    $owner = User::factory()->withRole(Role::Owner)->create();
    $owner->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Admin));
    $owner->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Member));
    $owner->notifications()->latest()->first()->markAsRead();

    browserLogin($owner, $device)
        ->navigate('/notifications')
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
})->with(['desktop', 'mobile']);

it('has no serious accessibility issues on the user detail page', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    $member = User::factory()->create();

    browserLogin($owner)
        ->navigate("/users/{$member->handle}")
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
});

it('exposes the users table sort direction', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();

    browserLogin($owner)
        ->navigate('/users')
        ->assertScript('document.querySelector("[aria-sort]")?.getAttribute("aria-sort") === "descending"')
        ->click('Terdaftar pada')
        ->wait(0.4)
        ->assertScript('document.querySelector("[aria-sort]")?.getAttribute("aria-sort") === "ascending"');
});

it('focuses the notification flyout and returns focus to its trigger', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    $owner->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Admin));

    $page = browserLogin($owner)->navigate('/notifications');

    $page->click('[data-flux-row] button[wire\:click\.stop]:not([role="checkbox"])')
        ->wait(0.5)
        ->assertScript('(() => {
            const dialog = document.querySelector("dialog[open]");

            return !!dialog
                && !!document.getElementById(dialog.getAttribute("aria-labelledby"))
                && document.activeElement.closest("dialog") === dialog;
        })()')
        ->keys('dialog[open]', 'Escape')
        ->wait(0.5)
        ->assertScript('document.querySelectorAll("dialog[open]").length === 0')
        ->assertScript('document.activeElement.getAttribute("wire:click.stop") !== null');
});

it('exposes tri-state user selection to assistive technology', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    $first = User::factory()->create();
    User::factory()->create();

    $page = browserLogin($owner)->navigate('/users');

    $page->click('input[aria-label="Pilih semua pengguna"]')
        ->wait(0.4)
        ->assertScript('[...document.querySelectorAll("ui-checkbox[value]")].every((el) => el.getAttribute("aria-checked") === "true")')
        ->assertScript('[...document.querySelectorAll("ui-checkbox[value]")].every((el) => (el.getAttribute("aria-label") || "").startsWith("Pilih "))')
        ->click('ui-checkbox[value="'.$first->id.'"]')
        ->wait(0.4)
        ->assertScript('document.querySelector("input[aria-label=\"Pilih semua pengguna\"]").indeterminate === true');
});

it('announces unread notification counts to screen readers', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    $actor = User::factory()->create();
    $owner->notify(new UserRoleChangedNotification($actor, Role::Admin));
    $owner->notify(new UserRoleChangedNotification($actor, Role::Member));

    $page = browserLogin($owner)->navigate('/notifications');

    $page->assertScript('[...document.querySelectorAll("[role=status]")].some((el) => el.textContent.includes("2"))')
        ->click('button[aria-label="Pilih semua notifikasi"]')
        ->wait(0.4)
        ->click('button[aria-label="Tandai sudah dibaca"]')
        ->wait(0.8)
        ->assertScript('[...document.querySelectorAll("[role=status]")].some((el) => el.textContent.includes("Tidak ada notifikasi belum dibaca"))');
});

it('exposes a level one heading on the dashboard', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();

    browserLogin($owner)
        ->navigate('/dashboard')
        ->assertScript('(() => {
            const headings = [...document.querySelectorAll("h1")];

            return headings.length === 1 && headings[0].textContent.trim() === "Dasbor";
        })()');
});
