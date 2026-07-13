@props(['label' => null, 'caption' => null])

<label class="inline-flex cursor-pointer items-start gap-2.5 select-none">
    <span class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 items-center rounded-full border border-border-strong bg-surface-sunken transition-colors has-[:checked]:border-accent-600 has-[:checked]:bg-accent-600">
        <input type="checkbox" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
        <span class="absolute left-0.5 h-3.5 w-3.5 rounded-full bg-white shadow-xs transition-transform peer-checked:translate-x-4"></span>
    </span>

    @if ($label)
        <span class="flex flex-col">
            <span class="text-sm text-text-primary">{{ $label }}</span>
            @if ($caption)
                <span class="text-xs text-text-muted">{{ $caption }}</span>
            @endif
        </span>
    @endif
</label>
