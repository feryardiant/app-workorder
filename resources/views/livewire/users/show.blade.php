<div class="flex w-full flex-1 flex-col gap-6">
    @php($roleColor = $user->role->isOwner() ? 'amber' : ($user->role->isAdmin() ? 'blue' : 'zinc'))
    @php($canManageUser = auth()->user()->role->canManageUsers() && ! $user->is(auth()->user()) && (auth()->user()->role->isOwner() || ! $user->role->isOwner()))

    <div class="flex items-start justify-between gap-4">
        <div class="relative w-full">
            <flux:heading size="xl" level="1">
                {{ $user->name }}
                <flux:badge :color="$roleColor">{{ $user->role->label() }}</flux:badge>
            </flux:heading>

            <flux:subheading class="flex flex-wrap items-center gap-2">
                <span>{{ $user->created_at?->toFormattedDateString() ?? '—' }}</span>
                <span>|</span>
                <span>{{ $user->email }}</span>

                <x-verification-badge :user="$user" />
            </flux:subheading>
        </div>

        @if ($canManageUser)
            <div class="hidden items-center gap-2 sm:flex">
                @if ($user->role->isMember())
                    <flux:button icon="arrow-up-circle" wire:click="confirmPromote">
                        {{ __('Promote admin') }}
                    </flux:button>
                @elseif ($user->role->isAdmin())
                    <flux:button icon="arrow-down-circle" wire:click="confirmDemote">
                        {{ __('Demote admin') }}
                    </flux:button>
                @endif

                <flux:button variant="primary" icon="pencil-square" wire:click="openEdit">
                    {{ __('Edit user') }}
                </flux:button>
            </div>

            <flux:dropdown align="end" class="sm:hidden">
                <flux:button
                    icon="ellipsis-horizontal"
                    square
                    :aria-label="__('Actions for :name', ['name' => $user->name])"
                />

                <flux:menu>
                    @if ($user->role->isMember())
                        <flux:menu.item icon="arrow-up-circle" wire:click="confirmPromote">
                            {{ __('Promote admin') }}
                        </flux:menu.item>
                    @elseif ($user->role->isAdmin())
                        <flux:menu.item icon="arrow-down-circle" wire:click="confirmDemote">
                            {{ __('Demote admin') }}
                        </flux:menu.item>
                    @endif

                    <flux:menu.item icon="pencil-square" wire:click="openEdit">
                        {{ __('Edit user') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endif
    </div>

    <flux:separator variant="subtle" />

    <flux:card class="max-w-lg space-y-4">
        <div>
            <flux:text>{{ __('Name') }}</flux:text>
            <flux:heading>{{ $user->name }}</flux:heading>
        </div>

        <div>
            <flux:text>{{ __('Email') }}</flux:text>
            <flux:heading>{{ $user->email }}</flux:heading>
        </div>

        <div>
            <flux:text>{{ __('Registered at') }}</flux:text>
            <flux:heading>{{ $user->created_at?->toFormattedDateString() ?? '—' }}</flux:heading>
        </div>
    </flux:card>

    @if ($canManageUser)
        <livewire:users.edit :user="$user" />
    @endif

    <flux:modal
        wire:model.self="showRoleModal"
        class="md:w-96"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'role-change-heading')"
    >
        <div class="space-y-6">
            <div>
                <flux:heading id="role-change-heading" size="lg">
                    {{ $this->roleChangeIsPromotion ? __('Promote user') : __('Demote user') }}
                </flux:heading>

                <flux:subheading>
                    {{ $this->roleChangeIsPromotion
                        ? __('Promote :name to Admin?', ['name' => $user->name])
                        : __('Demote :name to Member?', ['name' => $user->name]) }}
                </flux:subheading>
            </div>

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button
                    :variant="$this->roleChangeIsPromotion ? 'primary' : 'danger'"
                    wire:click="changeRoleConfirmed"
                >
                    {{ $this->roleChangeIsPromotion ? __('Promote admin') : __('Demote admin') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
