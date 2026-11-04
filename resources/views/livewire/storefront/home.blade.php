@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
@endphp

<div class="flex flex-col gap-10 md:gap-16">
    {{--
        Intro - the page's only <h1> plus a self-contained factual
        definition of what Ribbon is, kept as plain server-rendered prose
        (GEO: extractable "what is Ribbon" answer). The hero search is the
        page's search (header search is hidden here).
    --}}
    <section aria-labelledby="home-intro-heading" >
        <h1 id="home-intro-heading" class="max-w-3xl text-xl font-bold text-balance text-text-primary md:text-2xl lg:text-3xl">
            {{ __('storefront.home.h1') }}
        </h1>
        <p class="mt-2 max-w-3xl text-base text-text-secondary">
            {{ __('storefront.home.intro') }}
        </p>
        <x-storefront.search-form id="home-search" hero class="mt-4 max-w-2xl md:mt-6" />
        <p class="mt-3 text-sm text-text-muted">{{ __('storefront.home.search_hint') }}</p>

        {{-- Reassurance strip (spec 20 8.3): four facts that hold for every seller; copy in lang storefront.trust.* --}}
        <x-storefront.trust-strip class="mt-6 md:mt-8" />
    </section>

    {{-- Category tiles (real links; this grid is the home page's category nav) --}}
    <section aria-labelledby="home-categories-heading">
        <x-storefront.section-heading id="home-categories-heading" :title="__('storefront.home.categories_heading')" />

        @if ($categories->isEmpty())
            <div class="mt-4 rounded-md border border-border-strong">
                <x-empty-state variant="empty" :title="__('storefront.home.no_categories_title')" :description="__('storefront.home.no_categories_body')" />
            </div>
        @else
            <div class="mt-4 grid grid-cols-2 gap-3 md:gap-4 {{ $categories->count() % 3 === 0 && $categories->count() % 4 !== 0 ? 'md:grid-cols-3' : 'md:grid-cols-4' }}">
                @foreach ($categories as $category)
                    <x-storefront.tile
                        :href="route('storefront.catalog.show', ['categorySlug' => $category->slug[$locale] ?? ''])"
                        :name="$category->name[$locale] ?? ($category->name[$defaultLocale] ?? '')"
                        :count="$category->approved_products_count ?? 0"
                        :image="$category->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) : null"
                    />
                @endforeach
            </div>
        @endif
    </section>

    {{-- How it works (GEO): plain factual process, answers "how does Ribbon work / can I buy online". --}}
    <section aria-labelledby="home-how-heading" class="max-w-3xl">
        <h2 id="home-how-heading" class="text-lg font-semibold text-text-primary md:text-xl">{{ __('storefront.home.how_heading') }}</h2>
        <ol class="mt-3 list-decimal space-y-1 pl-5 text-base text-text-secondary">
            <li>{{ __('storefront.home.how_1') }}</li>
            <li>{{ __('storefront.home.how_2') }}</li>
            <li>{{ __('storefront.home.how_3') }}</li>
        </ol>
    </section>

    {{-- Hero banners (home_hero): one block, carousel when 2+ --}}
    @if ($heroBanners->isNotEmpty())
        <section aria-label="{{ __('storefront.home.hero_carousel_label') }}">
            <x-storefront.hero-carousel :banners="$heroBanners" :locale="$locale" :default-locale="$defaultLocale" />
        </section>
    @endif

    {{-- Secondary banners (home_secondary) --}}
    @if ($secondaryBanners->isNotEmpty())
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($secondaryBanners as $banner)
                @php $bannerTitle = $banner->title[$locale] ?? ($banner->title[$defaultLocale] ?? ''); @endphp
                <x-storefront.banner-frame :href="$banner->link_url" class="block overflow-hidden rounded-lg border border-border bg-surface-subtle hover:border-accent-700">
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banner->image_path) }}"
                        alt="{{ $bannerTitle }}"
                        class="aspect-[16/9] w-full object-cover"
                        loading="lazy"
                    >
                    @if ($bannerTitle !== '')
                        <p class="border-t border-border bg-surface-band px-3 py-2 text-sm font-semibold text-text-primary">{{ $bannerTitle }}</p>
                    @endif
                </x-storefront.banner-frame>
            @endforeach
        </section>
    @endif

    {{-- Recently added: the same rows as catalog (max 8) --}}
    @if ($recentProducts->isNotEmpty())
        <section aria-labelledby="home-products-heading">
            <x-storefront.section-heading id="home-products-heading" :title="__('storefront.home.products_heading')" />
            <div class="mt-4 overflow-hidden rounded-md border border-border-strong bg-surface">
                <x-storefront.product-list-header />
                <ul aria-label="{{ __('storefront.home.products_heading') }}">
                    @foreach ($recentProducts as $product)
                        <x-storefront.product-row :product="$product" :show-category="true" wire:key="home-product-{{ $product->id }}" />
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- "From Ribbon" articles teaser --}}
    @if ($recentArticles->isNotEmpty())
        <section aria-labelledby="home-articles-heading">
            <x-storefront.section-heading id="home-articles-heading" :title="__('storefront.home.articles_heading')" :href="route('storefront.articles.index')" :link-label="__('storefront.home.articles_view_all')" />
            <p class="mt-2 text-sm">
                <a href="{{ route('storefront.news.index') }}" wire:navigate class="inline-flex h-11 items-center font-medium text-accent-700 hover:underline">{{ __('storefront.nav.news') }}</a>
            </p>
            <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-3">
                @foreach ($recentArticles as $article)
                    <x-storefront.article-card :article="$article" />
                @endforeach
            </div>
        </section>
    @endif
</div>
