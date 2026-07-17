@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
@endphp

<div class="flex flex-col gap-12 md:gap-16">
    {{--
        Intro — the page's only <h1> plus a self-contained factual
        definition of what Ribbon is. Kept as plain, unambiguous prose
        (not marketing copy) so an AI answer engine can lift this
        paragraph directly when asked "what is Ribbon" / "what does
        ribbon.uz sell". Meta title/description/JSON-LD are seo-engineer's
        concern; this is the visible, server-rendered equivalent for
        generative crawlers that don't execute JS.
    --}}
    <section aria-labelledby="home-intro-heading" class="flex flex-col gap-3">
        <h1 id="home-intro-heading" class="text-2xl font-bold tracking-tight text-text-primary md:text-3xl">
            {{ __('storefront.home.h1') }}
        </h1>
        <p class="max-w-3xl text-base text-text-secondary md:text-lg">
            {{ __('storefront.home.intro') }}
        </p>
    </section>

    {{-- Hero banners (home_hero placement, isCurrentlyLive() only,
         sort_order) — a single banner renders statically; 2+ becomes an
         auto-advancing carousel (dots + arrows, pauses on hover/focus/
         touch, respects prefers-reduced-motion). See
         x-storefront.hero-carousel. --}}
    @if ($heroBanners->isNotEmpty())
        <section aria-label="{{ __('storefront.home.categories_heading') }}">
            <x-storefront.hero-carousel :banners="$heroBanners" :locale="$locale" :default-locale="$defaultLocale" />
        </section>
    @endif

    {{-- Category navigation --}}
    <section aria-labelledby="home-categories-heading">
        <h2 id="home-categories-heading" class="text-xl font-bold tracking-tight text-text-primary">{{ __('storefront.home.categories_heading') }}</h2>

        @if ($categories->isNotEmpty())
            {{-- Live count, not hardcoded copy — recomputed from $categories on every render. --}}
            <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.home.categories_count', ['count' => $categories->count()]) }}</p>
        @endif

        @if ($categories->isEmpty())
            <div class="mt-4 rounded-xl border border-dashed border-border p-8 text-center">
                <p class="text-base font-medium text-text-primary">{{ __('storefront.home.no_categories_title') }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.home.no_categories_body') }}</p>
            </div>
        @else
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                @foreach ($categories as $category)
                    <a
                        href="{{ route('storefront.catalog.show', ['categorySlug' => $category->slug[$locale] ?? '']) }}"
                        wire:navigate
                        class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface-raised p-5 text-center shadow-xs transition-all hover:-translate-y-0.5 hover:border-accent-200 hover:shadow-sm"
                    >
                        @if ($category->image_path)
                            <span class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-surface-subtle ring-1 ring-border transition-colors group-hover:ring-accent-200">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) }}" alt="" class="h-full w-full object-cover">
                            </span>
                        @else
                            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-surface-subtle text-text-muted ring-1 ring-border transition-colors group-hover:ring-accent-200">
                                <svg class="h-6 w-6" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.3" />
                                </svg>
                            </span>
                        @endif
                        <span class="line-clamp-2 text-sm font-semibold text-text-primary">{{ $category->name[$locale] ?? ($category->name[$defaultLocale] ?? '') }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Secondary banners (home_secondary placement) --}}
    @if ($secondaryBanners->isNotEmpty())
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($secondaryBanners as $banner)
                @php $bannerTitle = $banner->title[$locale] ?? ($banner->title[$defaultLocale] ?? ''); @endphp
                <x-storefront.banner-frame :href="$banner->link_url" class="group relative block overflow-hidden rounded-xl bg-surface-subtle">
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banner->image_path) }}"
                        alt="{{ $bannerTitle }}"
                        class="aspect-[16/9] w-full object-cover"
                        loading="lazy"
                    >
                    @if ($bannerTitle !== '')
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-900/70 to-transparent p-3">
                            <p class="text-sm font-semibold text-white">{{ $bannerTitle }}</p>
                        </div>
                    @endif
                </x-storefront.banner-frame>
            @endforeach
        </section>
    @endif

    {{-- Recently added products --}}
    @if ($recentProducts->isNotEmpty())
        <section aria-labelledby="home-products-heading">
            <h2 id="home-products-heading" class="text-xl font-bold tracking-tight text-text-primary">{{ __('storefront.home.products_heading') }}</h2>
            <div class="mt-4">
                <x-storefront.product-rail :products="$recentProducts" />
            </div>
        </section>
    @endif

    {{-- "From Ribbon" — educational content teaser (history, ribbon types,
         use cases, technical explainers), the content-marketing section a
         normal e-commerce home page has. Hidden entirely when nothing's
         published yet, rather than showing an empty state on the home
         page — see Storefront\Home::render(). --}}
    @if ($recentArticles->isNotEmpty())
        <section aria-labelledby="home-articles-heading">
            <div class="flex items-baseline justify-between">
                <h2 id="home-articles-heading" class="text-xl font-bold tracking-tight text-text-primary">{{ __('storefront.home.articles_heading') }}</h2>
                <a href="{{ route('storefront.articles.index') }}" wire:navigate class="text-sm font-medium text-accent-700 hover:underline">
                    {{ __('storefront.home.articles_view_all') }}
                </a>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-x-8 gap-y-10 sm:grid-cols-3">
                @foreach ($recentArticles as $article)
                    <x-storefront.article-card :article="$article" />
                @endforeach
            </div>
        </section>
    @endif
</div>
