<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\Locale;
use App\Models\User;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Preferences extends Component
{
    public string $locale = '';

    public string $timezone = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $locale = $user->locale;

        $this->locale = $locale === null
            ? (string) config('app.default_locale')
            : $locale->value;

        $this->timezone = $user->timezone ?? 'UTC';
    }

    public function render(): View
    {
        return view('livewire.settings.preferences', [
            'locales' => Locale::cases(),
            'timezones' => DateTimeZone::listIdentifiers(DateTimeZone::ALL),
        ])->title(__('Preferences settings'));
    }
}
