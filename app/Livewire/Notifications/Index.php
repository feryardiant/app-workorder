<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notifications')]
class Index extends Component
{
    use WithPagination;

    public bool $sortAscending = false;

    public function sortByReceivedAt(): void
    {
        $this->sortAscending = ! $this->sortAscending;

        $this->resetPage();
    }

    public function markAsRead(string $id): void
    {
        /** @var User $user */
        $user = Auth::user();

        $notification = $user->notifications()->find($id);

        if ($notification === null || $notification->read_at !== null) {
            return;
        }

        $notification->markAsRead();

        // Reload so the sidebar unread badge and count reflect the change.
        $this->redirect(route('notifications.index'), navigate: true);
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
