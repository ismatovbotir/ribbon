<?php

namespace App\Livewire\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductAnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Search extends Component
{
    use WithPagination;

    public const PER_PAGE = 24;

    /**
     * The search term, bound to `?q=` — the exact query param name the
     * header search form (layouts/storefront.blade.php, `name="q"`) already
     * submits and Storefront\Home's SearchAction JSON-LD already advertises
     * (`/search?q={search_term_string}`), so both existing integration
     * points line up with this page unchanged.
     */
    #[Url(as: 'q', history: true)]
    public string $query = '';

    /**
     * Cross-category "narrow by category" filter — the only filter this
     * page offers (docs/design/11's explicit v1 scope cut: categories share
     * no parameter vocabulary to build a faceted sidebar across, unlike a
     * single category page). Holds selected Category ids as strings (URL
     * query-string values arrive as strings). Rendered as a row of toggle
     * chips rather than Catalog\Show's full sidebar-plus-mobile-sheet
     * machinery, since a single filter dimension doesn't need that much
     * chrome — see the view for the rendering rationale.
     *
     * @var array<int, string>
     */
    #[Url(as: 'category', history: true)]
    public array $categoryIds = [];

    public function updatedQuery(): void
    {
        // A new search term can invalidate the current category selection
        // (a category that had matches under the old term may have none
        // under the new one) — cleared together so the buyer never lands on
        // a silently-still-applied filter that no longer makes sense.
        $this->categoryIds = [];
        $this->resetPage();
    }

    public function toggleCategory(int $categoryId): void
    {
        $categoryId = (string) $categoryId;

        if (in_array($categoryId, $this->categoryIds, true)) {
            $this->categoryIds = array_values(array_diff($this->categoryIds, [$categoryId]));
        } else {
            $this->categoryIds[] = $categoryId;
        }

        $this->resetPage();
    }

    public function clearCategoryFilter(): void
    {
        $this->categoryIds = [];
        $this->resetPage();
    }

    /**
     * Approved products matching the current search term against the
     * product's own composed `name` (fast LIKE; works across locales-ish
     * since brand/text/number-derived segments folded into `name` are
     * locale-invariant, even though any select-option segments in it aren't
     * — a known v1 limitation, not a full per-locale search index, per this
     * task's scope), the current locale's Category.name (JSON column), and
     * Brand.name. Plain `LIKE '%term%'` matches this project's established
     * search style (Admin\Products\Index, Admin\Sellers\Index) — no
     * full-text search engine for v1.
     *
     * $applyCategoryFilter is false when computing the category sidebar's
     * own facet counts, so each category's count reflects "how many results
     * match the search term overall", not "...within the currently selected
     * categories" (mirrors Catalog\Show's $excludeParameterId pattern for
     * the same reason).
     */
    protected function matchingProductsQuery(bool $applyCategoryFilter = true): Builder
    {
        $term = trim($this->query);
        $locale = app()->getLocale();

        $query = Product::query()->where('status', 'approved');

        if ($term !== '') {
            $query->where(function (Builder $q) use ($term, $locale) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhereHas('brand', fn (Builder $q2) => $q2->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('category', fn (Builder $q2) => $q2->where("name->{$locale}", 'like', "%{$term}%"));
            });
        } else {
            // No term at all — this page shows a "start searching" prompt,
            // not every approved product in the marketplace, so the query
            // deliberately matches nothing rather than falling through to
            // an implicit "browse everything" listing (that's the catalog
            // page's job, not search's).
            $query->whereRaw('1 = 0');
        }

        if ($applyCategoryFilter && ! empty($this->categoryIds)) {
            $query->whereIn('category_id', array_map('intval', $this->categoryIds));
        }

        return $query;
    }

