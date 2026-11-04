{{-- Maps moderation (pending|approved|rejected|suspended) and offer
     (pending|contacted|fulfilled|cancelled) statuses to a badge with a redundant icon. --}}
@props(['status', 'type' => 'moderation'])

@php
    $map = $type === 'offer'
        ? [
            'pending' => ['warning', 'New', 'clock'],
            'contacted' => ['info', 'Contacted', 'phone'],
            'fulfilled' => ['success', 'Fulfilled', 'check'],
            'cancelled' => ['muted', 'Cancelled', 'x'],
        ]
        : [
            'pending' => ['warning', 'Pending', 'clock'],
            'approved' => ['success', 'Approved', 'check'],
            'rejected' => ['danger', 'Rejected', 'x'],
            'suspended' => ['muted', 'Suspended', 'pause'],
        ];
    [$variant, $label, $icon] = $map[$status] ?? ['muted', ucfirst((string) $status), 'clock'];
    $icons = ['clock' => 'time', 'check' => 'success', 'x' => 'reject', 'pause' => 'pause', 'phone' => 'phone'];
@endphp

<x-badge :variant="$variant" {{ $attributes }}>
    <x-icon :name="$icons[$icon]" class="size-3.5" />
    {{ trim((string) $slot) !== '' ? $slot : $label }}
</x-badge>
