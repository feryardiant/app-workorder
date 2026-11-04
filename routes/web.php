<?php

declare(strict_types=1);

use App\Http\Controllers\VerifyNewUserEmailController;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Security;
use App\Livewire\Users\Index;
use App\Livewire\Users\Show;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'dashboard');

Route::get('email/verify-new/{id}/{hash}', VerifyNewUserEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify.new');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('users', Index::class)->name('users.index');
    Route::livewire('users/{user}', Show::class)->name('users.show');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', Profile::class)->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', Appearance::class)->name('appearance.edit');

    Route::livewire('settings/security', Security::class)
        ->middleware(['password.confirm'])
        ->name('security.edit');
});
