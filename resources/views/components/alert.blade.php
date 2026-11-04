@props(['variant' => 'info'])

@php
    $v = [
        'success' => 'border-success-200 border-l-success-600 bg-success-50 text-success-700',
        'warning' => 'border-warning-200 border-l-warning-600 bg-warning-50 text-warning-700',
        'danger' => 'border-danger-200 border-l-danger-600 bg-danger-50 text-danger-700',
        'info' => 'border-info-200 border-l-info-600 bg-info-50 text-info-700',
    ][$variant] ?? 'border-info-200 border-l-info-600 bg-info-50 text-info-700';
@endphp

<div role="{{ $variant === 'danger' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => "mb-4 flex gap-3 rounded-md border border-l-[3px] p-3 text-sm $v"]) }}>
    <x-icon :name="['success' => 'success', 'warning' => 'warning-alt', 'danger' => 'error'][$variant] ?? 'info'" class="mt-0.5 size-4" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
    @isset($action)
        <div class="shrink-0">{{ $action }}</div>
    @endisset
</div>
