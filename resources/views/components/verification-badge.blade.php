@props(['user'])

@php($verifiedAt = $user->email_verified_at?->toFormattedDateString())

<flux:tooltip :content="$verifiedAt ?? '—'">
    <flux:badge size="sm" :color="$verifiedAt ? 'lime' : 'zinc'">
        {{ $verifiedAt ? __('Verified') : __('Waiting') }}
    </flux:badge>
</flux:tooltip>
