<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div class="relative w-full">
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <flux:subheading>{{ __('All registered users') }}</flux:subheading>
        </div>

        @if (auth()->user()->role->canManageUsers())
            <div class="hidden items-center gap-2 sm:flex">
                <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
                    {{ __('Add user') }}
                </flux:button>

                @if ($selected !== [])
                    <flux:dropdown align="end" class="order-first">
                        <flux:button icon:trailing="chevron-down">
                            {{ __('Action') }}
                        </flux:button>

                        <flux:menu>
                            <flux:menu.item
                                variant="danger"
                                icon="trash"
                                wire:click="confirmDeleteSelected"
                            >
                                {{ __('Delete selected users') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                @endif
            </div>

            <flux:dropdown align="end" class="sm:hidden">
                <flux:button
                    icon="ellipsis-horizontal"
                    square
                    :aria-label="__('Actions')"
                />

                <flux:menu>
                    <flux:menu.item icon="plus" wire:click="$set('showCreateModal', true)">
                        {{ __('Add user') }}
                    </flux:menu.item>

                    @if ($selected !== [])
                        <flux:menu.separator />

                        <flux:menu.item
                            variant="danger"
                            icon="trash"
                            wire:click="confirmDeleteSelected"
                        >
                            {{ __('Delete selected users') }}
                        </flux:menu.item>
                    @endif
                </flux:menu>
            </flux:dropdown>
        @endif
    </div>

    <flux:separator variant="subtle" />

    @php($canManageUsers = auth()->user()->role->canManageUsers())
    @php($selectedIds = array_map('intval', $selected))

    <flux:checkbox.group
        wire:key="users-{{ $users->currentPage() }}-{{ $sortDirection }}-{{ $selectionKey }}"
        wire:model.live="selected"
    >
        <flux:table :paginate="$users">
            <flux:table.columns>
                <flux:table.column>
                    <span class="inline-flex items-center gap-2">
                        @if ($canManageUsers)
                            <label class="relative -m-1.5 inline-flex shrink-0 cursor-pointer items-center justify-center p-1.5">
                                <input
                                    type="checkbox"
                                    wire:change="toggleSelectAll"
                                    @checked($allSelected)
                                    x-init="const el = $el; const syncIndeterminate = () => el.indeterminate = $wire.someSelected; syncIndeterminate(); $wire.$watch('someSelected', syncIndeterminate)"
                                    aria-label="{{ __('Select all users') }}"
                                    class="peer size-[1.125rem] cursor-pointer appearance-none rounded-[.3rem] border border-zinc-300 bg-white shadow-xs outline-offset-2 checked:border-zinc-800 checked:bg-zinc-800 indeterminate:border-zinc-800 indeterminate:bg-zinc-800 dark:border-white/10 dark:bg-white/10 dark:checked:border-white dark:checked:bg-white dark:indeterminate:border-white dark:indeterminate:bg-white"
                                />

                                <svg class="pointer-events-none absolute hidden size-[.6rem] text-white peer-checked:block dark:text-zinc-800" viewBox="0 0 12 10" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M1 5 4.5 8.5 11 1" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>

                                <svg class="pointer-events-none absolute hidden h-0.5 w-[.55rem] rounded-full bg-white peer-indeterminate:block dark:bg-zinc-800" viewBox="0 0 10 2" fill="currentColor" aria-hidden="true">
                                    <rect width="10" height="2" rx="1" />
                                </svg>
                            </label>
                        @endif

                        {{ __('Name') }}
                    </span>
                </flux:table.column>
                <flux:table.column class="w-1/5">{{ __('Email') }}</flux:table.column>
                <flux:table.column align="center" class="w-[15%]">{{ __('Role') }}</flux:table.column>
                <flux:table.column
                    align="end"
                    class="w-[15%]"
                    sortable
                    sorted
                    :direction="$sortDirection"
                    :aria-sort="$sortDirection === 'asc' ? 'ascending' : 'descending'"
                    wire:click="sortByCreatedAt"
                >
                    {{ __('Registered at') }}
                </flux:table.column>
                <flux:table.column align="center">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($users as $user)
                    @php($isCurrentUser = $user->is(auth()->user()))
                    @php($isSelected = in_array($user->id, $selectedIds, true))
                    @php($canManageUser = $canManageUsers && ! $isCurrentUser && (auth()->user()->role->isOwner() || ! $user->role->isOwner()))

                    <flux:table.row
                        :key="$user->id"
                        class="group/row cursor-pointer hover:bg-zinc-100/60 dark:hover:bg-zinc-700/30"
                        x-on:click="if (! $event.target.closest('a, button, input, ui-checkbox, ui-dropdown')) window.Livewire.navigate('{{ route('users.show', $user) }}')"
                    >
                        <flux:table.cell class="w-7/12">
                            <span class="relative inline-flex items-center gap-2">
                                <a
                                    href="{{ route('users.show', $user) }}"
                                    wire:navigate
                                    class="inline-flex items-center gap-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-900"
                                >
                                    <span
                                        @class([
                                            'relative inline-flex ml-1 size-8 shrink-0 items-center justify-center transition-opacity',
                                            'opacity-0' => $isSelected,
                                            'group-hover/row:opacity-0 group-focus-within/row:opacity-0 [@media(hover:none)]:opacity-0' => ! $isSelected && $canManageUser,
                                        ])
                                        aria-hidden="true"
                                    >
                                        <flux:avatar size="sm" :initials="$user->initials()" />
                                    </span>

                                    {{ $user->name }}
                                </a>

                                @if ($isCurrentUser)
                                    <flux:badge size="sm" color="zinc">{{ __('You') }}</flux:badge>
                                @endif

                                @if ($canManageUser)
                                    <span
                                        class="absolute left-0 top-1/2 z-10 flex size-10 -translate-y-1/2 items-center justify-center"
                                        x-on:click="if (! $event.target.closest('ui-checkbox')) { $event.stopPropagation(); $el.querySelector('ui-checkbox').click() }"
                                    >
                                        <flux:checkbox
                                            value="{{ $user->id }}"
                                            :aria-label="__('Select :name', ['name' => $user->name])"
                                            @class([
                                                'transition-opacity',
                                                'pointer-events-none opacity-0 group-hover/row:pointer-events-auto group-hover/row:opacity-100 group-focus-within/row:pointer-events-auto group-focus-within/row:opacity-100 [@media(hover:none)]:pointer-events-auto [@media(hover:none)]:opacity-100' => ! $isSelected,
                                            ])
                                        />
                                    </span>
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
                            {{ $user->created_at?->translatedFormat('d M Y') ?? '—' }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="w-1/12">
                            @if ($canManageUser)
                                <flux:dropdown align="end">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        square
                                        icon="ellipsis-horizontal"
                                        :disabled="$isSelected"
                                        :aria-label="__('Actions for :name', ['name' => $user->name])"
                                    />

                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $user->id }})">
                                            {{ __('Edit user') }}
                                        </flux:menu.item>

                                        @if ($user->role->isMember())
                                            <flux:menu.item icon="arrow-up-circle" wire:click="confirmPromote({{ $user->id }})">
                                                {{ __('Promote admin') }}
                                            </flux:menu.item>
                                        @elseif ($user->role->isAdmin())
                                            <flux:menu.item icon="arrow-down-circle" wire:click="confirmDemote({{ $user->id }})">
                                                {{ __('Demote admin') }}
                                            </flux:menu.item>
                                        @endif

                                        @if (! $user->hasVerifiedEmail())
                                            <flux:menu.item icon="arrow-path" wire:click="resendVerification({{ $user->id }})">
                                                {{ __('Resend verification') }}
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
    </flux:checkbox.group>


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
        wire:model.self="showBatchDeleteModal"
        class="md:w-96"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'delete-selected-users-heading')"
    >
        <div class="space-y-6">
            <div>
                <flux:heading id="delete-selected-users-heading" size="lg">{{ __('Remove users') }}</flux:heading>
                <flux:subheading>
                    {{ __('Remove :count selected users? This cannot be undone.', ['count' => count($selected)]) }}
                </flux:subheading>
            </div>

            <div class="flex items-center justify-between">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteSelectedConfirmed">
                    {{ __('Remove users') }}
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