    /**
     * Category facet counts for the current search term, ignoring the
     * category filter itself (see matchingProductsQuery()'s
     * $applyCategoryFilter param) — one grouped count query, not one query
     * per category, since this is a flat GROUP BY rather than the
     * per-option whereHas() Catalog\Show needs for select-type parameters.
     *
     * @return array<int, int> category_id => count
     */
    protected function categoryFacetCounts(): array
    {
        if (trim($this->query) === '') {
            return [];
        }

        return $this->matchingProductsQuery(applyCategoryFilter: false)
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function render()
    {
        $navCategories = Category::query()->where('is_active', true)->orderBy('sort_order')->get();

        $term = trim($this->query);
        $hasQuery = $term !== '';

        $facetCounts = $this->categoryFacetCounts();

        // Only surfaced as a filter when the current term actually spans
        // more than one category — with a single matching category,
        // filtering by it is a no-op that just adds UI clutter (see the
        // task's own scope note: keep this "at most" a category filter, add
        // it only where it adds real value).
        $filterCategories = count($facetCounts) > 1
            ? Category::query()->whereIn('id', array_keys($facetCounts))->orderBy('sort_order')->get()
            : collect();

        $products = $this->matchingProductsQuery()
            ->with([
                'category',
                'brand',
                'seller',
                'parameterValues.categoryParameter',
                'parameterValues.options.categoryParameterOption',
                // No `->limit(1)` here on purpose — see the identical
                // comment in Storefront\Home::render() / Catalog\Show::render().
                'images',
                'prices' => fn ($q) => $q->where('is_vitrin', true),
            ])
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Impressions recorded once per real page load, not on every
        // Livewire re-render a query/filter change or pagination click
        // triggers within the same visit — see ProductAnalyticsService's
        // docblock for why this is a deliberate v1 scope cut.
        if (! request()->hasHeader('X-Livewire')) {
            ProductAnalyticsService::recordSearchAppearances($products);
        }

        $hasActiveCategoryFilter = ! empty($this->categoryIds);

        // --- SEO ---
        //
        // Search-result pages are standard `noindex,follow` regardless of
        // query state (this task's own instructions, matching general SEO
        // practice for internal-search URLs) — crawl any linked products,
        // don't index the query-string permutations themselves. Still gets
        // correct hreflang/OG per the established layouts/storefront.blade.php
        // convention (see Catalog\Show/Products\Show for the identical
        // shape this mirrors).
        $locale = app()->getLocale();
        $defaultLocale = config('ribbon.locales')[0];

        $canonicalQuery = [];

        if ($term !== '') {
            $canonicalQuery['q'] = $term;
        }

        if ($hasActiveCategoryFilter) {
            $canonicalQuery['category'] = $this->categoryIds;
        }

        $page = $products->currentPage();

        if ($page > 1) {
            $canonicalQuery['page'] = $page;
        }

        if ($locale !== $defaultLocale) {
            $canonicalQuery['lang'] = $locale;
        }

        $baseUrl = route('storefront.search');
        $canonicalUrl = $baseUrl.($canonicalQuery !== [] ? '?'.http_build_query($canonicalQuery) : '');

        $hreflangAlternates = [];

        foreach (config('ribbon.locales') as $loc) {
            $altQuery = $canonicalQuery;
            unset($altQuery['lang']);

            if ($loc !== $defaultLocale) {
                $altQuery['lang'] = $loc;
            }

            $hreflangAlternates[$loc] = $baseUrl.($altQuery !== [] ? '?'.http_build_query($altQuery) : '');
        }

        $hreflangAlternates['x-default'] = $hreflangAlternates[$defaultLocale];

        $title = $hasQuery
            ? __('storefront.seo.search_title_with_query', ['query' => $term])
            : __('storefront.seo.search_title_empty');

        $metaDescription = $hasQuery
            ? __('storefront.seo.search_description_with_query', ['query' => $term, 'count' => $products->total()])
            : __('storefront.seo.search_description_empty');

        // First result's cover image, if any — same fallback rule
        // Catalog\Show/Home use when no dedicated default share image
        // exists yet (pre-existing gap, not introduced here).
        $ogImage = null;

        if ($products->isNotEmpty()) {
            $firstCover = $products->getCollection()->first()->images->first();

            if ($firstCover) {
                $ogImage = Storage::disk('public')->url($firstCover->path);
            }
        }

        return view('livewire.storefront.search', [
            'products' => $products,
            'hasQuery' => $hasQuery,
            'filterCategories' => $filterCategories,
            'facetCounts' => $facetCounts,
            'hasActiveCategoryFilter' => $hasActiveCategoryFilter,
        ])->layout('layouts.storefront', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
            'hreflangAlternates' => $hreflangAlternates,
            // Search-result pages are never indexed directly (see comment
            // above) — always noindex,follow, independent of query state.
            'robots' => 'noindex,follow',
            'ogImage' => $ogImage,
            'navCategories' => $navCategories,
        ]);
    }
}
