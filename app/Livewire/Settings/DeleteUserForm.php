<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Concerns\PasswordValidationRules;
use App\Enums\Role;
use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        /** @var User $user */
        $user = Auth::user();

        DB::transaction(function () use ($user, $logout): void {
            $ownerIds = User::query()
                ->where('role', Role::Owner)
                ->lockForUpdate()
                ->pluck('id');

            if ($user->role->isOwner() && $ownerIds->count() === 1) {
                throw ValidationException::withMessages([
                    'password' => __('The last owner cannot delete their account.'),
                ]);
            }

            tap($user, $logout(...))->delete();
        });

        $this->redirect('/', navigate: true);
    }
}
