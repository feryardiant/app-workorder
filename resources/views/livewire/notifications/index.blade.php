<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="relative w-full">
            <flux:heading size="xl" level="1">{{ __('Notifications') }}</flux:heading>
            <flux:subheading>{{ __('All notifications for your account') }}</flux:subheading>
        </div>

        @if (count($selectedNotifications) > 0)
            <flux:button.group class="shrink-0 self-start max-sm:flex-wrap">
                <flux:button
                    icon="envelope-open"
                    wire:click="markSelectedAsRead"
                    aria-label="{{ __('Mark as read') }}"
                    class="max-sm:w-10! max-sm:px-0!"
                >
                    <span class="max-sm:hidden">{{ __('Mark as read') }}</span>
                </flux:button>
                <flux:button
                    icon="envelope"
                    wire:click="markSelectedAsUnread"
                    aria-label="{{ __('Mark as unread') }}"
                    class="max-sm:w-10! max-sm:px-0!"
                >
                    <span class="max-sm:hidden">{{ __('Mark as unread') }}</span>
                </flux:button>
            </flux:button.group>
        @endif
    </div>

    <flux:separator variant="subtle" />

    @php($pageIds = $notifications->pluck('id')->all())
    @php($allSelected = $pageIds !== [] && array_diff($pageIds, $selectedNotifications) === [])
    @php($someSelected = ! $allSelected && array_intersect($pageIds, $selectedNotifications) !== [])

    <flux:table :paginate="$notifications">
        <flux:table.columns>
            <flux:table.column class="w-11/12">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        role="checkbox"
                        aria-checked="{{ $allSelected ? 'true' : ($someSelected ? 'mixed' : 'false') }}"
                        aria-label="{{ __('Select all notifications') }}"
                        wire:click="toggleSelectAll"
                        @disabled($pageIds === [])
                        @class([
                            'relative flex size-[1.125rem] shrink-0 items-center justify-center rounded-[.3rem] border before:absolute before:-inset-2 before:content-[''] disabled:cursor-not-allowed disabled:opacity-40',
                            'border-sky-500 bg-sky-500 text-white' => $allSelected || $someSelected,
                            'border-zinc-300 dark:border-zinc-600' => ! $allSelected && ! $someSelected,
                        ])
                    >
                        @if ($allSelected)
                            <flux:icon name="check" variant="micro" class="size-3" />
                        @elseif ($someSelected)
                            <flux:icon name="minus" variant="micro" class="size-3" />
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
                aria-sort="{{ $sortAscending ? 'ascending' : 'descending' }}"
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
                    class="group cursor-pointer hover:bg-zinc-100/60 dark:hover:bg-zinc-700/30"
                    wire:click="openNotification('{{ $notification->id }}')"
                >
                    <flux:table.cell class="w-11/12 whitespace-normal">
                        <div class="flex items-start gap-3">
                            <div class="relative mt-0.5 flex size-[1.125rem] shrink-0 items-center justify-center">
                                <flux:icon
                                    :name="$isUnread ? 'envelope' : 'envelope-open'"
                                    variant="micro"
                                    @class([
                                        'text-sky-500' => $isUnread,
                                        'text-zinc-400' => ! $isUnread,
                                        'opacity-0' => $isSelected,
                                        'transition-opacity group-hover:opacity-0 group-focus-within:opacity-0 [@media(hover:none)]:opacity-0' => ! $isSelected,
                                    ])
                                />

                                <button
                                    type="button"
                                    role="checkbox"
                                    aria-checked="{{ $isSelected ? 'true' : 'false' }}"
                                    aria-label="{{ __('Select notification') }}: {{ $notification->data['title'] ?? '' }} ({{ $isUnread ? __('Unread') : __('Read') }})"
                                    wire:click.stop="toggleSelected('{{ $notification->id }}')"
                                    @class([
                                        "before:absolute before:-inset-2 before:content-[''] absolute inset-0 z-10 flex items-center justify-center rounded-[.3rem] border transition-opacity",
                                        'border-sky-500 bg-sky-500 text-white' => $isSelected,
                                        'border-zinc-300 bg-white dark:border-zinc-600 dark:bg-zinc-800' => ! $isSelected,
                                        'pointer-events-auto opacity-100' => $isSelected,
                                        'pointer-events-none opacity-0 group-hover:pointer-events-auto group-hover:opacity-100 group-focus-within:pointer-events-auto group-focus-within:opacity-100 focus-visible:pointer-events-auto focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500 [@media(hover:none)]:pointer-events-auto [@media(hover:none)]:opacity-100' => ! $isSelected,
                                    ])
                                >
                                    @if ($isSelected)
                                        <flux:icon name="check" variant="micro" class="size-3" />
                                    @endif
                                </button>
                            </div>

                            <div class="min-w-0">
                                <button
                                    type="button"
                                    wire:click.stop="openNotification('{{ $notification->id }}')"
                                    @class([
                                        'text-left font-medium focus-visible:underline focus-visible:outline-none',
                                        'text-zinc-900 dark:text-zinc-100' => $isUnread,
                                        'text-zinc-500 dark:text-zinc-400' => ! $isUnread,
                                    ])
                                >
                                    <span class="sr-only">{{ $isUnread ? __('Unread') : __('Read') }}:</span>
                                    {{ $notification->data['title'] ?? '' }}
                                </button>

                                <div @class([
                                    'text-sm',
                                    'text-zinc-600 dark:text-zinc-300' => $isUnread,
                                    'text-zinc-500 dark:text-zinc-400' => ! $isUnread,
                                ])>
                                    {{ $notification->data['description'] ?? '' }}
                                </div>
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell align="end" class="w-1/12 whitespace-nowrap">
                        {{ $notification->created_at?->translatedFormat('d M Y') ?? '—' }}
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
                <flux:heading size="lg" class="pe-8" data-notification-modal-title>
                    {{ $data['title'] ?? '' }}
                </flux:heading>

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
