@props(['brandFacets' => [], 'selectedIds' => [], 'variant' => 'sidebar'])

{{-- Brand filter group (same pattern as filter-select). Writes the Catalog `brands` array (?brand[]=). --}}
@php
    $sheet = $variant === 'sheet';
    $selectedStr = array_map('strval', array_filter((array) $selectedIds));
    $limit = $sheet ? 8 : 6;
    $open = count($selectedStr) > 0 || ! $sheet;
    $uid = ($sheet ? 'fg-' : 'fs-').'brand';
@endphp

<details id="{{ $uid }}" @if ($open) open @endif class="group border-b border-border {{ $sheet ? '' : 'last:border-b-0' }}">
    <summary class="flex cursor-pointer items-center justify-between font-semibold text-text-primary {{ $sheet ? 'h-14 text-base' : 'h-11 px-4 text-sm' }}">
        <span>{{ __('storefront.catalog.filter_brand') }}</span>
        <x-storefront.icon name="chevron-down" class="size-4 text-text-muted group-open:rotate-180" stroke="2" />
    </summary>
    <div class="pb-2">
        @foreach ($brandFacets as $brand)
            @if ($loop->index === $limit)
                <details class="group/more">
                    <summary class="flex min-h-11 cursor-pointer items-center text-sm font-medium text-accent-700 hover:underline {{ $sheet ? '' : 'px-4 dense:min-h-9' }} group-open/more:hidden">{{ __('storefront.catalog.filters_show_all', ['count' => count($brandFacets)]) }}</summary>
            @endif
            @php
                $isChecked = in_array((string) $brand['id'], $selectedStr, true);
                $isDisabled = $brand['count'] === 0 && ! $isChecked;
            @endphp
            <label class="flex items-center gap-3 {{ $sheet ? 'min-h-12 border-t border-border first:border-t-0' : 'min-h-11 px-4 text-sm dense:min-h-9' }} {{ $isDisabled ? 'cursor-not-allowed text-text-muted' : 'cursor-pointer text-text-primary hover:bg-surface-hover' }}">
                <input
                    type="checkbox"
                    value="{{ $brand['id'] }}"
                    wire:model.live="brands"
                    @disabled($isDisabled)
                    class="shrink-0 appearance-none rounded-xs border border-border-input bg-surface bg-center bg-no-repeat checked:border-accent-700 checked:bg-accent-700 checked:field-check-mark disabled:cursor-not-allowed disabled:bg-surface-sunken {{ $sheet ? 'size-5' : 'size-4' }}"
                >
                <span class="{{ $sheet ? 'text-base' : '' }}">{{ $brand['name'] }}</span>
                <span class="ml-auto text-text-muted tabular-nums {{ $sheet ? 'text-sm' : 'text-xs' }}">{{ $brand['count'] }}</span>
            </label>
            @if ($loop->last && count($brandFacets) > $limit)
                </details>
            @endif
        @endforeach
    </div>
</details>
