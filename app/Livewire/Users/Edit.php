<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Edit user')]
class Edit extends Component
{
    use ProfileValidationRules;

    public User $user;

    public string $name = '';

    public string $email = '';

    public function mount(User $user): void
    {
        $this->authorizeUserManagement($user);

        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function update(): void
    {
        $this->authorizeUserManagement($this->user);

        $this->email = Str::lower($this->email);

        $validated = $this->validate($this->profileRules($this->user->id));

        $this->user->fill([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
        ]);

        if ($this->user->isDirty('email')) {
            $this->user->email_verified_at = null;
        }

        $this->user->save();

        Flux::toast(variant: 'success', text: __('User updated.'));

        $this->redirectRoute('users.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.users.edit');
    }

    private function authorizeUserManagement(User $user): void
    {
        $actor = Auth::user();

        abort_unless($actor instanceof User && $actor->role->canManageUsers(), 403);
        abort_if($user->role->isOwner() && ! $actor->role->isOwner(), 403);
    }
}
