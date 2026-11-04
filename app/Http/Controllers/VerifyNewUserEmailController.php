<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class VerifyNewUserEmailController extends Controller
{
    /**
     * Verify the email address and send the user to the reset password page.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = User::findOrFail((int) $request->route('id'));

        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash')),
            403,
        );

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->route('password.reset', [
            'token' => Password::broker()->createToken($user),
            'email' => $user->email,
        ]);
    }
}
