{{-- Menu item. Renders <a> when `href` is set, otherwise <button>. `destructive` adds the rule + red text.
     `icon` = a name from the panel icon set (<x-icon>), shown before the label.
     When `disabled`, pass `reason` for the visible explanation (no tooltips on touch). --}}
@props(['href' => null, 'destructive' => false, 'disabled' => false, 'reason' => null, 'icon' => null])

@php
    // Icons are muted next to normal items and inherit the red of destructive ones.
    $iconClass = 'size-5 shrink-0 '.($disabled || $destructive ? '' : 'text-text-muted');
    $classes = 'flex min-h-12 w-full items-center gap-3 px-4 text-left text-sm dense:min-h-9 dense:px-3 '
        .($disabled ? 'cursor-not-allowed text-text-muted' : ($destructive ? 'text-danger-700 hover:bg-danger-50 focus-visible:bg-danger-50' : 'text-text-primary hover:bg-surface-hover focus-visible:bg-surface-hover'));
@endphp

@if ($destructive)
    <div role="separator" class="my-1 border-t border-border"></div>
@endif

@if ($href && ! $disabled)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->merge(['class' => $classes]) }}>@if ($icon)<x-icon :name="$icon" :class="$iconClass" />@endif<span>{{ $slot }}</span></a>
@else
    <button type="button" role="menuitem" @disabled($disabled) {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" :class="$iconClass" />@endif
        <span class="flex min-w-0 flex-col">
            <span>{{ $slot }}</span>
            @if ($disabled && $reason)
                <span class="text-xs">{{ $reason }}</span>
            @endif
        </span>
    </button>
@endif
