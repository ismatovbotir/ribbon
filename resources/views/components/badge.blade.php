@props(['variant' => 'muted', 'dot' => false])

@php
    $variants = [
        'success' => 'bg-success-50 text-success-700 border-success-200',
        'warning' => 'bg-warning-50 text-warning-700 border-warning-200',
        'danger' => 'bg-danger-50 text-danger-700 border-danger-200',
        'muted' => 'bg-muted-50 text-muted-700 border-muted-200',
        'info' => 'bg-info-50 text-info-700 border-info-200',
        // Storefront brand-partner badge (spec 18 2.4)
        'brand' => 'h-6 max-w-[11rem] border-(--color-brand-badge-border) bg-(--color-brand-badge-bg) px-2 text-(--color-brand-badge-text)',
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

<span {{ $attributes->merge(['class' => "inline-flex h-6 items-center gap-1.5 rounded-pill border px-2 text-xs font-medium whitespace-nowrap $classes"]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-pill {{ $dotColors[$variant] ?? $dotColors['muted'] }}"></span>
    @endif
    {{ $slot }}
</span>
