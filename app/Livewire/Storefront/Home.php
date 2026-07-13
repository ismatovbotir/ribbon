<?php

namespace App\Livewire\Storefront;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductAnalyticsService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class Home extends Component
{
    public function render()
    {
        $navCategories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $heroBanners = Banner::query()
            ->where('placement', 'home_hero')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Banner $banner) => $banner->isCurrentlyLive())
            ->values();

        $secondaryBanners = Banner::query()
            ->where('placement', 'home_secondary')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Banner $banner) => $banner->isCurrentlyLive())
            ->values();

        // No "featured" flag exists on products (see CLAUDE.md) — the most
        // recently approved products are the closest stand-in for a home
        // page "what's new" section without inventing a new model concept.
        $recentProducts = Product::query()
            ->where('status', 'approved')
            ->with([
                'category',
                'brand',
                'seller',
                'parameterValues.categoryParameter',
                'parameterValues.options.categoryParameterOption',
                // No `->limit(1)` here on purpose: limiting a hasMany
                // eager-load closure applies the LIMIT to the whole
                // grouped query, not per-parent-product, which would
                // silently starve every product after the first of its
                // cover image. A product has at most 4 images anyway (see
                // ProductImage::bootProductImage()), so eager-loading all
                // of them and taking ->first() in the card component is
                // cheap.
                'images',
                'prices' => fn ($query) => $query->where('is_vitrin', true),
            ])
            ->orderByDesc('moderated_at')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        // Impressions recorded once per real page load, not on every
        // Livewire re-render — see ProductAnalyticsService's docblock.
        // Home has no filters/pagination to trigger those, but the guard
        // is kept consistent with Catalog\Show/Search regardless.
        if (! request()->hasHeader('X-Livewire')) {
            ProductAnalyticsService::recordSearchAppearances($recentProducts);
        }

        // --- SEO (seo-engineer) ---
        //
        // Locale is switched via a `?lang=` query param + session (see
        // SetLocale middleware), not a URL path prefix — there is no
        // /uz/, /ru/, /en/ segment. That means the *bare* `/` URL always
        // resolves to the default locale (uz) for a stateless crawler with
        // no session cookie, and the ru/en experiences only exist at
        // `/?lang=ru` / `/?lang=en`. Those are genuinely different content
        // (different language), not duplicates of `/`, so each locale gets
        // its own self-referential canonical rather than collapsing all
        // three onto the bare URL — hreflang ties the three together.
        $locale = app()->getLocale();
        $defaultLocale = config('ribbon.locales')[0];

        $canonicalUrl = $locale === $defaultLocale
            ? url('/')
            : url('/').'?lang='.$locale;

        $hreflangAlternates = [];

        foreach (config('ribbon.locales') as $loc) {
            $hreflangAlternates[$loc] = $loc === $defaultLocale ? url('/') : url('/').'?lang='.$loc;
        }

        $hreflangAlternates['x-default'] = $hreflangAlternates[$defaultLocale];

        // First live hero banner doubles as the share/OG image when one
        // exists — no dedicated site logo/default-share-image asset exists
        // yet (see seo-engineer follow-up notes), so this is left null
        // (tags omitted) rather than pointing at a placeholder.
        $ogImage = $heroBanners->isNotEmpty()
            ? Storage::disk('public')->url($heroBanners->first()->image_path)
            : null;

        $structuredData = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => __('storefront.nav.brand'),
                'url' => url('/'),
                'inLanguage' => config('ribbon.locales'),
                // `/search` is the same target the header search form
                // already posts to (layouts/storefront.blade.php) — no
                // /search route is registered yet (routes/web.php), a
                // pre-existing gap this schema inherits rather than
                // introduces; flagged as a follow-up.
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => url('/search').'?q={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => __('storefront.nav.brand'),
                'url' => url('/'),
                'description' => __('storefront.footer.tagline'),
            ],
        ];

        return view('livewire.storefront.home', [
            'heroBanners' => $heroBanners,
            'secondaryBanners' => $secondaryBanners,
            'categories' => $navCategories,
            'recentProducts' => $recentProducts,
        ])->layout('layouts.storefront', [
            'title' => __('storefront.seo.home_title'),
            'metaDescription' => __('storefront.seo.home_description'),
            'canonicalUrl' => $canonicalUrl,
            'hreflangAlternates' => $hreflangAlternates,
            'ogImage' => $ogImage,
            'structuredData' => $structuredData,
            'navCategories' => $navCategories,
        ]);
    }
}
