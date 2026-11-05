<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailForNewUser extends VerifyEmail
{
    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => __('Verify your email address'),
            'greeting' => __('Hello!'),
            'description' => __('Please click the button below to verify your email address.'),
            'lines' => [__('If you did not create an account, no further action is required.')],
            'action' => [
                'label' => __('Verify email address'),
                'url' => $this->verificationUrl($notifiable),
            ],
            'salutation' => __('Regards, :app', ['app' => config('app.name')]),
        ];
    }

    /**
     * Use the public verification link, which ends on the reset password page.
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify.new',
            Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}
