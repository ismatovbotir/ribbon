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

    $loadingTargets = 'selected,numMin,numMax,brands,sort,removeOptionFilter,removeNumberFilter,removeBrandFilter,clearFilters';
    $chip = 'hit inline-flex h-11 shrink-0 snap-start items-center gap-2 rounded-sm border px-3 text-sm font-medium whitespace-nowrap';
    $chipOff = 'border-border-input bg-surface text-text-primary hover:bg-surface-hover';
    $chipOn = 'border-accent-700 bg-accent-50 text-accent-800';
    $activeCount = collect($selected)->sum(fn ($ids) => count(array_filter((array) $ids))) + count(array_filter($numMin, fn ($v) => $v !== null && $v !== '')) + count(array_filter($numMax, fn ($v) => $v !== null && $v !== '')) + count(array_filter((array) $brands));
    $brandNames = collect($brandFacets)->pluck('name', 'id');
    $quickParameters = $filterableParameters->filter(fn ($p) => in_array($p->type, ['select_single', 'select_multiple'], true))->take(3);
    $sortLabels = ['newest' => __('storefront.catalog.sort_newest'), 'price_asc' => __('storefront.catalog.sort_price_asc'), 'price_desc' => __('storefront.catalog.sort_price_desc')];
@endphp

<div>
    {{-- Breadcrumb (scroll strip on phones) --}}
    <nav aria-label="{{ __('storefront.catalog.breadcrumb_label') }}" class="no-scrollbar -mx-4 mb-3 flex items-center gap-1.5 overflow-x-auto px-4 text-sm whitespace-nowrap md:mx-0 md:px-0">
        <a href="{{ route('storefront.home') }}" wire:navigate class="hit text-text-secondary hover:text-text-primary hover:underline">{{ __('storefront.catalog.breadcrumb_home') }}</a>
        <span class="text-text-muted" aria-hidden="true">/</span>
        <span class="font-medium text-text-primary" aria-current="page">{{ $categoryName }}</span>
    </nav>

    <h1 class="text-xl font-semibold text-text-primary md:text-2xl">{{ $categoryName }}</h1>

    {{--
        Category intro - a direct, factual, self-contained statement of what
        this page lists (GEO). The product count is only stated when
        unfiltered; the spec list is the category's fixed parameter set.
        Generated from already-true page data, not static marketing copy.
    --}}
    <div class="mt-3 max-w-3xl space-y-1 text-sm text-text-secondary {{ $categoryTopBanner ? '' : 'rounded-md border border-border bg-surface-band px-4 py-3' }}">
        <p>
            @if (! $hasActiveFilters)
                {{ trans_choice('storefront.catalog.intro_with_count', $products->total(), ['category' => $categoryName]) }}
            @else
                {{ __('storefront.catalog.intro_no_count', ['category' => $categoryName]) }}
            @endif
        </p>
        @if ($specNames !== '')
            <p>{{ __('storefront.catalog.intro_specs', ['specs' => $specNames]) }}</p>
        @endif
    </div>

    {{-- category_top banner: targeted to this category, or a generic fallback. --}}
    @if ($categoryTopBanner)
        @php $bannerTitle = $categoryTopBanner->title[$locale] ?? ($categoryTopBanner->title[$defaultLocale] ?? ''); @endphp
        <x-storefront.banner-frame :href="$categoryTopBanner->link_url" class="mt-4 block overflow-hidden rounded-lg border border-border bg-surface-subtle hover:border-accent-700">
            <img
                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($categoryTopBanner->image_path) }}"
                alt="{{ $bannerTitle }}"
                class="aspect-[21/6] w-full object-cover md:aspect-[4/1]"
                loading="eager"
            >
            @if ($bannerTitle !== '')
                <p class="border-t border-border bg-surface-band px-3 py-2 text-sm font-semibold text-text-primary">{{ $bannerTitle }}</p>
            @endif
        </x-storefront.banner-frame>
    @endif

    {{-- Phone/tablet sticky filter chip bar (below lg). Sticks directly under the 56/64px header. --}}
    <div class="sticky top-(--spacing-storefront-header) z-(--z-bar) -mx-4 mt-4 border-b border-border-strong bg-surface px-4 md:-mx-6 md:px-6 lg:hidden">
        <div class="no-scrollbar flex h-filterbar snap-x items-center gap-2 overflow-x-auto">
            <button type="button" x-on:click="filtersOpen = true" aria-haspopup="dialog" class="{{ $chip }} {{ $activeCount > 0 ? 'border-accent-700 bg-accent-700 text-white' : $chipOff }}">
                <x-storefront.icon name="filter" class="size-4" stroke="2" />
                {{ __('storefront.catalog.filters_button') }}
                @if ($activeCount > 0)<span class="text-xs tabular-nums">{{ $activeCount }}</span>@endif
            </button>
            @foreach ($quickParameters as $qp)
                @php
                    $qpSelected = array_values(array_filter((array) ($selected[$qp->id] ?? [])));
                    $qpFirst = $qpSelected ? $qp->options->firstWhere('id', (int) $qpSelected[0]) : null;
                    $qpFirstLabel = $qpFirst ? ($qpFirst->value[$locale] ?? ($qpFirst->value[$defaultLocale] ?? '')) : '';
                @endphp
                <button
                    type="button"
                    x-on:click="filtersOpen = true; $nextTick(() => { const d = document.getElementById('fg-{{ $qp->id }}'); if (d) { d.open = true; d.scrollIntoView({ block: 'start' }); } })"
                    aria-haspopup="dialog"
                    class="{{ $chip }} {{ $qpSelected ? $chipOn : $chipOff }}"
                >
                    {{ $qp->name[$locale] ?? ($qp->name[$defaultLocale] ?? '') }}@if ($qpSelected): {{ $qpFirstLabel }}@if (count($qpSelected) > 1) +{{ count($qpSelected) - 1 }}@endif @endif
                    <x-storefront.icon name="chevron-down" class="size-3" stroke="2" />
                </button>
            @endforeach
            <button
                type="button"
                x-on:click="filtersOpen = true; $nextTick(() => document.getElementById('fg-sort')?.scrollIntoView({ block: 'start' }))"
                aria-haspopup="dialog"
                class="{{ $chip }} {{ $chipOff }} md:hidden"
            >
                {{ __('storefront.catalog.sort_label') }}: {{ $sortLabels[$sort] ?? '' }}
                <x-storefront.icon name="chevron-down" class="size-3" stroke="2" />
            </button>
        </div>
    </div>

    <div class="mt-4 lg:mt-6 lg:grid lg:grid-cols-[var(--spacing-filters)_minmax(0,1fr)] lg:items-start lg:gap-8">
        {{-- Desktop filter sidebar --}}
        <aside aria-label="{{ __('storefront.catalog.filters_heading') }}" class="hidden lg:sticky lg:top-[calc(var(--spacing-storefront-header)+1rem)] lg:block lg:max-h-[calc(100dvh-var(--spacing-storefront-header)-2rem)] lg:overflow-y-auto">
            <div class="overflow-hidden rounded-md border border-border-strong bg-surface">
                <div class="flex h-12 items-center justify-between border-b border-border-strong bg-surface-band px-4">
                    <h2 class="text-base font-semibold text-text-primary">{{ __('storefront.catalog.filters_heading') }}</h2>
                    @if ($hasActiveFilters)
                        <button type="button" wire:click="clearFilters" class="h-9 px-2 text-sm text-accent-700 hover:underline">{{ __('storefront.catalog.filters_clear_all') }}</button>
                    @endif
                </div>
                <x-storefront.filter-groups :filterable-parameters="$filterableParameters" :facet-counts="$facetCounts" :selected="$selected" :brand-facets="$brandFacets" :selected-brands="$brands" variant="sidebar" />
            </div>
        </aside>

        <div id="list" class="scroll-mt-32 min-w-0">
            {{-- Result bar --}}
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-text-secondary" aria-live="polite"><span class="tabular-nums">{{ $products->total() }}</span> {{ trans_choice('storefront.catalog.results_word', $products->total()) }}</p>

                <label class="hidden items-center gap-2 text-sm md:flex">
                    <span class="text-text-secondary">{{ __('storefront.catalog.sort_label') }}</span>
                    <x-select wire:model.live="sort" class="w-auto!">
                        @foreach ($sortLabels as $sortKey => $sortText)
                            <option value="{{ $sortKey }}">{{ $sortText }}</option>
                        @endforeach
                    </x-select>
                </label>
            </div>

            {{-- Applied filter chips (tablet/desktop; phones rely on the sticky bar) --}}
            @if ($hasActiveFilters)
                <div class="no-scrollbar mt-3 hidden gap-2 overflow-x-auto md:flex lg:flex-wrap">
                    @foreach ($filterableParameters as $parameter)
                        @foreach (array_filter((array) ($selected[$parameter->id] ?? [])) as $optionId)
                            @php
                                $option = $parameter->options->firstWhere('id', (int) $optionId);
                                $paramLabel = $parameter->name[$locale] ?? ($parameter->name[$defaultLocale] ?? '');
                                $optionLabel = $option ? ($option->value[$locale] ?? ($option->value[$defaultLocale] ?? '')) : $optionId;
                            @endphp
                            <button type="button" wire:click="removeOptionFilter({{ $parameter->id }}, '{{ $optionId }}')" class="{{ $chip }} {{ $chipOn }}">
                                {{ $paramLabel }}: {{ $optionLabel }}
                                <x-storefront.icon name="close" class="size-3" stroke="2" />
                                <span class="sr-only">{{ __('storefront.catalog.filter_remove') }}</span>
                            </button>
                        @endforeach

                        @if (! empty($numMin[$parameter->id] ?? null))
                            <button type="button" wire:click="removeNumberFilter({{ $parameter->id }}, 'min')" class="{{ $chip }} {{ $chipOn }}">
                                {{ $parameter->name[$locale] ?? '' }} &ge; <span class="font-mono tabular-nums">{{ $numMin[$parameter->id] }}{{ $parameter->unit }}</span>
                                <x-storefront.icon name="close" class="size-3" stroke="2" />
                                <span class="sr-only">{{ __('storefront.catalog.filter_remove') }}</span>
                            </button>
                        @endif

                        @if (! empty($numMax[$parameter->id] ?? null))
                            <button type="button" wire:click="removeNumberFilter({{ $parameter->id }}, 'max')" class="{{ $chip }} {{ $chipOn }}">
                                {{ $parameter->name[$locale] ?? '' }} &le; <span class="font-mono tabular-nums">{{ $numMax[$parameter->id] }}{{ $parameter->unit }}</span>
                                <x-storefront.icon name="close" class="size-3" stroke="2" />
                                <span class="sr-only">{{ __('storefront.catalog.filter_remove') }}</span>
                            </button>
                        @endif
                    @endforeach
                    @foreach (array_filter((array) $brands) as $brandId)
                        <button type="button" wire:click="removeBrandFilter({{ (int) $brandId }})" class="{{ $chip }} {{ $chipOn }}">
                            {{ __('storefront.catalog.filter_brand') }}: {{ $brandNames[(int) $brandId] ?? $brandId }}
                            <x-storefront.icon name="close" class="size-3" stroke="2" />
                            <span class="sr-only">{{ __('storefront.catalog.filter_remove') }}</span>
                        </button>
                    @endforeach
                    <button type="button" wire:click="clearFilters" class="hit inline-flex h-11 shrink-0 items-center px-3 text-sm font-medium text-accent-700 hover:underline">{{ __('storefront.catalog.filters_clear_all') }}</button>
                </div>
            @endif

            {{-- List / states --}}
            <div class="mt-4">
                <div wire:loading.block wire:target="{{ $loadingTargets }}" aria-busy="true" class="hidden overflow-hidden rounded-md border border-border-strong bg-surface">
                    <ul>
                        @for ($i = 0; $i < 8; $i++)
                            <x-storefront.product-row-skeleton />
                        @endfor
                    </ul>
                </div>

                <div wire:loading.remove wire:target="{{ $loadingTargets }}">
                    @if ($products->isEmpty())
                        <div class="rounded-md border border-border-strong">
                            @if (! $categoryHasAnyProducts)
                                <x-empty-state variant="empty" :title="__('storefront.catalog.empty_title')" :description="__('storefront.catalog.empty_body')">
                                    <x-slot:actions>
                                        <x-button tag="a" variant="primary" href="{{ route('storefront.home') }}" wire:navigate>{{ __('storefront.catalog.empty_cta') }}</x-button>
                                    </x-slot:actions>
                                </x-empty-state>
                            @else
                                <x-empty-state variant="filtered" :title="__('storefront.catalog.filtered_empty_title')" :description="__('storefront.catalog.filtered_empty_body')">
                                    <x-slot:actions>
                                        <x-button variant="primary" wire:click="clearFilters">{{ __('storefront.catalog.filters_clear') }}</x-button>
                                    </x-slot:actions>
                                </x-empty-state>
                            @endif
                        </div>
                    @else
                        <h2 class="sr-only">{{ __('storefront.list.products_heading') }}</h2>
                        <div class="overflow-hidden rounded-md border border-border-strong bg-surface">
                            <x-storefront.product-list-header />
                            <ul aria-label="{{ $categoryName }}">
                                @foreach ($products as $product)
                                    <x-storefront.product-row :product="$product" :show-category="false" wire:key="catalog-product-{{ $product->id }}" />
                                @endforeach
                            </ul>
                        </div>

                        {{ $products->links('pagination.storefront') }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{--
        About this category (GEO): server-rendered, filter-independent facts.
        Lists each filterable parameter with its unit or its option values so an
        answer engine can see which specs/values exist in this category.
    --}}
    <section aria-labelledby="category-about-heading" class="mt-12 max-w-3xl">
        <h2 id="category-about-heading" class="text-lg font-semibold text-text-primary md:text-xl">{{ __('storefront.catalog.about_heading', ['category' => $categoryName]) }}</h2>
        <p class="mt-2 text-base text-text-secondary">{{ __('storefront.catalog.about_lead', ['category' => $categoryName]) }}</p>
        @if ($filterableParameters->isNotEmpty())
            <dl class="mt-4 overflow-hidden rounded-md border border-border-strong text-sm">
                @foreach ($filterableParameters as $aboutParameter)
                    @php
                        $aboutLabel = $aboutParameter->name[$locale] ?? ($aboutParameter->name[$defaultLocale] ?? '');
                        $aboutValues = $aboutParameter->options
                            ->map(fn ($option) => $option->value[$locale] ?? ($option->value[$defaultLocale] ?? ''))
                            ->filter(fn ($v) => $v !== '')
                            ->take(15)
                            ->implode(', ');
                        if ($aboutValues === '' && $aboutParameter->unit) {
                            $aboutValues = __('storefront.catalog.about_numeric', ['unit' => $aboutParameter->unit]);
                        }
                    @endphp
                    @continue($aboutLabel === '' || $aboutValues === '')
                    <div class="grid grid-cols-[2fr_3fr] gap-3 border-b border-border px-3 py-3 last:border-b-0 md:px-4">
                        <dt class="text-text-secondary">{{ $aboutLabel }}</dt>
                        <dd class="font-mono tabular-nums break-words text-text-primary">{{ $aboutValues }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>

    {{-- Related articles --}}
    @if ($relatedArticles->isNotEmpty())
        <section aria-labelledby="related-articles-heading" class="mt-12">
            <x-storefront.section-heading id="related-articles-heading" :title="__('storefront.catalog.related_articles_heading')" />
            <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-3">
                @foreach ($relatedArticles as $article)
                    <x-storefront.article-card :article="$article" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- All-filters sheet (below lg). Open state is Alpine (body x-data filtersOpen), inputs are live. --}}
    <div
        x-show="filtersOpen"
        x-cloak
        role="dialog"
        aria-modal="true"
        aria-labelledby="filters-title"
        x-trap.noscroll="filtersOpen"
        x-on:keydown.escape.window="filtersOpen = false"
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        class="fixed inset-0 z-(--z-sheet) flex flex-col bg-surface motion-reduce:transition-none lg:hidden"
    >
        <header class="flex h-14 shrink-0 items-center justify-between border-b border-border-strong pr-1 pl-4">
            <h2 id="filters-title" class="text-lg font-semibold text-text-primary">{{ __('storefront.catalog.filters_heading') }}</h2>
            <button type="button" x-on:click="filtersOpen = false" aria-label="{{ __('storefront.nav.close_menu') }}" class="flex size-11 items-center justify-center text-text-primary">
                <x-storefront.icon name="close" class="size-5" />
            </button>
        </header>

        <div class="flex-1 overflow-y-auto overscroll-contain px-4">
            <x-storefront.filter-groups :filterable-parameters="$filterableParameters" :facet-counts="$facetCounts" :selected="$selected" :brand-facets="$brandFacets" :selected-brands="$brands" variant="sheet" />

            <details id="fg-sort" open class="group border-b border-border">
                <summary class="flex h-14 cursor-pointer items-center justify-between text-base font-semibold text-text-primary">
                    <span>{{ __('storefront.catalog.sort_label') }}</span>
                    <x-storefront.icon name="chevron-down" class="size-4 text-text-muted group-open:rotate-180" stroke="2" />
                </summary>
                <div class="pb-2">
                    @foreach ($sortLabels as $sortKey => $sortText)
                        <label class="flex min-h-12 cursor-pointer items-center gap-3 border-t border-border first:border-t-0">
                            <input type="radio" name="sheet-sort" value="{{ $sortKey }}" wire:model.live="sort" class="size-5 shrink-0 accent-[var(--color-accent-700)]">
                            <span class="text-base text-text-primary">{{ $sortText }}</span>
                        </label>
                    @endforeach
                </div>
            </details>
        </div>

        <footer class="grid shrink-0 grid-cols-[auto_minmax(0,1fr)] gap-2 border-t border-border-strong bg-surface p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
            <x-button variant="secondary" wire:click="clearFilters" class="min-h-12 px-4">{{ __('storefront.catalog.filters_reset') }}</x-button>
            <x-button variant="primary" x-on:click="filtersOpen = false" wire:loading.class="opacity-60" wire:target="{{ $loadingTargets }}" class="min-h-12" aria-live="polite">{{ trans_choice('storefront.catalog.filters_apply', $products->total()) }}</x-button>
        </footer>
    </div>
</div>
