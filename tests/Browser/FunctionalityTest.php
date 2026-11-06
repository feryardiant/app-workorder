<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use App\Notifications\UserRoleChangedNotification;

it('redirects guests away from protected pages', function () {
    visit('/users')->assertPathIs('/login');
});

it('lets a verified user sign in and out', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();

    $page = browserLogin($owner)
        ->assertPathIs('/dashboard')
        ->assertSee('Pengguna');

    $page->click('@sidebar-menu-button')
        ->click('[data-flux-sidebar] [data-test="logout-button"]');

    $page->assertPathIs('/login');
});

it('hides management actions on your own user detail page', function () {
    $admin = User::factory()->withRole(Role::Admin)->create();
    $member = User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($admin)->navigate("/users/{$admin->handle}");

    $page->assertDontSee('Edit pengguna')
        ->assertDontSee('Jadikan Anggota')
        ->navigate("/users/{$member->handle}")
        ->assertSee('Edit pengguna');
});

it('lets an owner add a user', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();

    $page = browserLogin($owner)->navigate('/users');

    $page->click('Tambah pengguna')
        ->fill('name', 'Budi Santoso')
        ->fill('email', 'budi@example.test')
        ->click('Tambah');

    $page->assertSee('Budi Santoso')
        ->assertSee('budi@example.test');
});

it('hides management controls from members on mobile', function () {
    $member = User::factory()->withRole(Role::Member)->create();

    browserLogin($member, 'mobile')
        ->navigate('/users')
        ->assertSee('Semua pengguna terdaftar')
        ->assertDontSee('Tambah pengguna')
        ->assertMissing('[aria-label^="Tindakan untuk"]');
});

it('rejects invalid credentials without signing in', function () {
    $member = User::factory()->create();

    visit('/login')
        ->fill('email', $member->email)
        ->fill('password', 'wrong-password')
        ->click('@login-button')
        ->assertPathIs('/login')
        ->assertSee('Kredensial ini tidak cocok dengan catatan kami.');
});

it('gates unverified users from verified pages', function () {
    $unverified = User::factory()->unverified()->create();

    browserLogin($unverified)
        ->assertPathIs('/email/verify')
        ->assertSee('verifikasi alamat email');
});

it('prevents admins from managing owners', function () {
    $admin = User::factory()->withRole(Role::Admin)->create();
    $owner = User::factory()->withRole(Role::Owner)->create();
    $member = User::factory()->withRole(Role::Member)->create();

    browserLogin($admin)
        ->navigate('/users')
        ->assertMissing("[aria-label=\"Tindakan untuk {$owner->name}\"]")
        ->assertPresent("[aria-label=\"Tindakan untuk {$member->name}\"]")
        ->assertMissing("[aria-label=\"Tindakan untuk {$admin->name}\"]");
});

it('deletes selected users in one batch', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    $first = User::factory()->withRole(Role::Member)->create();
    $second = User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($owner, 'mobile')->navigate('/users');

    $page->click('input[aria-label="Pilih semua pengguna"]')
        ->wait(0.5)
        ->click('button[aria-label="Tindakan"]')
        ->wait(0.6)
        ->click('[role="menu"]:popover-open [role="menuitem"]:last-of-type')
        ->wait(0.4)
        ->assertSee('Hapus 2 pengguna terpilih?')
        ->click('button[wire\:click="deleteSelectedConfirmed"]')
        ->wait(0.8)
        ->assertSee('2 pengguna berhasil dihapus.')
        ->assertDontSee($first->name)
        ->assertDontSee($second->name);
});

it('clears the user selection when sorting', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    User::factory()->count(2)->create();

    $page = browserLogin($owner, 'mobile')->navigate('/users');

    $page->click('input[aria-label="Pilih semua pengguna"]')
        ->wait(0.4)
        ->click('Terdaftar pada')
        ->wait(0.5)
        ->assertScript('document.querySelectorAll("ui-checkbox[aria-checked=\"true\"]").length === 0')
        ->assertScript('document.querySelector("input[aria-label=\"Pilih semua pengguna\"]").indeterminate === false');
});

it('updates a user from the edit flyout and discards on escape', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    $member = User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($owner)->navigate('/users');

    $page->click("[aria-label=\"Tindakan untuk {$member->name}\"]")
        ->wait(0.3)
        ->click('Edit pengguna')
        ->wait(0.5)
        ->fill('dialog[open] input[name="name"]', 'Nama Baru')
        ->click('dialog[open] button[type="submit"]')
        ->wait(0.8)
        ->assertSee('Pengguna diperbarui.')
        ->assertSee('Nama Baru')
        ->click('[aria-label="Tindakan untuk Nama Baru"]')
        ->wait(0.3)
        ->click('Edit pengguna')
        ->wait(0.5)
        ->fill('dialog[open] input[name="name"]', 'Nama Dibatalkan')
        ->keys('dialog[open] input[name="name"]', 'Escape')
        ->wait(0.5)
        ->assertSee('Nama Baru')
        ->assertDontSee('Nama Dibatalkan');
});

