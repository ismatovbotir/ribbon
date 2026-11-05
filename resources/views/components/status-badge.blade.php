{{-- Maps moderation (pending|approved|rejected|suspended) and offer
     (pending|contacted|fulfilled|cancelled) statuses to a badge with a redundant icon. --}}
@props(['status', 'type' => 'moderation'])

@php
    $map = $type === 'offer'
        ? [
            'pending' => ['warning', __('admin.ui.status_new'), 'clock'],
            'contacted' => ['info', __('admin.ui.status_contacted'), 'phone'],
            'fulfilled' => ['success', __('admin.ui.status_fulfilled'), 'check'],
            'cancelled' => ['muted', __('admin.ui.status_cancelled'), 'x'],
        ]
        : [
            'pending' => ['warning', __('admin.ui.status_pending'), 'clock'],
            'approved' => ['success', __('admin.ui.status_approved'), 'check'],
            'rejected' => ['danger', __('admin.ui.status_rejected'), 'x'],
            'suspended' => ['muted', __('admin.ui.status_suspended'), 'pause'],
        ];
    [$variant, $label, $icon] = $map[$status] ?? ['muted', ucfirst((string) $status), 'clock'];
    $icons = ['clock' => 'time', 'check' => 'success', 'x' => 'reject', 'pause' => 'pause', 'phone' => 'phone'];
@endphp

<x-badge :variant="$variant" {{ $attributes }}>
    <x-icon :name="$icons[$icon]" class="size-3.5" />
    {{ trim((string) $slot) !== '' ? $slot : $label }}
</x-badge>
