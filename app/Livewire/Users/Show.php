<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Actions\Users\ChangeUserRole;
use App\Enums\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    public User $user;

    public bool $showRoleModal = false;

    public ?int $roleChangeTo = null;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function openEdit(): void
    {
        $this->dispatch('edit-user', userId: $this->user->id);
    }

    public function confirmPromote(): void
    {
        $this->roleChangeTo = Role::Admin->value;
        $this->showRoleModal = true;
    }

    public function confirmDemote(): void
    {
        $this->roleChangeTo = Role::Member->value;
        $this->showRoleModal = true;
    }

    public function changeRoleConfirmed(ChangeUserRole $changeUserRole): void
    {
        $actor = Auth::user();

        if (! \in_array($this->roleChangeTo, [Role::Admin->value, Role::Member->value], true) || ! $actor instanceof User) {
            Flux::toast(variant: 'danger', text: __('Unable to change the user role.'));
            $this->reset('showRoleModal', 'roleChangeTo');

            return;
        }

        $promoted = $this->roleChangeTo === Role::Admin->value;

        $changed = $promoted
            ? $changeUserRole->promote($this->user, $actor)
            : $changeUserRole->demote($this->user, $actor);

        if ($changed) {
            $this->user->refresh();

            Flux::toast(
                variant: 'success',
                text: $promoted ? __('User promoted to Admin.') : __('User demoted to Member.'),
            );
        } else {
            Flux::toast(
                variant: 'danger',
                text: $promoted ? __('Unable to promote the user to Admin.') : __('Unable to demote the user to Member.'),
            );
        }

        $this->reset('showRoleModal', 'roleChangeTo');
    }

    #[On('user-updated')]
    public function refreshUser(): void
    {
        $this->user->refresh();
    }

    #[Computed]
    public function roleChangeIsPromotion(): bool
    {
        return $this->roleChangeTo === Role::Admin->value;
    }

    public function render(): View
    {
        return view('livewire.users.show')->title(__('User profile'));
    }
}