it('navigates from a user row but not from its selection control', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    $member = User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($owner)->navigate('/users');

    $page->click('[data-flux-row]:has(ui-checkbox[value="'.$member->id.'"]) [data-flux-cell]:nth-child(2)')
        ->assertPathIs("/users/{$member->handle}");

    $page->navigate('/users')
        ->hover('[data-flux-row]:has(ui-checkbox[value="'.$member->id.'"])')
        ->wait(0.4)
        ->click('ui-checkbox[value="'.$member->id.'"]')
        ->wait(0.4)
        ->assertPathIs('/users')
        ->assertScript('document.querySelector("ui-checkbox[value=\"'.$member->id.'\"]").getAttribute("aria-checked") === "true"');
});

it('does not navigate when selecting text in a user row', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($owner)->navigate('/users');

    $page->script(<<<'JS'
        const cell = document.querySelector('[data-flux-row]:has(ui-checkbox) [data-flux-cell]:nth-child(2)');
        const range = document.createRange();
        range.selectNodeContents(cell);

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);

        cell.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }))
        JS);
    $page->wait(0.4);

    $page->assertPathIs('/users');
});

it('lists only the signed-in user notifications', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $otherActor = User::factory()->create();

    $owner->notify(new UserRoleChangedNotification($actor, Role::Admin));
    User::factory()->create()->notify(new UserRoleChangedNotification($otherActor, Role::Admin));

    browserLogin($owner)
        ->navigate('/notifications')
        ->assertSee($actor->name)
        ->assertDontSee($otherActor->name);
});

it('shows an empty state when there are no notifications', function () {
    $member = User::factory()->create();

    browserLogin($member)
        ->navigate('/notifications')
        ->assertSee('Belum ada notifikasi.');
});

it('opens a notification, marks it read, and updates the menu count', function () {
    $owner = User::factory()->create();
    $owner->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Admin));

    $page = browserLogin($owner)->navigate('/notifications');

    $page->assertScript('[...document.querySelectorAll("[role=status]")].some((el) => el.textContent.includes("1"))')
        ->click('[data-flux-row] button[wire\:click\.stop]:not([role="checkbox"])')
        ->wait(0.6)
        ->assertScript('(() => {
            const dialog = document.querySelector("dialog[open]");

            return !!dialog && dialog.textContent.includes("Peran Anda di");
        })()')
        ->assertScript('[...document.querySelectorAll("[role=status]")].some((el) => el.textContent.includes("Tidak ada notifikasi belum dibaca"))');
});

it('opens a notification from a deep link', function () {
    $owner = User::factory()->create();
    $owner->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Admin));

    $notification = $owner->notifications()->firstOrFail();

    browserLogin($owner)
        ->navigate("/notifications?open={$notification->id}")
        ->wait(0.5)
        ->assertScript('document.querySelector("dialog[open]") !== null')
        ->assertScript('window.location.search === ""');

    expect($notification->refresh()->read_at)->not->toBeNull();
});

it('marks selected notifications read and unread in bulk', function () {
    $owner = User::factory()->create();
    $owner->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Admin));
    $owner->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Member));

    $page = browserLogin($owner, 'mobile')->navigate('/notifications');

    $page->click('button[aria-label="Pilih semua notifikasi"]')
        ->wait(0.4)
        ->click('button[aria-label="Tandai sudah dibaca"]')
        ->wait(0.8)
        ->assertSee('Berhasil menandai 2 notifikasi sebagai sudah dibaca')
        ->click('button[aria-label="Pilih semua notifikasi"]')
        ->wait(0.4)
        ->click('button[aria-label="Tandai belum dibaca"]')
        ->wait(0.8)
        ->assertSee('Berhasil menandai 2 notifikasi sebagai belum dibaca');
});

it('shows a toast when a new notification arrives', function () {
    $owner = User::factory()->create();
    $owner->notify(new UserRoleChangedNotification(User::factory()->create(), Role::Admin));

    $page = browserLogin($owner)->navigate('/dashboard');

    $page->wait(1);

    $actor = User::factory()->create();
    $owner->notify(new UserRoleChangedNotification($actor, Role::Member));

    $page->wait(7)
        ->assertSee($actor->name)
        ->assertScript('[...document.querySelectorAll("[role=status]")].some((el) => el.textContent.includes("2"))');
});

it('lets a user update their own profile', function () {
    $member = User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($member)->navigate('/settings/profile');

    $page->fill('name', 'Nama Baru')
        ->click('Simpan')
        ->assertSee('Profil diperbarui.');

    $page->navigate('/settings/profile')
        ->assertValue('name', 'Nama Baru');
});

it('updates locale and offers to reload the page', function () {
    $member = User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($member)->navigate('/settings/preferences');

    $page->click('Bahasa Inggris')
        ->assertSee('Preferensi Anda telah diperbarui.')
        ->click('Muat ulang halaman')
        ->wait(0.8)
        ->assertSee('Preferences');

    expect($member->refresh()->locale->value)->toBe('en');
});

it('updates timezone and offers to reload the page', function () {
    $member = User::factory()->withRole(Role::Member)->create(['timezone' => null]);

    $page = browserLogin($member)->navigate('/settings/preferences');

    $page->select('[x-model=timezone]', 'Asia/Jakarta')
        ->assertSee('Preferensi Anda telah diperbarui.')
        ->click('Muat ulang halaman')
        ->wait(0.8)
        ->assertScript('document.querySelector("[x-model=timezone]").value === "Asia/Jakarta"');

    expect($member->refresh()->timezone)->toBe('Asia/Jakarta');
});
