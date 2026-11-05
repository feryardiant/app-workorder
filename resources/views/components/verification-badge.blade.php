@props(['user'])

@php($verifiedAt = $user->email_verified_at?->translatedFormat('d M Y'))

<flux:tooltip :content="$verifiedAt ?? '—'">
    <flux:badge
        size="sm"
        :color="$verifiedAt ? 'lime' : 'zinc'"
        :class="$verifiedAt ? 'dark:text-lime-100!' : ''"
    >
        {{ $verifiedAt ? __('Verified') : __('Waiting') }}
    </flux:badge>
</flux:tooltip>
