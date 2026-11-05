<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Actions\Users\ChangeUserRole;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\VerifyEmailForNewUser;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Title('Users')]
class Index extends Component
{
    use ProfileValidationRules, WithPagination;

    public bool $showCreateModal = false;

    public bool $showDeleteModal = false;

    public bool $showRoleModal = false;

    public ?int $deletingUserId = null;

    public ?int $roleChangeUserId = null;

    public ?int $roleChangeTo = null;

    public string $name = '';

    public string $email = '';

    public int $role = Role::Member->value;

    /**
     * @var array<int, int|string>
     */
    public array $selected = [];

    public int $selectionKey = 0;

    /**
     * @var 'asc'|'desc'
     */
    public string $sortDirection = 'desc';

    public function createUser(): void
    {
        $actor = Auth::user();

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
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('User created. A verification email has been sent.'));
    }

    public function confirmDelete(User $user): void
    {
        $this->deletingUserId = $user->id;
        $this->showDeleteModal = true;
    }

    public function openEdit(User $user): void
    {
        $this->dispatch('edit-user', userId: $user->id);
    }

    public function confirmPromote(User $user): void
    {
        $this->roleChangeUserId = $user->id;
        $this->roleChangeTo = Role::Admin->value;
        $this->showRoleModal = true;
    }

    public function confirmDemote(User $user): void
    {
        $this->roleChangeUserId = $user->id;
        $this->roleChangeTo = Role::Member->value;
        $this->showRoleModal = true;
    }

    public function changeRoleConfirmed(ChangeUserRole $changeUserRole): void
    {
        $user = $this->roleChangeUser();

        if ($user === null) {
            Flux::toast(variant: 'danger', text: __('Unable to change the user role.'));
            $this->reset('showRoleModal', 'roleChangeUserId', 'roleChangeTo');

            return;
        }

        if ($this->roleChangeTo === Role::Admin->value) {
            $this->promote($user, $changeUserRole);
        } elseif ($this->roleChangeTo === Role::Member->value) {
            $this->demote($user, $changeUserRole);
        } else {
            Flux::toast(variant: 'danger', text: __('Unable to change the user role.'));
        }

        $this->reset('showRoleModal', 'roleChangeUserId', 'roleChangeTo');
    }

    public function promote(User $user, ChangeUserRole $changeUserRole): void
    {
        $actor = Auth::user();

        if (! $actor instanceof User || ! $changeUserRole->promote($user, $actor)) {
            Flux::toast(variant: 'danger', text: __('Unable to promote the user to Admin.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('User promoted to Admin.'));
    }

    public function demote(User $user, ChangeUserRole $changeUserRole): void
    {
        $actor = Auth::user();

        if (! $actor instanceof User || ! $changeUserRole->demote($user, $actor)) {
            Flux::toast(variant: 'danger', text: __('Unable to demote the user to Member.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('User demoted to Member.'));
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
        $actor = Auth::user();

        abort_unless($actor instanceof User && $actor->role->canManageUsers(), 403);
        abort_if($user->is($actor), 403);
        abort_if($user->role->isOwner() && ! $actor->role->isOwner(), 403);

        $user->delete();
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('User removed.'));
    }

    /**
     * @return array<int, Role>
     */
    #[Computed]
    public function roles(): array
    {
        $actor = Auth::user();

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

    #[Computed]
    public function roleChangeUser(): ?User
    {
        return $this->roleChangeUserId === null
            ? null
            : User::find($this->roleChangeUserId);
    }

    #[Computed]
    public function roleChangeIsPromotion(): bool
    {
        return $this->roleChangeTo === Role::Admin->value;
    }

    #[On('user-updated')]
    public function refreshUsers(): void
    {
        //
    }

    public function updatedPage(): void
    {
        $this->selected = [];
    }

    public function sortByCreatedAt(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    public function deleteSelected(): void
    {
        $actor = Auth::user();

        abort_unless($actor instanceof User && $actor->role->canManageUsers(), 403);

        $users = User::query()->whereKey($this->selected)->get();

        foreach ($users as $user) {
            abort_if(
                $user->is($actor) || ($user->role->isOwner() && ! $actor->role->isOwner()),
                403,
            );
        }

        $deleted = false;

        try {
            if ($users->isNotEmpty()) {
                DB::transaction(fn () => $users->each->delete());

                $deleted = true;
            }
        } catch (Throwable) {
            $deleted = false;
        }

        $this->reset('selected');
        $this->selectionKey++;
        $this->resetPage();

        if (! $deleted) {
            Flux::toast(variant: 'danger', text: __('Failed to delete the selected users.'));

            return;
        }

        Flux::toast(
            variant: 'success',
            text: __(':count users deleted successfully.', ['count' => $users->count()]),
        );
    }

    public function render(): View
    {
        return view('livewire.users.index', [
            'users' => User::query()
                ->orderBy('created_at', $this->sortDirection)
                ->orderBy('id')
                ->paginate(10),
        ]);
    }
}
