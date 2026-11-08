@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
    $chip = 'hit inline-flex h-11 shrink-0 items-center gap-2 rounded-sm border px-3 text-sm font-medium whitespace-nowrap';
    $zero = $hasQuery && $products->isEmpty() && ! $hasActiveCategoryFilter;
@endphp

<div>
    @if ($zero)
        {{-- Zero results: still the page's only H1, with a way forward (spec 18 7.2). --}}
        <section aria-labelledby="zero-title" class="text-center">
            <h1 id="zero-title" class="text-xl font-semibold text-text-primary md:text-2xl">{{ __('storefront.search.empty_title', ['query' => $query]) }}</h1>
            <p class="mx-auto mt-2 max-w-xl text-base text-text-secondary">{{ __('storefront.search.zero_searched') }}</p>
            <ul class="mx-auto mt-4 w-fit max-w-xl list-disc space-y-1 pl-5 text-left text-sm text-text-secondary">
                <li>{{ __('storefront.search.zero_tip_1') }}</li>
                <li>{{ __('storefront.search.zero_tip_2') }}</li>
                <li>{{ __('storefront.search.zero_tip_3') }}</li>
            </ul>

            <h2 class="mt-8 text-lg font-semibold text-text-primary">{{ __('storefront.search.zero_browse') }}</h2>
            <div class="mt-3 grid-balanced [--cols:2] {{ $navCategories->count() % 4 === 0 ? 'md:[--cols:4]' : 'md:[--cols:3]' }}">
                @foreach ($navCategories as $navCategory)
                    <x-storefront.tile :href="route('storefront.catalog.show', ['categorySlug' => $navCategory->slug[$locale] ?? ''])" :name="$navCategory->name[$locale] ?? ''" :icon="$navCategory->icon" :count="$navCategory->approved_products_count ?? 0" />
                @endforeach
            </div>

            <aside class="mt-8 rounded-md border border-border-strong bg-surface-band p-4 md:p-6">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('storefront.search.cant_find_title') }}</h2>
                <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.search.cant_find_body') }}</p>
                <div class="mt-3 flex flex-wrap justify-center gap-2">
                    @if ($settings->admin_phone)
                        <x-button tag="a" variant="secondary" href="tel:{{ $settings->admin_phone }}" class="font-mono tabular-nums">{{ __('storefront.product_detail.call') }} {{ $settings->admin_phone }}</x-button>
                    @endif
                    @if ($settings->isTelegramBotConnected())
                        <x-button tag="a" variant="secondary" href="https://t.me/{{ $settings->telegram_bot_username }}" target="_blank" rel="noopener">{{ __('storefront.search.cant_find_telegram') }}</x-button>
                    @endif
                    <x-button tag="a" variant="secondary" href="{{ route('storefront.contact') }}" wire:navigate>{{ __('storefront.nav.mega_cant_find_link') }}</x-button>
                </div>
            </aside>
        </section>
    @else
        <h1 class="text-xl font-semibold text-text-primary md:text-2xl">
            @if ($hasQuery)
                {{ __('storefront.search.heading_with_query', ['query' => $query]) }}
            @else
                {{ __('storefront.search.heading_empty') }}
            @endif
        </h1>

        @if ($hasQuery)
            <p class="mt-1 text-sm text-text-secondary" aria-live="polite"><span class="tabular-nums">{{ $products->total() }}</span> {{ trans_choice('storefront.catalog.results_word', $products->total()) }}</p>
        @endif

        {{-- Category facet strip: only when the term spans more than one category. --}}
        @if ($filterCategories->isNotEmpty())
            <div class="no-scrollbar -mx-4 mt-4 flex gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0">
                @foreach ($filterCategories as $category)
                    @php
                        $categoryIsActive = in_array((string) $category->id, $categoryIds, true);
                        $categoryLabel = $category->name[$locale] ?? ($category->name[$defaultLocale] ?? '');
                    @endphp
                    <button
                        type="button"
                        wire:click="toggleCategory({{ $category->id }})"
                        aria-pressed="{{ $categoryIsActive ? 'true' : 'false' }}"
                        class="{{ $chip }} {{ $categoryIsActive ? 'border-accent-700 bg-accent-50 text-accent-800' : 'border-border-input bg-surface text-text-primary hover:bg-surface-hover' }}"
                    >
                        {{ $categoryLabel }} <span class="text-xs tabular-nums">({{ $facetCounts[$category->id] ?? 0 }})</span>
                    </button>
                @endforeach

                @if ($hasActiveCategoryFilter)
                    <button type="button" wire:click="clearCategoryFilter" class="hit inline-flex h-11 shrink-0 items-center px-3 text-sm font-medium text-accent-700 hover:underline">{{ __('storefront.search.category_filter_clear') }}</button>
                @endif
            </div>
        @endif

        <div class="mt-4" id="list">
            <div wire:loading.block wire:target="toggleCategory,clearCategoryFilter" aria-busy="true" class="hidden overflow-hidden rounded-md border border-border-strong bg-surface">
                <ul>
                    @for ($i = 0; $i < 8; $i++)
                        <x-storefront.product-row-skeleton />
                    @endfor
                </ul>
            </div>

            <div wire:loading.remove wire:target="toggleCategory,clearCategoryFilter">
                @if (! $hasQuery)
                    {{-- No query yet: never a dead end, offer the categories. --}}
                    <div class="rounded-md border border-border-strong">
                        <x-empty-state variant="empty" :title="__('storefront.search.no_query_title')" :description="__('storefront.search.no_query_body')" />
                    </div>
                    <div class="mt-4 grid-balanced [--cols:2] {{ $navCategories->count() % 4 === 0 ? 'md:[--cols:4]' : 'md:[--cols:3]' }}">
                        @foreach ($navCategories as $navCategory)
                            <x-storefront.tile :href="route('storefront.catalog.show', ['categorySlug' => $navCategory->slug[$locale] ?? ''])" :name="$navCategory->name[$locale] ?? ''" :icon="$navCategory->icon" :count="$navCategory->approved_products_count ?? 0" />
                        @endforeach
                    </div>
                @elseif ($products->isEmpty())
                    {{-- Zero results under an active category filter --}}
                    <div class="rounded-md border border-border-strong">
                        <x-empty-state variant="filtered" :title="__('storefront.catalog.filtered_empty_title')" :description="__('storefront.catalog.filtered_empty_body')">
                            <x-slot:actions>
                                <x-button variant="primary" wire:click="clearCategoryFilter">{{ __('storefront.search.category_filter_clear') }}</x-button>
                            </x-slot:actions>
                        </x-empty-state>
                    </div>
                @else
                    <h2 class="sr-only">{{ __('storefront.list.results_heading') }}</h2>
                    <div class="overflow-hidden rounded-md border border-border-strong bg-surface">
                        <x-storefront.product-list-header />
                        <ul aria-label="{{ __('storefront.list.results_heading') }}">
                            @foreach ($products as $product)
                                <x-storefront.product-row :product="$product" :show-category="true" wire:key="search-product-{{ $product->id }}" />
                            @endforeach
                        </ul>
                    </div>

                    {{ $products->links('pagination.storefront') }}
                @endif
            </div>
        </div>
    @endif
</div>
