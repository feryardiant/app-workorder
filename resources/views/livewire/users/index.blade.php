<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div class="relative w-full">
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <flux:subheading>{{ __('All registered users') }}</flux:subheading>
        </div>

        @if (auth()->user()->role->canManageUsers())
            <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
                {{ __('Add User') }}
            </flux:button>
        @endif
    </div>

    <flux:separator variant="subtle" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Registered at') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($users as $user)
                @php($isCurrentUser = $user->is(auth()->user()))

                <flux:table.row :key="$user->id" :class="$isCurrentUser ? 'bg-zinc-100/60 dark:bg-zinc-700/30' : ''">
                    <flux:table.cell>
                        <span class="inline-flex items-center gap-2">
                            <flux:link :href="route('users.show', $user)" wire:navigate>{{ $user->name }}</flux:link>

                            @if ($isCurrentUser)
                                <flux:badge size="sm" color="zinc">{{ __('You') }}</flux:badge>
                            @endif
                        </span>
                    </flux:table.cell>

                    <flux:table.cell>{{ $user->email }}</flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">
                        {{ $user->created_at?->toFormattedDateString() ?? '—' }}
                    </flux:table.cell>

                    <flux:table.cell align="end">
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
                                    <flux:menu.item :href="route('users.edit', $user)" icon="pencil-square" wire:navigate>
                                        {{ __('Edit User') }}
                                    </flux:menu.item>

                                    <flux:menu.item
                                        variant="danger"
                                        icon="trash"
                                        wire:click="deleteUser({{ $user->id }})"
                                        wire:confirm="{{ __('Remove :name? This cannot be undone.', ['name' => $user->name]) }}"
                                    >
                                        {{ __('Remove User') }}
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
</div>
