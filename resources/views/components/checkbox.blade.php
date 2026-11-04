{{-- Square checkbox. `label` is visible text; pass `sr-only` => true for table select cells. --}}
@props(['label' => null, 'srOnly' => false])

<label class="flex min-h-11 items-center gap-3 dense:min-h-8">
    <input type="checkbox" {{ $attributes->merge(['class' => 'size-5 shrink-0 appearance-none rounded-xs border border-border-input bg-surface bg-center bg-no-repeat checked:border-accent-700 checked:bg-accent-700 checked:field-check-mark indeterminate:border-accent-700 indeterminate:bg-accent-700 indeterminate:field-dash-mark']) }}>
    @if ($label)
        <span class="{{ $srOnly ? 'sr-only' : 'text-sm text-text-primary' }}">{{ $label }}</span>
    @endif
</label>
