@props(['variant' => 'secondary', 'size' => 'md', 'tag' => 'button'])

@php
    $variants = [
        'primary' => 'border-accent-700 bg-accent-700 in-[.storefront]:font-semibold text-white hover:bg-accent-800',
        // Storefront-only primary CTA: label yellow + graphite text (11.6:1). Never used in admin/seller panels.
        'cta' => 'border-(--color-cta-border) bg-cta font-semibold text-cta-text hover:border-cta-hover hover:bg-cta-hover active:border-cta-pressed active:bg-cta-pressed',
        'secondary' => 'border-border-input bg-surface text-text-primary hover:bg-surface-hover',
        'ghost' => 'border-transparent bg-transparent text-text-secondary hover:bg-surface-hover',
        'danger' => 'border-transparent bg-transparent text-danger-700 hover:bg-danger-50',
        'danger-solid' => 'border-danger-600 bg-danger-600 text-white hover:bg-danger-700',
    ];

    $sizes = [
        'sm' => 'h-control-sm px-3 text-xs dense:px-2.5',
        'md' => 'h-control px-4 text-sm dense:px-3',
        // Storefront main CTA height (spec 18 2.1); inert in the panels.
        'lg' => 'h-cta px-6 text-base',
    ];

    $classes = 'inline-flex items-center justify-center gap-1.5 rounded-sm border font-medium whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:cursor-not-allowed aria-disabled:opacity-50 '
        .($variants[$variant] ?? $variants['secondary']).' '
        .($sizes[$size] ?? $sizes['md']);
    $tag = $tag ?: 'button';
@endphp

@if ($tag === 'a')
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
