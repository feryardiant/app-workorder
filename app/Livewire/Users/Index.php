<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Users')]
class Index extends Component
{
    use ProfileValidationRules;

    public bool $showCreateModal = false;

    public bool $showDeleteModal = false;

    public ?int $deletingUserId = null;

    public string $name = '';

    public string $email = '';

    public int $role = Role::Member->value;

    public function createUser(): void
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User && $actor->role->canManageUsers(), 403);

        $this->email = Str::lower($this->email);

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $role = Role::from((int) $validated['role']);

        abort_if($role->isOwner() && ! $actor->role->isOwner(), 403);

        $user = User::create([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'role' => $role,
            'password' => Str::random(16),
        ]);

        $user->notify(new VerifyEmailForNewUser);

        $this->reset('name', 'email', 'showCreateModal');
        $this->role = Role::Member->value;

        Flux::toast(variant: 'success', text: __('User created. A verification email has been sent.'));
    }

    public function confirmDelete(User $user): void
    {
        $this->deletingUserId = $user->id;
        $this->showDeleteModal = true;
    }

    public function deleteConfirmed(): void
    {
        $user = $this->deletingUser();

        abort_if($user === null, 404);

        $this->deleteUser($user);

        $this->reset('showDeleteModal', 'deletingUserId');
    }

    public function deleteUser(User $user): void
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User && $actor->role->canManageUsers(), 403);
        abort_if($user->is($actor), 403);
        abort_if($user->role->isOwner() && ! $actor->role->isOwner(), 403);

        $user->delete();

        Flux::toast(variant: 'success', text: __('User removed.'));
    }

    /**
     * @return array<int, Role>
     */
    #[Computed]
    public function roles(): array
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->role->isOwner()
            ? Role::cases()
            : [Role::Admin, Role::Member];
    }

    #[Computed]
    public function deletingUser(): ?User
    {
        return $this->deletingUserId === null
            ? null
            : User::find($this->deletingUserId);
    }

    public function render(): View
    {
        return view('livewire.users.index', [
            /** @var Collection<int, User> $users */
            'users' => User::query()->latest()->get(),
        ]);
    }
}
