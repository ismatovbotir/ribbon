{{-- Icon-only button. Always pass aria-label. Slot = the <svg>. --}}
@props(['tag' => 'button', 'dense' => true])

@php
    $classes = 'inline-flex size-11 shrink-0 items-center justify-center rounded-sm text-text-secondary hover:bg-surface-hover hover:text-text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring '.($dense ? 'dense:size-8' : '');
@endphp

@if ($tag === 'a')
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
