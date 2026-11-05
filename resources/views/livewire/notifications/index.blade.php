<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div class="relative w-full">
            <flux:heading size="xl" level="1">{{ __('Notifications') }}</flux:heading>
            <flux:subheading>{{ __('All notifications for your account') }}</flux:subheading>
        </div>

        @if (count($selectedNotifications) > 0)
            <flux:button.group>
                <flux:button icon="envelope-open" wire:click="markSelectedAsRead">
                    {{ __('Mark as read') }}
                </flux:button>
                <flux:button icon="envelope" wire:click="markSelectedAsUnread">
                    {{ __('Mark as unread') }}
                </flux:button>
            </flux:button.group>
        @endif
    </div>

    <flux:separator variant="subtle" />

    @php($pageIds = $notifications->pluck('id')->all())
    @php($allSelected = $pageIds !== [] && array_diff($pageIds, $selectedNotifications) === [])

    <flux:table :paginate="$notifications">
        <flux:table.columns>
            <flux:table.column class="w-11/12">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        role="checkbox"
                        aria-checked="{{ $allSelected ? 'true' : 'false' }}"
                        aria-label="{{ __('Select all notifications') }}"
                        wire:click="toggleSelectAll"
                        @class([
                            'flex size-[1.125rem] shrink-0 items-center justify-center rounded-[.3rem] border',
                            'border-sky-500 bg-sky-500 text-white' => $allSelected,
                            'border-zinc-300 dark:border-zinc-600' => ! $allSelected,
                        ])
                    >
                        @if ($allSelected)
                            <flux:icon name="check" variant="micro" class="size-3" />
                        @endif
                    </button>

                    {{ __('Notification') }}
                </div>
            </flux:table.column>
            <flux:table.column
                align="end"
                sortable
                :sorted="true"
                :direction="$sortAscending ? 'asc' : 'desc'"
                wire:click="sortByReceivedAt"
                class="w-1/12"
            >
                {{ __('Received at') }}
            </flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($notifications as $notification)
                @php($isUnread = $notification->read_at === null)
                @php($isSelected = in_array($notification->id, $selectedNotifications, true))

                <flux:table.row
                    :key="$notification->id"
                    :class="$isUnread ? 'group cursor-pointer' : 'group cursor-pointer text-zinc-500 dark:text-zinc-400'"
                    wire:click="openNotification('{{ $notification->id }}')"
                >
                    <flux:table.cell class="w-11/12 whitespace-normal">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex size-[1.125rem] shrink-0 items-center justify-center">
                                <flux:icon
                                    :name="$isUnread ? 'envelope' : 'envelope-open'"
                                    variant="micro"
                                    @class([
                                        'text-sky-500' => $isUnread,
                                        'text-zinc-400' => ! $isUnread,
                                        'hidden' => $isSelected,
                                        'group-hover:hidden' => ! $isSelected,
                                    ])
                                />

                                <button
                                    type="button"
                                    role="checkbox"
                                    aria-checked="{{ $isSelected ? 'true' : 'false' }}"
                                    aria-label="{{ __('Select notification') }}"
                                    wire:click.stop="toggleSelected('{{ $notification->id }}')"
                                    @class([
                                        'size-[1.125rem] items-center justify-center rounded-[.3rem] border',
                                        'border-sky-500 bg-sky-500 text-white' => $isSelected,
                                        'border-zinc-300 dark:border-zinc-600' => ! $isSelected,
                                        'hidden group-hover:flex' => ! $isSelected,
                                        'flex' => $isSelected,
                                    ])
                                >
                                    @if ($isSelected)
                                        <flux:icon name="check" variant="micro" class="size-3" />
                                    @endif
                                </button>
                            </div>

                            <div class="min-w-0">
                                <div class="font-medium">{{ $notification->data['title'] ?? '' }}</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $notification->data['description'] ?? '' }}
                                </div>
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell align="end" class="w-1/12 whitespace-nowrap">
                        {{ $notification->created_at?->toFormattedDateString() ?? '—' }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="2" class="text-center text-zinc-500">
                        {{ __('No notifications yet.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal flyout variant="floating" wire:model.self="showNotificationModal" class="md:w-96">
        @if ($this->selectedNotification)
            @php($data = $this->selectedNotification->data)

            <div class="space-y-6">
                <flux:heading size="lg" class="pe-8">{{ $data['title'] ?? '' }}</flux:heading>

                <div class="space-y-3 text-sm">
                    @if (! empty($data['greeting']))
                        <p class="font-medium">{{ $data['greeting'] }}</p>
                    @endif

                    @if (! empty($data['description']))
                        <p>{{ $data['description'] }}</p>
                    @endif

                    @foreach ($data['lines'] ?? [] as $line)
                        <p>{{ $line }}</p>
                    @endforeach

                    @if (! empty($data['action']['url']))
                        <flux:button :href="$data['action']['url']" variant="primary" size="sm">
                            {{ $data['action']['label'] ?? '' }}
                        </flux:button>
                    @endif

                    @if (! empty($data['salutation']))
                        <p>{{ $data['salutation'] }}</p>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>
