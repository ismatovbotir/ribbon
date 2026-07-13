@props(['variant' => 'secondary', 'size' => 'md', 'tag' => 'button'])

@php
    $variants = [
        'primary' => 'border-accent-600 bg-accent-600 text-white hover:bg-accent-700',
        'secondary' => 'border-border-strong bg-surface text-text-primary hover:bg-surface-hover',
        'ghost' => 'border-transparent bg-transparent text-text-secondary hover:bg-surface-hover',
        'danger' => 'border-transparent bg-transparent text-danger-600 hover:bg-danger-50',
        'danger-solid' => 'border-danger-600 bg-danger-600 text-white hover:bg-danger-700',
    ];

    $sizes = [
        'sm' => 'h-7 px-2.5 text-xs',
        'md' => 'h-8 px-3 text-sm',
    ];

    $classes = 'inline-flex items-center justify-center gap-1.5 rounded-sm border font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50 '
        .($variants[$variant] ?? $variants['secondary']).' '
        .($sizes[$size] ?? $sizes['md']);
    $tag = $tag ?: 'button';
@endphp

@if ($tag === 'a')
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
