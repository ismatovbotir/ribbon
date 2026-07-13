@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
    $categoryName = $category->name[$locale] ?? ($category->name[$defaultLocale] ?? '');

    // Filterable parameter *names* (not values) for this category — fixed
    // by the category regardless of which filters a buyer currently has
    // applied, so this is safe to state unconditionally, unlike a product
    // count (which only reflects the *unfiltered* category total — see
    // $hasActiveFilters below).
    $specNames = $filterableParameters
        ->map(fn ($parameter) => $parameter->name[$locale] ?? ($parameter->name[$defaultLocale] ?? ''))
        ->filter(fn ($name) => $name !== '')
        ->implode(', ');
@endphp

<div>
    {{-- Breadcrumb --}}
    <nav aria-label="Breadcrumb" class="mb-3 flex items-center gap-1.5 text-sm">
        <a href="{{ route('storefront.home') }}" wire:navigate class="text-text-secondary hover:text-text-primary">{{ __('storefront.catalog.breadcrumb_home') }}</a>
        <span class="text-text-muted" aria-hidden="true">/</span>
        <span class="font-medium text-text-primary">{{ $categoryName }}</span>
    </nav>

    <h1 class="text-2xl font-semibold text-text-primary">{{ $categoryName }}</h1>

    {{--
        Category intro — a direct, factual, self-contained statement of
        what this page lists, for an AI answer engine to extract/cite
        without needing the filter sidebar or product grid. The product
        count is only stated when unfiltered ($products->total() reflects
        whatever filters are currently applied, so it would misrepresent
        the category's real size otherwise); the spec list is always safe
        since it's the category's fixed parameter set, not a per-result
        figure. There is no admin-authored category description field
        today (see Category model) — this is generated entirely from
        already-true, already-queried page data rather than static/stale
        marketing copy.
    --}}
    <div class="mt-2 max-w-3xl space-y-1 text-sm text-text-secondary">
        <p>
            @if (! $hasActiveFilters)
                {{ __('storefront.catalog.intro_with_count', ['category' => $categoryName, 'count' => $products->total()]) }}
            @else
                {{ __('storefront.catalog.intro_no_count', ['category' => $categoryName]) }}
            @endif
        </p>
        @if ($specNames !== '')
            <p>{{ __('storefront.catalog.intro_specs', ['specs' => $specNames]) }}</p>
        @endif
    </div>

    {{-- category_top banner: targeted to this category, or a generic
         fallback (banners.category_id null) — see Show::render(). --}}
    @if ($categoryTopBanner)
        @php $bannerTitle = $categoryTopBanner->title[$locale] ?? ($categoryTopBanner->title[$defaultLocale] ?? ''); @endphp
        <x-storefront.banner-frame :href="$categoryTopBanner->link_url" class="relative mt-6 block overflow-hidden rounded-2xl bg-surface-subtle">
            <img
                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($categoryTopBanner->image_path) }}"
                alt="{{ $bannerTitle }}"
                class="aspect-[21/6] w-full object-cover md:aspect-[4/1]"
                loading="eager"
            >
            @if ($bannerTitle !== '')
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-900/70 to-transparent p-4">
                    <p class="text-lg font-semibold text-white">{{ $bannerTitle }}</p>
                </div>
            @endif
        </x-storefront.banner-frame>
    @endif

    <div class="mt-6 lg:grid lg:grid-cols-[18rem_1fr] lg:items-start lg:gap-8">
        {{-- Filter sidebar (desktop) --}}
        <aside class="hidden lg:sticky lg:top-[calc(var(--spacing-storefront-header)+1rem)] lg:block">
            <div class="rounded-xl border border-border bg-surface-raised p-4">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-sm font-semibold text-text-primary">{{ __('storefront.catalog.filters_heading') }}</p>
                    @if ($hasActiveFilters)
                        <button type="button" wire:click="clearFilters" class="text-xs text-accent-700 hover:underline">{{ __('storefront.catalog.filters_clear_all') }}</button>
                    @endif
                </div>

                <x-storefront.filter-groups
                    :filterable-parameters="$filterableParameters"
                    :facet-counts="$facetCounts"
                    :selected="$selected"
                    :live="true"
                />
            </div>
        </aside>

        <div class="mt-6 lg:mt-0">
            {{-- Mobile filters trigger --}}
            <div class="mb-4 lg:hidden">
                <button
                    type="button"
                    wire:click="$set('showMobileFilters', true)"
                    class="flex h-9 items-center gap-2 rounded-sm border border-border px-3 text-sm font-medium text-text-primary"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M3 5h14M6 10h8M8.5 15h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    </svg>
                    {{ __('storefront.catalog.filters_button') }}
                    @if ($hasActiveFilters)
                        <span class="flex h-4 min-w-4 items-center justify-center rounded-full bg-accent-600 px-1 text-[10px] font-semibold text-white">
                            {{ collect($selected)->sum(fn ($ids) => count($ids)) + count($numMin) + count($numMax) }}
                        </span>
                    @endif
                </button>
            </div>

            {{-- Result bar --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-text-secondary">{{ __('storefront.catalog.results_count', ['count' => $products->total()]) }}</p>

                <label class="flex items-center gap-2 text-sm">
                    <span class="text-text-secondary">{{ __('storefront.catalog.sort_label') }}</span>
                    <select wire:model.live="sort" class="h-9 rounded-sm border border-border bg-surface px-2 text-sm text-text-primary focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none">
                        <option value="newest">{{ __('storefront.catalog.sort_newest') }}</option>
                        <option value="price_asc">{{ __('storefront.catalog.sort_price_asc') }}</option>
                        <option value="price_desc">{{ __('storefront.catalog.sort_price_desc') }}</option>
                    </select>
                </label>
            </div>

            {{-- Applied filter chips --}}
            @if ($hasActiveFilters)
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @foreach ($filterableParameters as $parameter)
                        @foreach (($selected[$parameter->id] ?? []) as $optionId)
                            @php
                                $option = $parameter->options->firstWhere('id', (int) $optionId);
                                $paramLabel = $parameter->name[$locale] ?? ($parameter->name[$defaultLocale] ?? '');
                                $optionLabel = $option ? ($option->value[$locale] ?? ($option->value[$defaultLocale] ?? '')) : $optionId;
                            @endphp
                            <button
                                type="button"
                                wire:click="removeOptionFilter({{ $parameter->id }}, '{{ $optionId }}')"
                                class="inline-flex items-center gap-1 rounded-full border border-border bg-surface-subtle px-2.5 py-1 text-xs text-text-secondary hover:text-text-primary"
                            >
                                {{ $paramLabel }}: {{ $optionLabel }}
                                <span aria-hidden="true">×</span>
                            </button>
                        @endforeach

                        @if (! empty($numMin[$parameter->id] ?? null))
                            <button type="button" wire:click="removeNumberFilter({{ $parameter->id }}, 'min')" class="inline-flex items-center gap-1 rounded-full border border-border bg-surface-subtle px-2.5 py-1 text-xs text-text-secondary hover:text-text-primary">
                                {{ $parameter->name[$locale] ?? '' }} ≥ {{ $numMin[$parameter->id] }}{{ $parameter->unit }}
                                <span aria-hidden="true">×</span>
                            </button>
                        @endif

                        @if (! empty($numMax[$parameter->id] ?? null))
                            <button type="button" wire:click="removeNumberFilter({{ $parameter->id }}, 'max')" class="inline-flex items-center gap-1 rounded-full border border-border bg-surface-subtle px-2.5 py-1 text-xs text-text-secondary hover:text-text-primary">
                                {{ $parameter->name[$locale] ?? '' }} ≤ {{ $numMax[$parameter->id] }}{{ $parameter->unit }}
                                <span aria-hidden="true">×</span>
                            </button>
                        @endif
                    @endforeach
                </div>
            @endif

            {{-- Grid / states --}}
            <div class="mt-4">
                <div wire:loading.grid wire:target="selected,numMin,numMax,sort,removeOptionFilter,removeNumberFilter,clearFilters,closeMobileFilters" class="hidden grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 lg:grid-cols-4">
                    @for ($i = 0; $i < 8; $i++)
                        <x-storefront.product-card-skeleton />
                    @endfor
                </div>

                <div wire:loading.remove wire:target="selected,numMin,numMax,sort,removeOptionFilter,removeNumberFilter,clearFilters,closeMobileFilters">
                    @if ($products->isEmpty())
                        @if (! $categoryHasAnyProducts)
                            <div class="rounded-xl border border-dashed border-border p-10 text-center">
                                <p class="text-base font-medium text-text-primary">{{ __('storefront.catalog.empty_title') }}</p>
                                <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.catalog.empty_body') }}</p>
                                <a href="{{ route('storefront.home') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-accent-700 hover:underline">{{ __('storefront.catalog.empty_cta') }}</a>
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-border p-10 text-center">
                                <p class="text-base font-medium text-text-primary">{{ __('storefront.catalog.filtered_empty_title') }}</p>
                                <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.catalog.filtered_empty_body') }}</p>
                                <button type="button" wire:click="clearFilters" class="mt-4 text-sm font-medium text-accent-700 hover:underline">{{ __('storefront.catalog.filters_clear') }}</button>
                            </div>
                        @endif
                    @else
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 lg:grid-cols-4">
                            @foreach ($products as $product)
                                <x-storefront.product-card :product="$product" :show-category="false" wire:key="catalog-product-{{ $product->id }}" />
                            @endforeach
                        </div>

                        <div class="mt-8">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Mobile filters off-canvas sheet --}}
    <div
        x-show="$wire.showMobileFilters"
        x-cloak
        class="fixed inset-0 z-modal-backdrop bg-slate-900/40 lg:hidden"
        x-on:click="$wire.closeMobileFilters()"
    ></div>
    <div
        x-show="$wire.showMobileFilters"
        x-cloak
        x-transition
        class="fixed inset-x-0 bottom-0 z-modal flex max-h-[85vh] flex-col rounded-t-2xl border-t border-border bg-surface lg:hidden"
    >
        <div class="flex shrink-0 items-center justify-between border-b border-border px-4 py-3">
            <p class="text-base font-semibold text-text-primary">{{ __('storefront.catalog.filters_heading') }}</p>
            <button type="button" wire:click="$set('showMobileFilters', false)" class="text-text-muted" aria-label="{{ __('storefront.nav.close_menu') }}">✕</button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-4">
            <x-storefront.filter-groups
                :filterable-parameters="$filterableParameters"
                :facet-counts="$facetCounts"
                :selected="$selected"
                :live="false"
            />
        </div>

        <div class="shrink-0 border-t border-border p-4">
            <button
                type="button"
                wire:click="closeMobileFilters"
                class="flex h-11 w-full items-center justify-center rounded-sm bg-accent-600 text-sm font-semibold text-white hover:bg-accent-700"
            >
                {{ __('storefront.catalog.filters_apply', ['count' => $products->total()]) }}
            </button>
        </div>
    </div>
</div>
