<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public bool $sortAscending = false;

    public bool $showNotificationModal = false;

    public ?string $selectedNotificationId = null;

    /**
     * @var array<int, string>
     */
    public array $selectedNotifications = [];

    public function mount(Request $request): void
    {
        $open = $request->query('open');

        if (! \is_string($open)) {
            return;
        }

        $this->openNotification($open);
    }

    public function sortByReceivedAt(): void
    {
        $this->sortAscending = ! $this->sortAscending;
        $this->selectedNotifications = [];

        $this->resetPage();
    }

    public function updatedPage(): void
    {
        $this->selectedNotifications = [];
    }

    public function openNotification(string $id): void
    {
        /** @var User $user */
        $user = Auth::user();

        $notification = $user->notifications()->whereKey($id)->first();

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();

        $this->selectedNotificationId = $id;
        $this->showNotificationModal = true;

        $this->dispatchUnreadCount();

        $this->js(<<<'JS'
            const trapNotificationModalFocus = (dialog) => {
                if (dialog.dataset.focusTrapped) {
                    return;
                }

                dialog.dataset.focusTrapped = 'true';

                dialog.addEventListener('keydown', (event) => {
                    if (event.key !== 'Tab') {
                        return;
                    }

                    const focusable = Array.from(dialog.querySelectorAll(
                        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
                    )).filter((element) => element.offsetParent !== null);

                    if (focusable.length === 0) {
                        event.preventDefault();

                        return;
                    }

                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];

                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (! event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                });
            };

            const focusNotificationModal = () => {
                const dialog = document.querySelector('dialog[data-flux-flyout][open]');

                if (! dialog) {
                    return false;
                }

                const title = dialog.querySelector('[data-notification-modal-title]');

                if (title) {
                    title.id ||= 'notification-modal-title';
                    dialog.setAttribute('aria-labelledby', title.id);
                }

                trapNotificationModalFocus(dialog);

                dialog.querySelector('[data-flux-modal-close] button')?.focus();

                return true;
            };

            if (! focusNotificationModal()) {
                setTimeout(focusNotificationModal, 60);
            }
        JS);
    }

    public function toggleSelected(string $id): void
    {
        $this->selectedNotifications = \in_array($id, $this->selectedNotifications, true)
            ? array_values(array_diff($this->selectedNotifications, [$id]))
            : [...$this->selectedNotifications, $id];
    }

    public function toggleSelectAll(): void
    {
        $ids = $this->notificationIdsOnPage();

        $this->selectedNotifications = array_diff($ids, $this->selectedNotifications) === []
            ? []
            : $ids;
    }

    public function markSelectedAsRead(): void
    {
        $this->markSelected(asRead: true);
    }

    public function markSelectedAsUnread(): void
    {
        $this->markSelected(asRead: false);
    }

    private function markSelected(bool $asRead): void
    {
        if ($this->selectedNotifications === []) {
            return;
        }

        /** @var User $user */
        $user = Auth::user();

        $count = $user->notifications()
            ->whereIn('id', $this->selectedNotifications)
            ->update(['read_at' => $asRead ? now() : null]);

        $this->selectedNotifications = [];

        Flux::toast(
            variant: 'success',
            text: $asRead
                ? __('Successfully mark :count notification as read', ['count' => $count])
                : __('Successfully mark :count notification as unread', ['count' => $count]),
        );

        $this->dispatchUnreadCount();
    }

    private function dispatchUnreadCount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->dispatch('notifications-updated', count: $user->unreadNotifications()->count());
    }

    /**
     * @return array<int, string>
     */
    private function notificationIdsOnPage(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->notifications()
            ->reorder('created_at', $this->sortAscending ? 'asc' : 'desc')
            ->orderBy('id', $this->sortAscending ? 'asc' : 'desc')
            ->forPage($this->getPage(), 10)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    #[Computed]
    public function selectedNotification(): ?DatabaseNotification
    {
        if ($this->selectedNotificationId === null) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        return $user->notifications()->whereKey($this->selectedNotificationId)->first();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.notifications.index', [
            'notifications' => $user->notifications()
                ->reorder('created_at', $this->sortAscending ? 'asc' : 'desc')
                ->orderBy('id', $this->sortAscending ? 'asc' : 'desc')
                ->paginate(10),
        ])->title(__('Notifications'));
    }
}
