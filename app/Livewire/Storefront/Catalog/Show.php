<?php

namespace App\Livewire\Storefront\Catalog;

use App\Models\Banner;
use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\Product;
use App\Models\ProductParameterValue;
use App\Models\ProductPrice;
use App\Services\ProductAnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use WithPagination;

    public Category $category;

    public const PER_PAGE = 24;

    public const SORTS = ['newest', 'price_asc', 'price_desc'];

    #[Url(as: 'sort', history: true)]
    public string $sort = 'newest';

    /**
     * select_single/select_multiple filters, keyed by category_parameter_id
     * (arrives as a string array key from the URL, coerced back to int
     * wherever it's used) => array of selected category_parameter_option_id
     * strings. Rendered as checkbox lists regardless of the parameter's own
     * authored single/multiple type — see docs/design/11.
     *
     * @var array<int|string, array<int, string>>
     */
    #[Url(as: 'f', history: true)]
    public array $selected = [];

    /**
     * `number`-type filters, keyed by category_parameter_id => the raw
     * string min/max the buyer typed.
     *
     * @var array<int|string, string>
     */
    #[Url(as: 'min', history: true)]
    public array $numMin = [];

    #[Url(as: 'max', history: true)]
    public array $numMax = [];

    public bool $showMobileFilters = false;

    /**
     * Resolves the category from its per-locale JSON slug — Category.slug
     * is a JSON column (no plain unique column Laravel's implicit
     * route-model-binding can bind against), so this is a manual lookup
     * against the current locale first, falling back across the other two
     * locales (a buyer who switches language while on a category page
     * reuses the same URL segment via the `?lang=` mechanism — see
     * layouts/storefront.blade.php — so the old-locale slug must still
     * resolve). If the resolved category's *current-locale* slug differs
     * from the URL segment actually requested, redirect to the canonical
     * current-locale URL rather than silently rendering under a stale
     * slug.
     *
     * Parameter deliberately named `$categorySlug`, matching the route's
     * `{categorySlug}` segment — not `$category`, which would collide with
     * the `public Category $category` property below and trip Livewire's
     * implicit route-model-binding (it matches route parameter names
     * against public property names/types on full-page components,
     * independent of this method's own signature) into trying to bind the
     * raw slug string against the Category model directly.
     */
    public function mount(string $categorySlug): void
    {
        $locales = config('ribbon.locales');
        $currentLocale = app()->getLocale();

        $found = Category::query()
            ->where('is_active', true)
            ->where("slug->{$currentLocale}", $categorySlug)
            ->first();

        if (! $found) {
            foreach ($locales as $locale) {
                if ($locale === $currentLocale) {
                    continue;
                }

                $found = Category::query()
                    ->where('is_active', true)
                    ->where("slug->{$locale}", $categorySlug)
                    ->first();

                if ($found) {
                    break;
                }
            }
        }

        abort_unless($found, 404);

        $canonicalSlug = $found->slug[$currentLocale] ?? null;

        if ($canonicalSlug && $canonicalSlug !== $categorySlug) {
            $this->redirect(route('storefront.catalog.show', ['categorySlug' => $canonicalSlug]));

            return;
        }

        $this->category = $found->load(['parameters' => function ($query) {
            // Buyer-facing filter order must match the order the admin who
            // built the category chose (docs/design/11) — parameters()
            // already orders by sort_order, but CategoryParameter::options()
            // has no default ordering, so it's applied explicitly here.
            $query->where('is_filterable', true)->with(['options' => fn ($q) => $q->orderBy('sort_order')]);
        }]);

        if (! in_array($this->sort, self::SORTS, true)) {
            $this->sort = 'newest';
        }
    }

    public function updatedSelected(): void
    {
        $this->resetPage();
    }

    public function updatedNumMin(): void
    {
        $this->resetPage();
    }

    public function updatedNumMax(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function removeOptionFilter(int $parameterId, string $optionId): void
    {
        $this->selected[$parameterId] = array_values(array_filter(
            $this->selected[$parameterId] ?? [],
            fn ($id) => (string) $id !== $optionId,
        ));

        if (empty($this->selected[$parameterId])) {
            unset($this->selected[$parameterId]);
        }

        $this->resetPage();
    }

    public function removeNumberFilter(int $parameterId, string $bound): void
    {
        if ($bound === 'min') {
            unset($this->numMin[$parameterId]);
        } else {
            unset($this->numMax[$parameterId]);
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->selected = [];
        $this->numMin = [];
        $this->numMax = [];
        $this->resetPage();
    }

    public function closeMobileFilters(): void
    {
        $this->showMobileFilters = false;
        $this->resetPage();
    }

    /**
     * Builds the approved-products-in-this-category query with every
     * currently applied filter, optionally skipping one parameter's own
     * filter — used both for the actual result set (no exclusion) and for
     * that parameter's own facet counts (excluded, so a filter group shows
     * "what happens if I also pick this option" rather than the
     * already-narrowed-by-itself count).
     */
    protected function filteredProductsQuery(?int $excludeParameterId = null): Builder
    {
        $query = Product::query()
            ->where('category_id', $this->category->id)
            ->where('status', 'approved');

        foreach ($this->selected as $parameterId => $optionIds) {
            $parameterId = (int) $parameterId;

            if ($excludeParameterId === $parameterId) {
                continue;
            }

            $optionIds = array_values(array_filter((array) $optionIds, fn ($id) => $id !== null && $id !== ''));

            if (empty($optionIds)) {
                continue;
            }

            $query->whereHas('parameterValues', function ($q) use ($parameterId, $optionIds) {
                $q->where('category_parameter_id', $parameterId)
                    ->whereHas('options', function ($q2) use ($optionIds) {
                        $q2->whereIn('category_parameter_option_id', $optionIds);
                    });
            });
        }

        $numberParameterIds = array_unique(array_merge(array_keys($this->numMin), array_keys($this->numMax)));

        foreach ($numberParameterIds as $parameterId) {
            $parameterId = (int) $parameterId;

            if ($excludeParameterId === $parameterId) {
                continue;
            }

            $min = $this->numMin[$parameterId] ?? null;
            $max = $this->numMax[$parameterId] ?? null;
            $min = is_numeric($min) ? (float) $min : null;
            $max = is_numeric($max) ? (float) $max : null;

            if ($min === null && $max === null) {
                continue;
            }

            $query->whereHas('parameterValues', function ($q) use ($parameterId, $min, $max) {
                $q->where('category_parameter_id', $parameterId);

                if ($min !== null) {
                    $q->where('value_number', '>=', $min);
                }

                if ($max !== null) {
                    $q->where('value_number', '<=', $max);
                }
            });
        }

        return $query;
    }

    /**
     * Facet counts for a single select_single/select_multiple parameter's
     * options, reflecting results under every *other* currently applied
     * filter (see filteredProductsQuery()'s $excludeParameterId param) —
     * one count query per option, acceptable for the option-list sizes
     * this domain's category parameters realistically have (v1 scope, not
     * pre-optimized for hundreds of options).
     *
     * @return array<int, int> option_id => count
     */
    protected function facetCounts(CategoryParameter $parameter): array
    {
        $base = $this->filteredProductsQuery($parameter->id);
        $counts = [];

        foreach ($parameter->options as $option) {
            $counts[$option->id] = (clone $base)->whereHas('parameterValues', function ($q) use ($parameter, $option) {
                $q->where('category_parameter_id', $parameter->id)
                    ->whereHas('options', fn ($q2) => $q2->where('category_parameter_option_id', $option->id));
            })->count();
        }

        return $counts;
    }

    public function render()
    {
        $navCategories = Category::query()->where('is_active', true)->orderBy('sort_order')->get();

        $filterableParameters = $this->category->parameters;

        $facetCounts = $filterableParameters
            ->filter(fn (CategoryParameter $parameter) => in_array($parameter->type, ['select_single', 'select_multiple'], true))
            ->mapWithKeys(fn (CategoryParameter $parameter) => [$parameter->id => $this->facetCounts($parameter)]);

        $query = $this->filteredProductsQuery()->with([
            'category',
            'brand',
            'seller',
            'parameterValues.categoryParameter',
            'parameterValues.options.categoryParameterOption',
            // No `->limit(1)` here on purpose — see the identical comment
            // in Storefront\Home::render(); limiting a hasMany eager-load
            // closure applies globally across the grouped query, not
            // per-parent-product.
            'images',
            'prices' => fn ($q) => $q->where('is_vitrin', true),
        ]);

        if ($this->sort === 'price_asc' || $this->sort === 'price_desc') {
            $query->addSelect('products.*')->addSelect([
                'vitrin_price' => ProductPrice::query()
                    ->select('price')
                    ->whereColumn('product_id', 'products.id')
                    ->where('is_vitrin', true)
                    ->limit(1),
            ])->orderBy('vitrin_price', $this->sort === 'price_asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('created_at');
        }

        $products = $query->paginate(self::PER_PAGE)->withQueryString();

        // Impressions recorded once per real page load, not on every
        // Livewire re-render a filter/sort change or pagination click
        // triggers within the same visit — see ProductAnalyticsService's
        // docblock for why this is a deliberate v1 scope cut.
        if (! request()->hasHeader('X-Livewire')) {
            ProductAnalyticsService::recordSearchAppearances($products);
        }

        $categoryHasAnyProducts = Product::query()
            ->where('category_id', $this->category->id)
            ->where('status', 'approved')
            ->exists();

        $hasActiveFilters = ! empty(array_filter($this->selected)) || ! empty($this->numMin) || ! empty($this->numMax);

        // category_top banner: targeted to this category takes priority,
        // falling back to a generic (category_id null) one — see
        // docs/design/11-storefront-catalog-filters.md's flagged model gap,
        // resolved via the added banners.category_id column.
        $categoryTopBanner = Banner::query()
            ->where('placement', 'category_top')
            ->where('category_id', $this->category->id)
            ->orderBy('sort_order')
            ->get()
            ->first(fn (Banner $banner) => $banner->isCurrentlyLive());

        if (! $categoryTopBanner) {
            $categoryTopBanner = Banner::query()
                ->where('placement', 'category_top')
                ->whereNull('category_id')
                ->orderBy('sort_order')
                ->get()
                ->first(fn (Banner $banner) => $banner->isCurrentlyLive());
        }

        // --- SEO (seo-engineer) ---
        //
        // Locale is switched via a `?lang=` query param + session (see
        // SetLocale middleware), not a URL path prefix. Category.slug is
        // itself per-locale JSON, so each locale already gets its own path
        // segment (mount() resolves/canonicalizes against it) — but a
        // stateless crawler with no session cookie still needs `?lang=`
        // appended for any non-default locale, or the SetLocale middleware
        // falls back to the default (uz) and the requested slug won't
        // match. Canonical/hreflang below account for both.
        $locale = app()->getLocale();
        $defaultLocale = config('ribbon.locales')[0];
        $categoryName = $this->category->name[$locale] ?? ($this->category->name[$defaultLocale] ?? '');

        $categorySlugForLocale = $this->category->slug[$locale] ?? ($this->category->slug[$defaultLocale] ?? '');
        $baseUrl = route('storefront.catalog.show', ['categorySlug' => $categorySlugForLocale]);

        // Faceted filters (`f`/`min`/`max`) and non-default sort produce
        // near-duplicate subsets/reorderings of the same underlying
        // category, not distinct indexable content — canonicalize away to
        // the clean, unfiltered, default-sort URL and mark noindex,follow
        // (crawl links, don't index the combination itself). Pagination
        // (`page`) is left alone: page 2+ genuinely has different products,
        // so it gets its own self-referential canonical, matching current
        // Google guidance for paginated series.
        $hasQueryNoise = ! empty(array_filter($this->selected)) || ! empty($this->numMin) || ! empty($this->numMax) || $this->sort !== 'newest';
        $robots = $hasQueryNoise ? 'noindex,follow' : 'index,follow';

        $page = $products->currentPage();

        $canonicalQuery = [];

        // Only preserve the page number when this *is* the clean,
        // default-sort, unfiltered series — a filtered/sorted "page 2" has
        // no stable canonical counterpart (the underlying result set
        // itself is thin/noindexed above), so those collapse straight to
        // page 1 of the clean listing instead of a made-up page number.
        if ($page > 1 && ! $hasQueryNoise) {
            $canonicalQuery['page'] = $page;
        }

        if ($locale !== $defaultLocale) {
            $canonicalQuery['lang'] = $locale;
        }

        $canonicalUrl = $baseUrl.($canonicalQuery !== [] ? '?'.http_build_query($canonicalQuery) : '');

        $hreflangAlternates = [];

        foreach (config('ribbon.locales') as $loc) {
            $slugForLoc = $this->category->slug[$loc] ?? null;

            if (! $slugForLoc) {
                // Category.name/slug are required in all 3 locales at the
                // validation layer, so this shouldn't happen in practice —
                // guarded defensively rather than emitting a broken
                // hreflang link.
                continue;
            }

            $altUrl = route('storefront.catalog.show', ['categorySlug' => $slugForLoc]);

            if ($loc !== $defaultLocale) {
                $altUrl .= '?lang='.$loc;
            }

            $hreflangAlternates[$loc] = $altUrl;
        }

        if (isset($hreflangAlternates[$defaultLocale])) {
            $hreflangAlternates['x-default'] = $hreflangAlternates[$defaultLocale];
        }

        // Filterable parameter *names* (not values) — buyers in this
        // industry search by exact spec (width, core size, material,
        // compatible printer model), so surfacing what's filterable here is
        // more useful in a SERP snippet than generic marketing copy.
        $specNames = $filterableParameters
            ->map(fn (CategoryParameter $parameter) => $parameter->name[$locale] ?? ($parameter->name[$defaultLocale] ?? null))
            ->filter()
            ->values();

        $categoryTitle = __('storefront.seo.catalog_title', ['category' => $categoryName]);

        $metaDescription = $specNames->isNotEmpty()
            ? __('storefront.seo.catalog_description_with_specs', [
                'category' => $categoryName,
                'specs' => $specNames->take(4)->implode(', '),
            ])
            : __('storefront.seo.catalog_description', ['category' => $categoryName]);

        // category_top banner image first (already the page's own visual
        // header), falling back to the first visible product's cover image
        // — no dedicated default share image exists yet (see seo-engineer
        // follow-up notes), so this stays null (tags omitted) rather than a
        // placeholder when neither is available.
        $ogImage = null;

        if ($categoryTopBanner) {
            $ogImage = Storage::disk('public')->url($categoryTopBanner->image_path);
        } elseif ($products->isNotEmpty()) {
            $firstCover = $products->getCollection()->first()->images->first();

            if ($firstCover) {
                $ogImage = Storage::disk('public')->url($firstCover->path);
            }
        }

        $structuredData = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => __('storefront.catalog.breadcrumb_home'),
                        'item' => url('/'),
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $categoryName,
                        'item' => $baseUrl,
                    ],
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $categoryTitle,
                'description' => $metaDescription,
                'url' => $canonicalUrl,
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'numberOfItems' => $products->total(),
                    'itemListElement' => collect($products->items())
                        ->values()
                        ->map(fn (Product $product, int $index) => $this->productListItemJsonLd($product, $index + 1, $locale, $defaultLocale))
                        ->all(),
                ],
            ],
        ];

        return view('livewire.storefront.catalog.show', [
            'products' => $products,
            'filterableParameters' => $filterableParameters,
            'facetCounts' => $facetCounts,
            'categoryHasAnyProducts' => $categoryHasAnyProducts,
            'hasActiveFilters' => $hasActiveFilters,
            'categoryTopBanner' => $categoryTopBanner,
        ])->layout('layouts.storefront', [
            'title' => $categoryTitle,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
            'hreflangAlternates' => $hreflangAlternates,
            'robots' => $robots,
            'ogImage' => $ogImage,
            'structuredData' => $structuredData,
            'navCategories' => $navCategories,
        ]);
    }

    /**
     * Builds a single ItemList entry (ListItem -> Product, with nested
     * Offer/PropertyValue specs) for the CollectionPage/ItemList JSON-LD
     * assembled in render() above. Requires `parameterValues.
     * categoryParameter`, `parameterValues.options.categoryParameterOption`,
     * `images`, `prices`, `category`, `brand`, and `seller` to already be
     * eager-loaded on $product (render()'s main query already does this) —
     * this method issues no queries of its own.
     *
     * additionalProperty (PropertyValue) entries surface the product's
     * filled category-parameter specs (width, core size, material,
     * compatible printer model, ...) directly in structured data, since
     * that's how B2B auto-ID buyers actually search rather than by
     * marketing copy.
     *
     * There is no seller-entered SKU/GTIN field on products today (see
     * Product model) — `sku` falls back to the product's internal ULID id,
     * which is not a true SKU; flagged as a seo-engineer follow-up.
     *
     * `storefront.products.show` (docs/design/12,
     * App\Livewire\Storefront\Products\Show) now exists — this was
     * previously a hardcoded `/products/{slug}` string with a "404s until
     * that page ships" caveat; now resolved via the named route like every
     * other storefront link.
     */
    protected function productListItemJsonLd(Product $product, int $position, string $locale, string $defaultLocale): array
    {
        $name = $product->localizedName($locale);
        $name = $name !== '' ? $name : $product->name;

        $productUrl = route('storefront.products.show', ['productSlug' => $product->slug[$locale] ?? $product->id]);

        $coverImage = $product->images->first();
        $vitrinPrice = $product->prices->firstWhere('is_vitrin', true);

        $additionalProperties = $product->parameterValues
            ->filter(fn (ProductParameterValue $value) => $value->categoryParameter !== null)
            ->map(function (ProductParameterValue $value) use ($locale, $defaultLocale) {
                $parameter = $value->categoryParameter;
                $paramName = $parameter->name[$locale] ?? ($parameter->name[$defaultLocale] ?? null);

                $display = match ($parameter->type) {
                    'text' => (string) $value->value_text,
                    'number' => $value->value_number !== null
                        ? rtrim(rtrim((string) $value->value_number, '0'), '.').($parameter->unit ?? '')
                        : '',
                    'select_single', 'select_multiple' => $value->options
                        ->map(fn ($option) => $option->categoryParameterOption?->value[$locale] ?? ($option->categoryParameterOption?->value[$defaultLocale] ?? ''))
                        ->filter(fn ($label) => $label !== '')
                        ->implode(', '),
                    default => '',
                };

                if ($paramName === null || $display === '') {
                    return null;
                }

                return ['@type' => 'PropertyValue', 'name' => $paramName, 'value' => $display];
            })
            ->filter()
            ->values()
            ->all();

        $productNode = array_filter([
            '@type' => 'Product',
            'name' => $name,
            'url' => $productUrl,
            'sku' => (string) $product->id,
            'category' => $product->category?->name[$locale] ?? ($product->category?->name[$defaultLocale] ?? null),
            'image' => $coverImage ? Storage::disk('public')->url($coverImage->path) : null,
            'brand' => ($product->brand && $product->brand->id !== 1)
                ? ['@type' => 'Brand', 'name' => $product->brand->name]
                : null,
            'additionalProperty' => $additionalProperties !== [] ? $additionalProperties : null,
        ], fn ($value) => $value !== null);

        if ($vitrinPrice) {
            $productNode['offers'] = array_filter([
                '@type' => 'Offer',
                'url' => $productUrl,
                'price' => (string) $vitrinPrice->price,
                'priceCurrency' => 'UZS',
                // No inventory/stock-quantity field exists on products
                // today (see ProductPrice model) — every approved listing
                // is assumed InStock as a simplification; flagged as a
                // follow-up if real stock tracking is ever added.
                'availability' => 'https://schema.org/InStock',
                'seller' => $product->seller ? ['@type' => 'Organization', 'name' => $product->seller->name] : null,
            ], fn ($value) => $value !== null);
        }

        return [
            '@type' => 'ListItem',
            'position' => $position,
            'item' => $productNode,
        ];
    }
}
