@props(['filterableParameters', 'facetCounts', 'selected', 'live' => true])

@if ($filterableParameters->isEmpty())
    <p class="text-sm text-text-muted">{{ __('storefront.catalog.filters_none') }}</p>
@else
    @foreach ($filterableParameters as $parameter)
        @if (in_array($parameter->type, ['select_single', 'select_multiple'], true))
            <x-storefront.filter-select
                :parameter="$parameter"
                :facet-counts="$facetCounts[$parameter->id] ?? []"
                :selected-option-ids="$selected[$parameter->id] ?? []"
                :live="$live"
            />
        @elseif ($parameter->type === 'number')
            <x-storefront.filter-number :parameter="$parameter" :live="$live" />
        @endif
        {{-- `text`-type filterable parameters are a rare edge case per
             docs/design/11-storefront-catalog-filters.md and are omitted
             from this v1 filter sidebar. --}}
    @endforeach
@endif
