<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notifications')]
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

    public function sortByReceivedAt(): void
    {
        $this->sortAscending = ! $this->sortAscending;

        $this->resetPage();
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

        $user->notifications()
            ->whereIn('id', $this->selectedNotifications)
            ->update(['read_at' => $asRead ? now() : null]);

        $this->selectedNotifications = [];

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
                ->paginate(10),
        ]);
    }
}
