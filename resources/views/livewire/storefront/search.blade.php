@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
@endphp

<div>
    <h1 class="text-2xl font-bold tracking-tight text-text-primary">
        @if ($hasQuery)
            {{ __('storefront.search.heading_with_query', ['query' => $query]) }}
        @else
            {{ __('storefront.search.heading_empty') }}
        @endif
    </h1>

    @if ($hasQuery)
        <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.catalog.results_count', ['count' => $products->total()]) }}</p>
    @endif

    {{--
        Category filter row — a single flat dimension (unlike catalog's
        full parameter sidebar, since results here can span many
        categories that share no filterable-parameter vocabulary; see
        App\Livewire\Storefront\Search's own docblock). Only rendered when
        the current term actually spans more than one category
        ($filterCategories is already scoped to that by the component).
        Reuses the exact pill visual language of the product detail page's
        unit selector (docs/design/13 §6).
    --}}
    @if ($filterCategories->isNotEmpty())
        <div class="mt-4 flex flex-wrap items-center gap-2">
            @foreach ($filterCategories as $category)
                @php
                    $categoryIsActive = in_array((string) $category->id, $categoryIds, true);
                    $categoryLabel = $category->name[$locale] ?? ($category->name[$defaultLocale] ?? '');
                @endphp
                <button
                    type="button"
                    wire:click="toggleCategory({{ $category->id }})"
                    aria-pressed="{{ $categoryIsActive ? 'true' : 'false' }}"
                    class="rounded-full border px-3 py-1.5 text-sm font-medium transition-colors {{ $categoryIsActive ? 'border-accent-600 bg-accent-600 text-white' : 'border-border text-text-secondary hover:text-text-primary' }}"
                >
                    {{ $categoryLabel }} ({{ $facetCounts[$category->id] ?? 0 }})
                </button>
            @endforeach

            @if ($hasActiveCategoryFilter)
                <button type="button" wire:click="clearCategoryFilter" class="text-xs text-accent-700 hover:underline">{{ __('storefront.search.category_filter_clear') }}</button>
            @endif
        </div>
    @endif

    {{-- Grid / states — mirrors catalog/show.blade.php's exact grid,
         skeleton and empty-state shapes (docs/design/13 §6), plus a
         distinct "no query yet" state below. --}}
    <div class="mt-6">
        <div wire:loading.grid wire:target="toggleCategory,clearCategoryFilter" class="hidden grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 lg:grid-cols-4 lg:gap-8">
            @for ($i = 0; $i < 8; $i++)
                <x-storefront.product-card-skeleton />
            @endfor
        </div>

        <div wire:loading.remove wire:target="toggleCategory,clearCategoryFilter">
            @if (! $hasQuery)
                <div class="rounded-xl border border-dashed border-border p-10 text-center">
                    <svg class="mx-auto h-10 w-10 text-text-muted" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <circle cx="9" cy="9" r="5.5" stroke="currentColor" stroke-width="1.4" />
                        <path d="M17 17l-3.5-3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    </svg>
                    <p class="mt-3 text-base font-medium text-text-primary">{{ __('storefront.search.no_query_title') }}</p>
                    <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.search.no_query_body') }}</p>
                </div>
            @elseif ($products->isEmpty())
                <div class="rounded-xl border border-dashed border-border p-10 text-center">
                    <p class="text-base font-medium text-text-primary">{{ __('storefront.search.empty_title', ['query' => $query]) }}</p>
                    <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.search.empty_body') }}</p>
                    <a href="{{ route('storefront.home') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-accent-700 hover:underline">{{ __('storefront.catalog.empty_cta') }}</a>
                </div>
            @else
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 lg:grid-cols-4 lg:gap-8">
                    @foreach ($products as $product)
                        <x-storefront.product-card :product="$product" :show-category="true" wire:key="search-product-{{ $product->id }}" />
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
