@props(['filterableParameters', 'facetCounts', 'selected', 'variant' => 'sidebar', 'brandFacets' => [], 'selectedBrands' => []])

{{-- Spec 18 6.3 / 6.4. `sheet` (phone/tablet, ids prefixed fg-) and `sidebar` (lg) render the same state; only one is visible at a time. --}}
@if ($filterableParameters->isEmpty() && count($brandFacets) === 0)
    <p class="px-4 py-3 text-sm text-text-muted">{{ __('storefront.catalog.filters_none') }}</p>
@else
    @foreach ($filterableParameters as $parameter)
        @if (in_array($parameter->type, ['select_single', 'select_multiple'], true))
            <x-storefront.filter-select
                :parameter="$parameter"
                :facet-counts="$facetCounts[$parameter->id] ?? []"
                :selected-option-ids="$selected[$parameter->id] ?? []"
                :variant="$variant"
                :first="$loop->first"
            />
        @elseif ($parameter->type === 'number')
            <x-storefront.filter-number :parameter="$parameter" :variant="$variant" />
        @endif
        {{-- `text`-type filterable parameters are omitted (spec 11). --}}
    @endforeach
    @if (count($brandFacets) > 0)
        <x-storefront.filter-brand :brand-facets="$brandFacets" :selected-ids="$selectedBrands" :variant="$variant" />
    @endif
@endif
