<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;

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
