<section
    class="w-full"
    x-data="{
        locale: @js($locale),
        timezone: @js($timezone),
        async save(key, value) {
            try {
                const response = await fetch(@js(route('preferences.update')), {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': @js(csrf_token()),
                    },
                    body: JSON.stringify({ [key]: value }),
                });

                if (! response.ok) {
                    const data = await response.json().catch(() => ({}));

                    throw new Error(data.message ?? @js(__('Failed to update preferences.')));
                }

                window.Flux.toast({
                    variant: 'success',
                    text: @js(__('Your preferences have been updated. Reload to apply them.')),
                    duration: 0,
                    link: {
                        label: @js(__('Reload page')),
                        href: window.location.href,
                    },
                });
            } catch (error) {
                window.Flux.toast({
                    variant: 'danger',
                    text: error.message,
                });
            }
        },
    }"
>
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Preferences settings') }}</flux:heading>

    <x-settings.layout :heading="__('Preferences')" :subheading="__('Manage your appearance and localization preferences')">
        <div class="my-6 space-y-8">
            <section class="space-y-3">
                <div>
                    <flux:heading id="appearance-heading" size="lg">{{ __('Appearance') }}</flux:heading>
                    <flux:subheading id="appearance-description">{{ __('Update the appearance settings for your account') }}</flux:subheading>
                </div>

                <flux:radio.group
                    x-data
                    variant="segmented"
                    x-model="$flux.appearance"
                    aria-labelledby="appearance-heading"
                    aria-describedby="appearance-description"
                >
                    <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                    <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                    <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
                </flux:radio.group>
            </section>

            <section class="space-y-3">
                <div>
                    <flux:heading id="locale-heading" size="lg">{{ __('Locale') }}</flux:heading>
                    <flux:subheading id="locale-description">{{ __('Choose your preferred language') }}</flux:subheading>
                </div>

                <flux:radio.group
                    x-data
                    variant="segmented"
                    x-model="locale"
                    x-on:change="save('locale', $event.target.value)"
                    aria-labelledby="locale-heading"
                    aria-describedby="locale-description"
                >
                    @foreach ($locales as $option)
                        <flux:radio :value="$option->value">{{ $option->label() }}</flux:radio>
                    @endforeach
                </flux:radio.group>
            </section>

            <section class="space-y-3">
                <div>
                    <flux:heading id="timezone-heading" size="lg">{{ __('Timezone') }}</flux:heading>
                    <flux:subheading id="timezone-description">{{ __('Choose your local timezone') }}</flux:subheading>
                </div>

                <flux:select
                    x-model="timezone"
                    x-on:change="save('timezone', $event.target.value)"
                    aria-labelledby="timezone-heading"
                    aria-describedby="timezone-description"
                >
                    @foreach ($timezones as $timezone)
                        <option value="{{ $timezone }}">{{ $timezone }}</option>
                    @endforeach
                </flux:select>
            </section>
        </div>
    </x-settings.layout>
</section>
