<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div class="relative w-full">
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <flux:subheading>{{ __('All registered users') }}</flux:subheading>
        </div>

        @if (auth()->user()->role->canManageUsers())
            <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
                {{ __('Add user') }}
            </flux:button>
        @endif
    </div>

    <flux:separator variant="subtle" />

    <flux:table :paginate="$users">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column class="w-1/5">{{ __('Email') }}</flux:table.column>
            <flux:table.column align="center" class="w-[15%]">{{ __('Role') }}</flux:table.column>
            <flux:table.column align="end" class="w-[15%]">{{ __('Registered at') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($users as $user)
                @php($isCurrentUser = $user->is(auth()->user()))

                <flux:table.row :key="$user->id" :class="$isCurrentUser ? 'bg-zinc-100/60 dark:bg-zinc-700/30' : ''">
                    <flux:table.cell class="w-7/12">
                        <span class="inline-flex items-center gap-2">
                            <flux:avatar size="sm" :initials="$user->initials()" />

                            <flux:link :href="route('users.show', $user)" wire:navigate>{{ $user->name }}</flux:link>

                            @if ($isCurrentUser)
                                <flux:badge size="sm" color="zinc">{{ __('You') }}</flux:badge>
                            @endif
                        </span>
                    </flux:table.cell>

                    <flux:table.cell class="w-2/12">
                        <span class="inline-flex items-center gap-2">
                            {{ $user->email }}

                            <x-verification-badge :user="$user" />
                        </span>
                    </flux:table.cell>

                    <flux:table.cell align="center" class="w-1/12">
                        {{ $user->role->label() }}
                    </flux:table.cell>

                    <flux:table.cell align="end" class="w-1/12 whitespace-nowrap">
                        {{ $user->created_at?->toFormattedDateString() ?? '—' }}
                    </flux:table.cell>

                    <flux:table.cell align="end" class="w-1/12">
                        @if (! $isCurrentUser && auth()->user()->role->canManageUsers())
                            <flux:dropdown align="end">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    square
                                    icon="ellipsis-horizontal"
                                    :aria-label="__('Actions for :name', ['name' => $user->name])"
                                />

                                <flux:menu>
                                    @if (auth()->user()->role->isOwner() || ! $user->role->isOwner())
                                        <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $user->id }})">
                                            {{ __('Edit user') }}
                                        </flux:menu.item>
                                    @endif

                                    @if ($user->role->isMember())
                                        <flux:menu.item icon="arrow-up-circle" wire:click="confirmPromote({{ $user->id }})">
                                            {{ __('Promote admin') }}
                                        </flux:menu.item>
                                    @elseif ($user->role->isAdmin())
                                        <flux:menu.item icon="arrow-down-circle" wire:click="confirmDemote({{ $user->id }})">
                                            {{ __('Demote admin') }}
                                        </flux:menu.item>
                                    @endif

                                    <flux:menu.separator />

                                    <flux:menu.item
                                        variant="danger"
                                        icon="trash"
                                        wire:click="confirmDelete({{ $user->id }})"
                                    >
                                        {{ __('Remove user') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal
        wire:model.self="showCreateModal"
        class="md:w-96"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'add-user-heading')"
    >
        <form wire:submit="createUser" class="space-y-6">
            <div>
                <flux:heading id="add-user-heading" size="lg">{{ __('Add user') }}</flux:heading>
                <flux:subheading>{{ __('Create an account and send a verification email.') }}</flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" type="text" required autofocus autocomplete="off" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Email') }}</flux:label>
                <flux:input wire:model="email" type="email" required autocomplete="off" />
                <flux:error name="email" />
            </flux:field>

            <flux:radio.group wire:model="role" :label="__('Role')" class="flex flex-wrap gap-x-6">
                @foreach ($this->roles as $role)
                    <flux:radio :value="$role->value" :label="__($role->name)" />
                @endforeach

                <flux:error name="role" />
            </flux:radio.group>

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle" color="red">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">{{ __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal
        wire:model.self="showDeleteModal"
        class="md:w-96"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'delete-user-heading')"
    >
        <div class="space-y-6">
            <div>
                <flux:heading id="delete-user-heading" size="lg">{{ __('Remove user') }}</flux:heading>
                <flux:subheading>
                    {{ __('Remove :name? This cannot be undone.', ['name' => $this->deletingUser?->name ?? __('this user')]) }}
                </flux:subheading>
            </div>

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteConfirmed">
                    {{ __('Remove user') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

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
                        ? __('Promote :name to Admin?', ['name' => $this->roleChangeUser?->name ?? __('this user')])
                        : __('Demote :name to Member?', ['name' => $this->roleChangeUser?->name ?? __('this user')]) }}
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

    <livewire:users.edit />
</div>
