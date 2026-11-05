<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;

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
