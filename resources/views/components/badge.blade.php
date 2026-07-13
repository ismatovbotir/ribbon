@props(['variant' => 'muted', 'dot' => false])

@php
    $variants = [
        'success' => 'bg-success-50 text-success-700 border-success-200',
        'warning' => 'bg-warning-50 text-warning-700 border-warning-200',
        'danger' => 'bg-danger-50 text-danger-700 border-danger-200',
        'muted' => 'bg-muted-50 text-muted-700 border-muted-200',
        'info' => 'bg-info-50 text-info-700 border-info-200',
    ];

    $dotColors = [
        'success' => 'bg-success-600',
        'warning' => 'bg-warning-600',
        'danger' => 'bg-danger-600',
        'muted' => 'bg-muted-600',
        'info' => 'bg-info-600',
    ];

    $classes = $variants[$variant] ?? $variants['muted'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap $classes"]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $dotColors[$variant] ?? $dotColors['muted'] }}"></span>
    @endif
    {{ $slot }}
</span>
