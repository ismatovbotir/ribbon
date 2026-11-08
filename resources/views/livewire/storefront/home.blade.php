@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
@endphp

{{-- Every section sits on the page's central axis or fills the full width in equal columns. --}}
<div class="flex flex-col gap-12 md:gap-20">
    {{--
        Intro - the page's only <h1> plus a self-contained factual
        definition of what Ribbon is, kept as plain server-rendered prose
        (GEO: extractable "what is Ribbon" answer).
    --}}
    <section aria-labelledby="home-intro-heading">
        <div class="mx-auto max-w-4xl text-center">
            <h1 id="home-intro-heading" class="text-2xl font-bold text-balance text-text-primary md:text-3xl">
                {{ __('storefront.home.h1') }}
            </h1>
            <p class="mx-auto mt-4 max-w-3xl text-base text-pretty text-text-secondary">
                {{ __('storefront.home.intro') }}
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <x-button tag="a" variant="primary" size="lg" href="#home-categories-heading">{{ __('storefront.home.categories_heading') }}</x-button>
                <x-button tag="a" variant="secondary" size="lg" href="#home-how-heading">{{ __('storefront.home.how_heading') }}</x-button>
            </div>
        </div>

        {{-- Reassurance strip (spec 20 8.3): four facts that hold for every seller; copy in lang storefront.trust.* --}}
        <x-storefront.trust-strip class="mt-8 md:mt-12" />
    </section>

    {{-- Category tiles (real links; this grid is the home page's category nav) --}}
    <section aria-labelledby="home-categories-heading">
        <x-storefront.section-heading center id="home-categories-heading" :title="__('storefront.home.categories_heading')" />

        @if ($categories->isEmpty())
            <div class="mt-6 rounded-md border border-border-strong">
                <x-empty-state variant="empty" :title="__('storefront.home.no_categories_title')" :description="__('storefront.home.no_categories_body')" />
            </div>
        @else
            <div class="grid-balanced mt-6 [--cols:2] {{ $categories->count() % 4 === 0 ? 'md:[--cols:4]' : 'md:[--cols:3]' }}">
                @foreach ($categories as $category)
                    <x-storefront.tile
                        :href="route('storefront.catalog.show', ['categorySlug' => $category->slug[$locale] ?? ''])"
                        :name="$category->name[$locale] ?? ($category->name[$defaultLocale] ?? '')"
                        :count="$category->approved_products_count ?? 0"
                        :icon="$category->icon"
                        :image="$category->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) : null"
                    />
                @endforeach
            </div>
        @endif
    </section>

    {{-- How it works (GEO): plain factual process, answers "how does Ribbon work / can I buy online". --}}
    <section aria-labelledby="home-how-heading">
        <x-storefront.section-heading center id="home-how-heading" :title="__('storefront.home.how_heading')" />
        <ol class="mt-6 grid gap-4 md:grid-cols-3">
            @foreach ([1, 2, 3] as $step)
                <li class="flex flex-col items-center gap-3 rounded-md border border-border bg-surface-band p-6 text-center">
                    <span aria-hidden="true" class="flex size-10 items-center justify-center rounded-sm bg-accent-700 font-mono text-base font-semibold text-white">{{ $step }}</span>
                    <p class="text-sm text-pretty text-text-secondary">{{ __('storefront.home.how_'.$step) }}</p>
                </li>
            @endforeach
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
        <section class="grid-balanced sm:[--cols:2] lg:[--cols:3]">
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
                        <p class="border-t border-border bg-surface-band px-3 py-2 text-center text-sm font-semibold text-text-primary">{{ $bannerTitle }}</p>
                    @endif
                </x-storefront.banner-frame>
            @endforeach
        </section>
    @endif

    {{-- Recently added: the same rows as catalog (max 8) --}}
    @if ($recentProducts->isNotEmpty())
        <section aria-labelledby="home-products-heading">
            <x-storefront.section-heading center id="home-products-heading" :title="__('storefront.home.products_heading')" />
            <div class="mt-6 overflow-hidden rounded-md border border-border-strong bg-surface">
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
            <x-storefront.section-heading center id="home-articles-heading" :title="__('storefront.home.articles_heading')" :href="route('storefront.articles.index')" :link-label="__('storefront.home.articles_view_all')">
                <a href="{{ route('storefront.news.index') }}" wire:navigate class="inline-flex h-11 items-center gap-1 text-sm font-medium text-accent-700 hover:underline">{{ __('storefront.nav.news') }}<x-storefront.icon name="arrow-right" class="size-4" stroke="2" /></a>
            </x-storefront.section-heading>
            <div class="grid-balanced mt-6 [--gap:1.5rem] sm:[--cols:2] lg:[--cols:3]">
                @foreach ($recentArticles as $article)
                    <x-storefront.article-card :article="$article" />
                @endforeach
            </div>
        </section>
    @endif
</div>
