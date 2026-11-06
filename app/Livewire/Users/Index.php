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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * @property-read LengthAwarePaginator<int, User> $users
 */
class Index extends Component
{
    use ProfileValidationRules, WithPagination;

    public bool $showCreateModal = false;

    public bool $showDeleteModal = false;

    public bool $showBatchDeleteModal = false;

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

    public bool $allSelected = false;

    public bool $someSelected = false;

    /**
     * @var 'asc'|'desc'
     */
    public string $sortDirection = 'desc';

    /**
     * Create a new user and send a verification email.
     */
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

        $user = $this->createUserRecord([
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

    /**
     * Create a user and retry when a generated handle loses a race.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createUserRecord(array $attributes): User
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return User::create($attributes);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }

                $attributes['handle'] = User::generateHandle(Str::random(8));
            }
        }

        throw new \RuntimeException('Unable to create user.');
    }

    public function confirmDelete(int $userId): void
    {
        $this->deletingUserId = $userId;
        $this->showDeleteModal = true;
    }

    public function confirmDeleteSelected(): void
    {
        $this->showBatchDeleteModal = true;
    }

    public function deleteSelectedConfirmed(): void
    {
        $this->deleteSelected();

        $this->reset('showBatchDeleteModal');
    }

    public function openEdit(int $userId): void
    {
        $this->dispatch('edit-user', userId: $userId);
    }

    /**
     * Resend the verification email to the given user.
     */
    public function resendVerification(int $userId): void
    {
        $user = User::findOrFail($userId);

        $actor = Auth::user();

        abort_unless($actor instanceof User && $actor->role->canManageUsers(), 403);
        abort_if($user->hasVerifiedEmail(), 422);
        abort_if($user->role->isOwner() && ! $actor->role->isOwner(), 403);

        $user->notify(new VerifyEmailForNewUser);

        Flux::toast(
            variant: 'success',
            text: __('Verification email resent to :email.', ['email' => $user->email]),
        );
    }

    public function confirmPromote(int $userId): void
    {
        $this->roleChangeUserId = $userId;
        $this->roleChangeTo = Role::Admin->value;
        $this->showRoleModal = true;
    }

    public function confirmDemote(int $userId): void
    {
        $this->roleChangeUserId = $userId;
        $this->roleChangeTo = Role::Member->value;
        $this->showRoleModal = true;
    }

    /**
     * Apply the confirmed role change.
     */
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

    /**
     * Delete the user awaiting confirmation.
     */
    public function deleteConfirmed(): void
    {
        $user = $this->deletingUser();

        abort_if($user === null, 404);

        $this->deleteUser($user->id);

        $this->reset('showDeleteModal', 'deletingUserId');
    }

    /**
     * Delete the given user.
     */
    public function deleteUser(int $userId): void
    {
        $user = User::findOrFail($userId);

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
        $this->allSelected = false;
        $this->someSelected = false;
    }

    public function updatedSelected(): void
    {
        $pageIds = $this->selectablePageIds();
        $selected = array_map('intval', $this->selected);

        $this->allSelected = $pageIds !== [] && array_diff($pageIds, $selected) === [];
        $this->someSelected = ! $this->allSelected && array_intersect($pageIds, $selected) !== [];
    }

    public function toggleSelectAll(): void
    {
        $pageIds = $this->selectablePageIds();
        $selected = array_map('intval', $this->selected);

        $this->selected = array_diff($pageIds, $selected) === [] ? [] : $pageIds;
        $this->selectionKey++;
        $this->updatedSelected();
    }

    public function sortByCreatedAt(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    /**
     * Delete the selected users.
     */
    public function deleteSelected(): void
    {
        $actor = Auth::user();

        abort_unless($actor instanceof User && $actor->role->canManageUsers(), 403);

        $pageIds = $this->users->pluck('id')->all();
        $selected = array_values(array_intersect(array_map('intval', $this->selected), $pageIds));

        $users = User::query()->whereKey($selected)->get();

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
        } catch (Throwable $exception) {
            report($exception);

            $deleted = false;
        }

        if (! $deleted) {
            Flux::toast(variant: 'danger', text: __('Failed to delete the selected users.'));

            return;
        }

        $this->reset('selected', 'allSelected', 'someSelected');
        $this->selectionKey++;
        $this->resetPage();
        unset($this->users);

        Flux::toast(
            variant: 'success',
            text: __(':count users deleted successfully.', ['count' => $users->count()]),
        );
    }

    public function render(): View
    {
        return view('livewire.users.index', [
            'users' => $this->users,
        ])->title(__('Users'));
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->orderBy('created_at', $this->sortDirection)
            ->orderBy('id')
            ->paginate(10);
    }

    /**
     * @return array<int, int>
     */
    private function selectablePageIds(): array
    {
        $actor = Auth::user();

        if (! $actor instanceof User || ! $actor->role->canManageUsers()) {
            return [];
        }

        return $this->users
            ->filter(fn (User $user): bool => ! $user->is($actor) && ($actor->role->isOwner() || ! $user->role->isOwner()))
            ->pluck('id')
            ->all();
    }
}
