<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use App\Notifications\UserRoleChangedNotification;

it('uses pointer cursors for controls and default cursors for menu containers', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    $first = User::factory()->withRole(Role::Member)->create();
    User::factory()->withRole(Role::Member)->create();

    $page = browserLogin($owner)->navigate('/users');

    $page->hover('[data-flux-row]:has(ui-checkbox[value="'.$first->id.'"])')
        ->wait(0.3)
        ->click('ui-checkbox[value="'.$first->id.'"]')
        ->wait(0.3)
        ->click('Tindakan')
        ->wait(0.4)
        ->assertScript(<<<'JS'
            (() => {
                const cursor = (element) => element ? getComputedStyle(element).cursor : null;
                const menu = [...document.querySelectorAll('[role="menu"]')].at(-1);
                const trigger = [...document.querySelectorAll('button')]
                    .find((button) => button.textContent.trim() === 'Tindakan');
                const rowTrigger = document.querySelector('[aria-label^="Tindakan untuk"]:not([disabled])');

                return cursor(trigger) === 'pointer'
                    && cursor(menu) === 'default'
                    && cursor(menu?.querySelector('[role="menuitem"]')) === 'pointer'
                    && cursor(rowTrigger) === 'pointer';
            })()
            JS);
});

it('mutes read notification titles and descriptions', function () {
    $owner = User::factory()->create();
    $actor = User::factory()->create();

    $owner->notify(new UserRoleChangedNotification($actor, Role::Admin));
    $owner->notify(new UserRoleChangedNotification($actor, Role::Member));
    $owner->notifications()->latest()->first()->markAsRead();

    browserLogin($owner)
        ->navigate('/notifications')
        ->assertScript(<<<'JS'
            (() => {
                const luminance = (color) => {
                    const [r, g, b] = color.match(/\d+/g).map(Number).slice(0, 3).map((channel) => {
                        const value = channel / 255;

                        return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
                    });

                    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
                };
                const contrast = (foreground) => {
                    const background = getComputedStyle(document.body).backgroundColor;
                    const lighter = Math.max(luminance(foreground), luminance(background));
                    const darker = Math.min(luminance(foreground), luminance(background));

                    return (lighter + 0.05) / (darker + 0.05);
                };
                const items = [...document.querySelectorAll('[data-flux-row]')].map((row) => {
                    const title = row.querySelector('button[wire\\:click\\.stop]:not([role="checkbox"])');
                    const description = title.parentElement.querySelector('div.text-sm');

                    return {
                        state: title.textContent.includes('Sudah dibaca') ? 'read' : 'unread',
                        title: getComputedStyle(title).color,
                        description: getComputedStyle(description).color,
                    };
                });
                const read = items.find((item) => item.state === 'read');
                const unread = items.find((item) => item.state === 'unread');

                return contrast(read.title) < contrast(unread.title)
                    && contrast(read.description) < contrast(unread.description);
            })()
            JS);
});

it('labels the new controls in Indonesian', function () {
    $owner = User::factory()->withRole(Role::Owner)->create();
    User::factory()->withRole(Role::Member)->create();

    browserLogin($owner, 'mobile')
        ->navigate('/users')
        ->assertScript('document.querySelector("input[aria-label=\"Pilih semua pengguna\"]") !== null')
        ->assertScript('(document.querySelector("ui-checkbox[value]").getAttribute("aria-label") || "").startsWith("Pilih ")')
        ->click('button[aria-label="Tindakan"]')
        ->wait(0.3)
        ->assertSee('Tambah pengguna');
});
