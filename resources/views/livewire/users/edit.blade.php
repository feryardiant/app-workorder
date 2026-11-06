<div>
    <flux:modal
        wire:model.self="showModal"
        flyout
        position="right"
        variant="floating"
        class="md:w-96"
        x-init="$el.querySelector('dialog')?.setAttribute('aria-labelledby', 'edit-user-heading')"
    >
        <form wire:submit="update" class="flex min-h-[calc(100dvh-5rem)] flex-col">
            <div class="space-y-6">
                <div>
                    <flux:heading id="edit-user-heading" size="lg">{{ __('Edit user') }}</flux:heading>
                    <flux:subheading>{{ $user?->name }}</flux:subheading>
                </div>

                <flux:field>
                    <flux:label>{{ __('Name') }}</flux:label>
                    <flux:input wire:model="name" type="text" required autofocus autocomplete="name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Handle') }}</flux:label>
                    <flux:input wire:model="handle" type="text" required autocomplete="username" />
                    <flux:description>{{ __('4-15 lowercase letters and numbers.') }}</flux:description>
                    <flux:error name="handle" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input wire:model="email" type="email" required autocomplete="email" />
                    <flux:error name="email" />
                </flux:field>
            </div>

            <div class="mt-auto flex items-center justify-between pt-6">
                <flux:modal.close>
                    <flux:button variant="subtle">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
