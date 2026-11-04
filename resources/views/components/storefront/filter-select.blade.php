@props(['parameter', 'facetCounts' => [], 'selectedOptionIds' => [], 'variant' => 'sidebar', 'first' => false])

@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
    $sheet = $variant === 'sheet';
    $selectedStr = is_array($selectedOptionIds) ? array_map('strval', array_filter($selectedOptionIds, 'is_scalar')) : [];
    $options = $parameter->options->values();
    $limit = $sheet ? 8 : 6;
    $open = $first || count($selectedStr) > 0 || ! $sheet;
    $uid = ($sheet ? 'fg-' : 'fs-').$parameter->id;
    $optionLabel = fn ($option) => $option->value[$locale] ?? ($option->value[$defaultLocale] ?? '');
@endphp

<details id="{{ $uid }}" @if ($open) open @endif class="group border-b border-border {{ $sheet ? '' : 'last:border-b-0' }}">
    <summary class="flex cursor-pointer items-center justify-between font-semibold text-text-primary {{ $sheet ? 'h-14 text-base' : 'h-11 px-4 text-sm' }}">
        <span>{{ $parameter->name[$locale] ?? ($parameter->name[$defaultLocale] ?? '') }}</span>
        <x-storefront.icon name="chevron-down" class="size-4 text-text-muted group-open:rotate-180" stroke="2" />
    </summary>
    <div class="pb-2">
        @foreach ($options as $option)
            @if ($loop->index === $limit)
                <details class="group/more">
                    <summary class="flex min-h-11 cursor-pointer items-center text-sm font-medium text-accent-700 hover:underline {{ $sheet ? '' : 'px-4 dense:min-h-9' }} group-open/more:hidden">{{ __('storefront.catalog.filters_show_all', ['count' => $options->count()]) }}</summary>
            @endif
            @php
                $count = $facetCounts[$option->id] ?? 0;
                $isChecked = in_array((string) $option->id, $selectedStr, true);
                $isDisabled = $count === 0 && ! $isChecked;
            @endphp
            <label class="flex items-center gap-3 {{ $sheet ? 'min-h-12 border-t border-border first:border-t-0' : 'min-h-11 px-4 text-sm dense:min-h-9' }} {{ $isDisabled ? 'cursor-not-allowed text-text-muted' : 'cursor-pointer text-text-primary hover:bg-surface-hover' }}">
                <input
                    type="checkbox"
                    value="{{ $option->id }}"
                    wire:model.live="selected.{{ $parameter->id }}"
                    @disabled($isDisabled)
                    class="shrink-0 appearance-none rounded-xs border border-border-input bg-surface bg-center bg-no-repeat checked:border-accent-700 checked:bg-accent-700 checked:field-check-mark disabled:cursor-not-allowed disabled:bg-surface-sunken {{ $sheet ? 'size-5' : 'size-4' }}"
                >
                <span class="{{ $sheet ? 'text-base' : '' }}">{{ $optionLabel($option) }}</span>
                <span class="ml-auto text-text-muted tabular-nums {{ $sheet ? 'text-sm' : 'text-xs' }}">{{ $count }}</span>
            </label>
            @if ($loop->last && $options->count() > $limit)
                </details>
            @endif
        @endforeach
    </div>
</details>
