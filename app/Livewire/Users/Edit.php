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

    public string $name = '';

    public string $email = '';

    public function mount(?User $user = null): void
    {
        if ($user instanceof User) {
            $this->fillFrom($user);
        }
    }

    #[On('edit-user')]
    public function editUser(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->authorizeUserManagement($user);
        $this->fillFrom($user);

        $this->showModal = true;
    }

    public function update(): void
    {
        abort_if($this->user === null, 404);

        $this->authorizeUserManagement($this->user);

        $this->email = Str::lower($this->email);

        $validated = $this->validate($this->profileRules($this->user->id));

        $this->user->fill([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
        ]);

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
        $this->email = $user->email;
    }

    private function authorizeUserManagement(User $user): void
    {
        $actor = Auth::user();

        abort_unless($actor instanceof User && $actor->role->canManageUsers(), 403);
        abort_if($user->role->isOwner() && ! $actor->role->isOwner(), 403);
    }
}
