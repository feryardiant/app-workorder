<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;

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

it('lets a user update their own profile', function () {
    $member = User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($member)->navigate('/settings/profile');

    $page->fill('name', 'Nama Baru')
        ->click('Simpan')
        ->assertSee('Profil diperbarui.');

    $page->navigate('/settings/profile')
        ->assertValue('name', 'Nama Baru');
});
