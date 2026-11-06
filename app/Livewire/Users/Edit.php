<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class Edit extends Component
{
    use ProfileValidationRules;

    public ?User $user = null;

    public bool $showModal = false;

    public bool $fromDetail = false;

    public string $name = '';

    public string $handle = '';

    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(?User $user = null): void
    {
        if ($user instanceof User) {
            $this->fromDetail = true;
            $this->fillFrom($user);
        }
    }

    /**
     * Fill the form from the selected user.
     */
    #[On('edit-user')]
    public function editUser(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->authorizeUserManagement($user);
        $this->fillFrom($user);

        $this->showModal = true;
    }

    /**
     * Update the selected user.
     */
    public function update(): void
    {
        abort_if($this->user === null, 404);

        $this->authorizeUserManagement($this->user);

        $this->email = Str::lower($this->email);

        $validated = $this->validate($this->profileRules($this->user->id));

        $this->user->fill([
            'name' => $validated['name'],
            'handle' => $validated['handle'],
            'email' => Str::lower($validated['email']),
        ]);

        $handleChanged = $this->user->isDirty('handle');
        $emailChanged = $this->user->isDirty('email');

        if ($emailChanged) {
            $this->user->email_verified_at = null;
        }

        $this->user->save();

        if ($emailChanged) {
            $this->user->notify(new VerifyEmailForNewUser);
        }

        $this->showModal = false;

        $this->dispatch('user-updated');

        if ($this->fromDetail && $handleChanged) {
            $this->redirect(route('users.show', $this->user), navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: $emailChanged
            ? __('User updated. A verification email has been sent to the new address.')
            : __('User updated.'));
    }

    public function render(): View
    {
        return view('livewire.users.edit');
    }

    private function fillFrom(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->handle = $user->handle;
        $this->email = $user->email;
    }

    /**
     * Authorize the current user to manage the given user.
     */
    private function authorizeUserManagement(User $user): void
    {
        $actor = Auth::user();

        abort_unless($actor instanceof User && $actor->role->canManageUsers(), 403);
        abort_if($user->role->isOwner() && ! $actor->role->isOwner(), 403);
    }
}
