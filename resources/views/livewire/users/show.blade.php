<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div class="relative w-full">
            <flux:heading size="xl" level="1">{{ $user->name }}</flux:heading>
            <flux:subheading>{{ __('User profile') }}</flux:subheading>
        </div>

        @if (auth()->user()->role->canManageUsers())
            <flux:button variant="primary" icon="pencil-square" :href="route('users.edit', $user)" wire:navigate>
                {{ __('Edit User') }}
            </flux:button>
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
</div>
