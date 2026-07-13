@props(['parameter', 'live' => true])

@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
@endphp

<div class="mb-4 border-b border-border pb-4 last:mb-0 last:border-b-0 last:pb-0">
    <p class="mb-2 text-sm font-semibold text-text-primary">
        {{ $parameter->name[$locale] ?? ($parameter->name[$defaultLocale] ?? '') }}
        @if ($parameter->unit)
            <span class="font-normal text-text-muted">({{ $parameter->unit }})</span>
        @endif
    </p>
    <div class="flex items-center gap-2">
        <input
            type="number"
            inputmode="decimal"
            placeholder="{{ __('storefront.catalog.filters_min') }}"
            @if ($live) wire:model.live.blur @else wire:model.blur @endif="numMin.{{ $parameter->id }}"
            class="h-8 w-full rounded-sm border border-border px-2 text-sm text-text-primary focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none"
        >
        <span class="text-text-muted" aria-hidden="true">–</span>
        <input
            type="number"
            inputmode="decimal"
            placeholder="{{ __('storefront.catalog.filters_max') }}"
            @if ($live) wire:model.live.blur @else wire:model.blur @endif="numMax.{{ $parameter->id }}"
            class="h-8 w-full rounded-sm border border-border px-2 text-sm text-text-primary focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none"
        >
    </div>
</div>
