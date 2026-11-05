@php($unreadNotifications = auth()->user()->unreadNotifications()->count())

<div
    {{ $attributes }}
    x-data="{ unread: {{ $unreadNotifications }} }"
    @notifications-updated.window="unread = $event.detail.count"
>
    <span
        class="sr-only"
        role="status"
        aria-live="polite"
        aria-atomic="true"
        x-text="unread > 0
            ? {{ \Illuminate\Support\Js::from(__(':count unread notifications')) }}.replace(':count', unread)
            : {{ \Illuminate\Support\Js::from(__('No unread notifications')) }}"
    ></span>

    <flux:dropdown position="bottom" align="start" class="w-full">
        <flux:sidebar.profile
            :name="auth()->user()->name"
            icon:trailing="chevrons-up-down"
            data-test="sidebar-menu-button"
        >
            <x-slot:avatar>
                <span class="relative inline-flex">
                    <flux:avatar
                        :name="auth()->user()->name"
                        :initials="auth()->user()->initials()"
                        size="sm"
                    />

                    <span
                        x-show="unread > 0"
                        class="absolute top-0 left-0 z-10 size-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-zinc-900"
                        aria-hidden="true"
                    ></span>
                </span>
            </x-slot:avatar>
        </flux:sidebar.profile>

        <flux:menu class="w-56">
            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                <flux:avatar
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                />
                <div class="grid flex-1 text-start text-sm leading-tight">
                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                </div>
            </div>
            <flux:menu.separator />
            <flux:menu.radio.group>
                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                    {{ __('Settings') }}
                </flux:menu.item>
                <flux:menu.item :href="route('notifications.index')" icon="bell" wire:navigate>
                    {{ __('Notification') }}

                    <x-slot:suffix>
                        <span x-show="unread > 0" x-text="unread" class="ms-auto text-xs text-zinc-400"></span>
                    </x-slot:suffix>
                </flux:menu.item>
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item
                        as="button"
                        type="submit"
                        icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer"
                        data-test="logout-button"
                    >
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu.radio.group>
        </flux:menu>
    </flux:dropdown>
</div>
