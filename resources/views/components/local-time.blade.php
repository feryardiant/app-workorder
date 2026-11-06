@props(['date' => null, 'format' => 'd M Y'])

@php($timezone = auth()->user()?->timezone ?? 'UTC')

{{ $date?->timezone($timezone)?->translatedFormat($format) ?? '—' }}
