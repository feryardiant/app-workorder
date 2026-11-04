<div class="flex w-full flex-1 flex-col gap-6">
    @php($roleColor = $user->role->isOwner() ? 'amber' : ($user->role->isAdmin() ? 'blue' : 'zinc'))
    @php($canManageUser = auth()->user()->role->canManageUsers() && (auth()->user()->role->isOwner() || ! $user->role->isOwner()))

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
            <div class="flex items-center gap-2">
                @if ($user->role->isMember())
                    <flux:button wire:click="confirmPromote" icon="arrow-up-circle">
                        {{ __('Promote Admin') }}
                    </flux:button>
                @elseif ($user->role->isAdmin())
                    <flux:button wire:click="confirmDemote" icon="arrow-down-circle">
                        {{ __('Demote Admin') }}
                    </flux:button>
                @endif

                <flux:button variant="primary" wire:click="openEdit" icon="pencil-square">
                    {{ __('Edit User') }}
                </flux:button>
            </div>
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
                    {{ $this->roleChangeIsPromotion ? __('Promote Admin') : __('Demote Admin') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
