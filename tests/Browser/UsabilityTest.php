<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use App\Notifications\UserRoleChangedNotification;

it('keeps the login form usable on mobile', function () {
    $member = User::factory()->withRole(Role::Member)->create();

    $page = visit('/login')->on()->mobile();

    $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth');

    $page->fill('email', $member->email)
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs('/dashboard');
});

it('opens the mobile navigation', function () {
    $member = User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($member, 'mobile')->assertPathIs('/dashboard');

    $page->click('[data-flux-sidebar-toggle]')
        ->click('Pengguna')
        ->assertPathIs('/users');
});

it('keeps the users page within the mobile viewport', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();

    browserLogin($owner, 'mobile')
        ->navigate('/users')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
});

it('reaches notifications from the mobile user menu', function () {
    $member = User::factory()->create();

    browserLogin($member, 'mobile')
        ->navigate('/dashboard')
        ->click('header button[aria-haspopup]')
        ->wait(0.3)
        ->click('header a[href$="/notifications"]')
        ->assertPathIs('/notifications');
});

it('keeps the notifications page within the mobile viewport', function () {
    $owner = User::factory()->create();
    $owner->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Admin));

    browserLogin($owner, 'mobile')
        ->navigate('/notifications')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
});

it('exposes user actions in the mobile overflow menu', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($owner, 'mobile')->navigate('/users');

    $page->click('ui-checkbox[value]')
        ->wait(0.3)
        ->click('button[aria-label="Tindakan"]')
        ->wait(0.3)
        ->assertSee('Tambah pengguna')
        ->assertSee('Hapus pengguna terpilih');
});

it('keeps the edit flyout actions within the mobile viewport', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    $member = User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($owner, 'mobile')->navigate('/users');

    $page->click("[aria-label=\"Tindakan untuk {$member->name}\"]")
        ->wait(0.3)
        ->click('Edit pengguna')
        ->wait(0.6)
        ->assertScript('(() => {
            const save = [...document.querySelectorAll("dialog[open] button")]
                .find((el) => el.textContent.trim() === "Simpan");

            if (!save) {
                return false;
            }

            const rect = save.getBoundingClientRect();

            return rect.top >= 0 && rect.bottom <= window.innerHeight;
        })()');
});

it('selects users by tapping the checkbox on touch screens', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($owner, 'mobile')->navigate('/users');

    $page->assertScript('(() => {
        const row = document.querySelector("[data-flux-row]:has(ui-checkbox)");
        const checkbox = row.querySelector("ui-checkbox[value]");
        const avatar = row.querySelector("a span[aria-hidden=\"true\"]");

        return getComputedStyle(checkbox).opacity === "1"
            && getComputedStyle(checkbox).pointerEvents === "auto"
            && getComputedStyle(avatar).opacity === "0";
    })()')
        ->click('ui-checkbox[value]')
        ->wait(0.4)
        ->assertScript('document.querySelector("ui-checkbox[value]").getAttribute("aria-checked") === "true"');
});

it('selects a user when tapping the checkbox wrapper', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($owner, 'mobile')->navigate('/users');

    $page->script(<<<'JS'
        document.querySelector('[data-flux-row]:has(ui-checkbox) span.absolute')
            .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }))
        JS);
    $page->wait(0.4);

    $page->assertPathIs('/users')
        ->assertScript('document.querySelector("ui-checkbox[value]").getAttribute("aria-checked") === "true"');
});

it('selects notifications by tapping the checkbox on touch screens', function () {
    $owner = User::factory()->create();
    $owner->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Admin));

    $page = browserLogin($owner, 'mobile')->navigate('/notifications');

    $page->assertScript('(() => {
        const checkbox = document.querySelector("button[aria-label^=\"Pilih notifikasi\"]");
        const envelope = checkbox.parentElement.querySelector("svg");

        return getComputedStyle(checkbox).opacity === "1"
            && getComputedStyle(checkbox).pointerEvents === "auto"
            && getComputedStyle(envelope).opacity === "0";
    })()')
        ->click('button[aria-label^="Pilih notifikasi"]')
        ->wait(0.4)
        ->assertScript('document.querySelector("button[aria-label^=\"Pilih notifikasi\"]").getAttribute("aria-checked") === "true"');
});

it('reveals user selection on hover on desktop', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    User::factory()->withRole(Role::Member)->create();

    browserLogin($owner)
        ->navigate('/users')
        ->assertScript('getComputedStyle(document.querySelector("ui-checkbox[value]")).opacity === "0"')
        ->hover('[data-flux-row]:has(ui-checkbox)')
        ->wait(0.5)
        ->assertScript('getComputedStyle(document.querySelector("ui-checkbox[value]")).opacity === "1"');
});

it('reveals notification selection on hover on desktop', function () {
    $owner = User::factory()->create();
    $owner->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Admin));
    $owner->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Member));
    $owner->notifications()->latest()->first()->markAsRead();

    $page = browserLogin($owner)->navigate('/notifications');

    $page->assertScript('[...document.querySelectorAll("[data-flux-row]")].every((row) => getComputedStyle(row).cursor === "pointer")')
        ->hover('[data-flux-row]:has(button[aria-label*="Belum dibaca"])')
        ->wait(0.5)
        ->assertScript(<<<'JS'
            (() => {
                const row = document.querySelector('[data-flux-row]:has(button[aria-label*="Belum dibaca"])');
                const checkbox = row.querySelector('button[aria-label^="Pilih notifikasi"]');

                return getComputedStyle(checkbox).opacity === '1';
            })()
            JS);
});

it('switches to light mode and remembers it after navigation', function () {
    $member = User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($member)->navigate('/settings/appearance');

    $page->click('Terang')
        ->wait(0.5)
        ->assertScript('! document.documentElement.classList.contains("dark")');

    $page->navigate('/dashboard')
        ->wait(0.5)
        ->assertScript('! document.documentElement.classList.contains("dark")');
});
