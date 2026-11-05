<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex items-start justify-between gap-4">
        <div class="relative w-full">
            <flux:heading size="xl" level="1">{{ __('Notifications') }}</flux:heading>
            <flux:subheading>{{ __('All notifications for your account') }}</flux:subheading>
        </div>
    </div>

    <flux:separator variant="subtle" />

    <flux:table :paginate="$notifications">
        <flux:table.columns>
            <flux:table.column class="w-11/12">{{ __('Notification') }}</flux:table.column>
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

                <flux:table.row
                    :key="$notification->id"
                    :class="$isUnread ? '' : 'text-zinc-500 dark:text-zinc-400'"
                >
                    <flux:table.cell class="w-11/12 whitespace-normal">
                        <div class="flex items-start gap-3">
                            @if ($isUnread)
                                <button
                                    type="button"
                                    wire:click="markAsRead('{{ $notification->id }}')"
                                    class="mt-0.5 shrink-0 text-sky-500"
                                    aria-label="{{ __('Mark as read') }}"
                                >
                                    <flux:icon name="envelope" variant="micro" />
                                </button>
                            @else
                                <flux:icon name="envelope-open" variant="micro" class="mt-0.5 shrink-0 text-zinc-400" />
                            @endif

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
</div>
