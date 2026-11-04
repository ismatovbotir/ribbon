{{-- Rectangular switch. Mobile: label left / switch right (settings-style row). Dense: switch left of label. --}}
@props(['label' => null, 'caption' => null])

<label class="flex min-h-11 w-full cursor-pointer items-start justify-between gap-4 select-none dense:min-h-0 dense:w-auto dense:justify-start dense:gap-2.5">
    @if ($label)
        <span class="order-1 flex min-w-0 flex-col self-center dense:order-2 dense:self-auto">
            <span class="text-sm text-text-primary">{{ $label }}</span>
            @if ($caption)
                <span class="text-xs text-text-muted">{{ $caption }}</span>
            @endif
        </span>
    @endif
    <span class="relative order-2 mt-0.5 inline-flex h-6 w-11 shrink-0 items-center self-center rounded-pill border border-border-input bg-surface-sunken transition-colors has-checked:border-accent-700 has-checked:bg-accent-700 has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-focus-ring dense:order-1 dense:self-auto">
        <input type="checkbox" role="switch" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
        <span class="absolute left-0.5 size-4 rounded-pill bg-switch-off transition-transform duration-100 motion-reduce:transition-none peer-checked:translate-x-5 peer-checked:bg-white"></span>
    </span>
</label>
