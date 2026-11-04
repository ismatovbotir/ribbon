@props(['parameter', 'variant' => 'sidebar'])

@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
    $sheet = $variant === 'sheet';
    $uid = ($sheet ? 'fg-' : 'fs-').$parameter->id;
    $inputClass = 'font-mono tabular-nums '.($sheet ? '' : 'h-9 text-sm');
@endphp

<details id="{{ $uid }}" open class="group border-b border-border {{ $sheet ? '' : 'last:border-b-0' }}">
    <summary class="flex cursor-pointer items-center justify-between font-semibold text-text-primary {{ $sheet ? 'h-14 text-base' : 'h-11 px-4 text-sm' }}">
        <span>
            {{ $parameter->name[$locale] ?? ($parameter->name[$defaultLocale] ?? '') }}
            @if ($parameter->unit)
                <span class="font-normal text-text-muted">({{ $parameter->unit }})</span>
            @endif
        </span>
        <x-storefront.icon name="chevron-down" class="size-4 text-text-muted group-open:rotate-180" stroke="2" />
    </summary>
    <div class="grid grid-cols-2 gap-2 pb-3 {{ $sheet ? '' : 'px-4' }}">
        <x-input type="number" inputmode="decimal" aria-label="{{ ($parameter->name[$locale] ?? '').' - '.__('storefront.catalog.filters_min') }}" placeholder="{{ __('storefront.catalog.filters_min') }}" wire:model.live.blur="numMin.{{ $parameter->id }}" class="{{ $inputClass }}" />
        <x-input type="number" inputmode="decimal" aria-label="{{ ($parameter->name[$locale] ?? '').' - '.__('storefront.catalog.filters_max') }}" placeholder="{{ __('storefront.catalog.filters_max') }}" wire:model.live.blur="numMax.{{ $parameter->id }}" class="{{ $inputClass }}" />
    </div>
</details>
