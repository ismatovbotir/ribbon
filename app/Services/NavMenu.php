<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\Page;
use App\Models\Product;
use App\Support\CatalogFilterUrl;
use App\Support\Icon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cached view data for the storefront navigation (spec 19, section 9):
 * the catalog mega menu and the header/footer CMS page links. Cached
 * 10 minutes per locale (pages cached until busted); flush() is called
 * from the FlushesNavMenu model trait on save/delete of Category,
 * CategoryParameter, CategoryParameterOption, Brand, Article, Product
 * and Page. Counts may lag by the TTL — the product page itself still
 * enforces the real visibility scope.
 */
class NavMenu
{
    public const TTL_SECONDS = 600;

    private const MAX_GROUPS = 2;

    private const MAX_LINKS = 6;

    private const MAX_BRANDS = 6;

    /** Fallback when the admin hasn't picked a category icon (spec 22). */
    public const DEFAULT_ICON = 'product';

    /** Parameter types that can produce shortcut groups. */
    private const SHORTCUT_TYPES = ['select_single', 'select_multiple', 'number'];

    public static function megaKey(string $locale): string
    {
        return "storefront:nav:mega:{$locale}";
    }

    public static function pagesKey(string $locale): string
    {
        return "storefront:nav:pages:{$locale}";
    }

    public static function flush(): void
    {
        foreach (config('ribbon.locales') as $locale) {
            Cache::forget(self::megaKey($locale));
            Cache::forget(self::pagesKey($locale));
        }
    }

    /**
     * @return array{
     *     categories: list<array{
     *         id: int, name: string, url: string, count: int, icon: string,
     *         verified_sellers: int, guide: array{title: string, url: string}|null,
     *         groups: list<array{label: string, mono: bool, links: list<array{label: string, url: string}>}>,
     *         brands: list<array{label: string, url: string}>
     *     }>,
     *     article: array{title: string, url: string, image: ?string}|null
     * }
     */
    public function megaMenu(string $locale): array
    {
        return Cache::remember(
            self::megaKey($locale),
            self::TTL_SECONDS,
            fn () => $this->buildMegaMenu($locale),
        );
    }

    /**
     * Published header/footer pages, grouped for the layout:
     * ['header' => [<=3], 'footer' => ['company' => [...], 'legal' => [...]]],
     * each item ['key' => ?string, 'title' => string, 'url' => string].
     *
     * @return array{header: list<array{key: ?string, title: string, url: string}>, footer: array{company: list<array{key: ?string, title: string, url: string}>, legal: list<array{key: ?string, title: string, url: string}>}}
     */
    public function menuPages(string $locale): array
    {
        return Cache::rememberForever(self::pagesKey($locale), function () use ($locale) {
            $pages = Page::query()
                ->isPublished()
                ->where(fn ($q) => $q->where('show_in_header', true)->orWhere('show_in_footer', true))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $item = fn (Page $page) => [
                'key' => $page->key,
                'title' => $page->localized('title', $locale),
                'url' => $page->path($locale) ?? '#',
            ];

            // The contact page only supplies the /contact intro copy; it
            // never appears as a menu link even if flagged.
            $linkable = $pages->reject(fn (Page $page) => $page->key === 'contact' || $page->path($locale) === null);

            return [
                'header' => $linkable->where('show_in_header', true)->take(Page::MAX_HEADER_PAGES)->map($item)->values()->all(),
                'footer' => [
                    'company' => $linkable->where('show_in_footer', true)->where('footer_column', 'company')->map($item)->values()->all(),
                    'legal' => $linkable->where('show_in_footer', true)->where('footer_column', 'legal')->map($item)->values()->all(),
                ],
            ];
        });
    }

    /**
     * Adds the per-request `active` flag (never cached) to a megaMenu()
     * result. $categoryId = the category of the page being viewed.
     *
     * @param  array<string, mixed>  $mega
     * @return array<string, mixed>
     */
    public static function markActive(array $mega, ?int $categoryId): array
    {
        $mega['categories'] = array_map(
            fn (array $category) => $category + ['active' => $categoryId !== null && $category['id'] === $categoryId],
            $mega['categories'] ?? [],
        );

        return $mega;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMegaMenu(string $locale): array
    {
        // Same query as the chip row / footer so counts cannot drift.
        $categories = Category::navList();

        $parametersByCategory = CategoryParameter::query()
            ->whereIn('category_id', $categories->pluck('id'))
            ->where('is_filterable', true)
            ->whereIn('type', self::SHORTCUT_TYPES)
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('category_id');

        // Approved sellers with at least one storefront-visible product per
        // category (shared visibility scope), one query for all categories.
        $sellerCounts = Product::query()
            ->whereIn('category_id', $categories->pluck('id'))
            ->visibleOnStorefront()
            ->groupBy('category_id')
            ->selectRaw('category_id, count(distinct seller_id) as total')
            ->pluck('total', 'category_id');

        $guides = Article::query()
            ->whereIn('id', $categories->pluck('guide_article_id')->filter())
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($categories as $category) {
            $guide = $category->guide_article_id ? $guides->get($category->guide_article_id) : null;

            $rows[] = [
                'id' => $category->id,
                'name' => $this->localize($category->name, $locale),
                'url' => CatalogFilterUrl::category($category, $locale),
                'count' => (int) $category->approved_products_count,
                'icon' => in_array($category->icon, Icon::CATEGORY_ICONS, true) ? $category->icon : self::DEFAULT_ICON,
                'verified_sellers' => (int) ($sellerCounts[$category->id] ?? 0),
                'guide' => $guide ? $this->articleLink($guide, $locale) : null,
                'groups' => $this->groups($category, $parametersByCategory->get($category->id, collect()), $locale),
                'brands' => $this->brands($category, $locale),
            ];
        }

        return [
            'categories' => $rows,
            'article' => $this->featuredArticle($locale),
        ];
    }

    /**
     * @param  iterable<CategoryParameter>  $parameters
     * @return list<array{label: string, mono: bool, links: list<array{label: string, url: string}>}>
     */
    private function groups(Category $category, iterable $parameters, string $locale): array
    {
        $groups = [];

        foreach ($parameters as $parameter) {
            if (count($groups) >= self::MAX_GROUPS) {
                break;
            }

            $links = $parameter->type === 'number'
                ? $this->numberLinks($category, $parameter, $locale)
                : $this->optionLinks($category, $parameter, $locale);

            if ($links === []) {
                continue;
            }

            $labels = array_column($links, 'label');

            $groups[] = [
                'label' => $this->localize($parameter->name, $locale),
                'mono' => $parameter->type === 'number'
                    || count(array_filter($labels, fn ($l) => preg_match('/^\d/', $l))) === count($labels),
                'links' => $links,
            ];
        }

        return $groups;
    }

    /**
     * Visible products of the category (shared visibleOnStorefront scope),
     * as a subquery usable in whereIn.
     */
    private function visibleProductIds(Category $category)
    {
        return Product::query()
            ->where('category_id', $category->id)
            ->visibleOnStorefront()
            ->select('products.id');
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function optionLinks(Category $category, CategoryParameter $parameter, string $locale): array
    {
        $counts = DB::table('product_parameter_value_options as ppvo')
            ->join('product_parameter_values as ppv', 'ppv.id', '=', 'ppvo.product_parameter_value_id')
            ->where('ppv.category_parameter_id', $parameter->id)
            ->whereIn('ppv.product_id', $this->visibleProductIds($category))
            ->groupBy('ppvo.category_parameter_option_id')
            ->selectRaw('ppvo.category_parameter_option_id as option_id, count(distinct ppv.product_id) as total')
            ->pluck('total', 'option_id');

        return $parameter->options
            ->filter(fn ($option) => ($counts[$option->id] ?? 0) > 0)
            // options are already sort_order-ordered; sortByDesc is stable
            // in Collection, so ties keep that order.
            ->sortByDesc(fn ($option) => (int) $counts[$option->id])
            ->take(self::MAX_LINKS)
            ->map(fn ($option) => [
                'label' => $this->localize($option->value, $locale),
                'url' => CatalogFilterUrl::option($category, $parameter, $option, $locale),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function numberLinks(Category $category, CategoryParameter $parameter, string $locale): array
    {
        $rows = DB::table('product_parameter_values as ppv')
            ->where('ppv.category_parameter_id', $parameter->id)
            ->whereNotNull('ppv.value_number')
            ->whereIn('ppv.product_id', $this->visibleProductIds($category))
            ->groupBy('ppv.value_number')
            ->selectRaw('ppv.value_number as value, count(distinct ppv.product_id) as total')
            ->orderByDesc('total')
            ->orderBy('value')
            ->limit(self::MAX_LINKS)
            ->get();

        return $rows->map(function ($row) use ($category, $parameter, $locale) {
            $number = rtrim(rtrim(number_format((float) $row->value, 3, '.', ''), '0'), '.');

            return [
                'label' => trim($number.' '.($parameter->unit ?? '')),
                'url' => CatalogFilterUrl::number($category, $parameter, $number, $number, $locale),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function brands(Category $category, string $locale): array
    {
        $counts = Product::query()
            ->where('category_id', $category->id)
            ->where('brand_id', '!=', 1)
            ->visibleOnStorefront()
            ->groupBy('brand_id')
            ->selectRaw('brand_id, count(*) as total')
            ->orderByDesc('total')
            ->orderBy('brand_id')
            ->limit(self::MAX_BRANDS)
            ->pluck('total', 'brand_id');

        if ($counts->isEmpty()) {
            return [];
        }

        $brands = Brand::query()->whereIn('id', $counts->keys())->get()->keyBy('id');

        return $counts->keys()
            ->filter(fn ($id) => $brands->has($id))
            ->map(fn ($id) => [
                'label' => $brands[$id]->name,
                'url' => CatalogFilterUrl::brand($category, (int) $id, $locale),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{title: string, url: string, image: ?string}|null
     */
    private function featuredArticle(string $locale): ?array
    {
        $article = Article::query()
            ->where('type', 'article')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->first();

        $link = $article ? $this->articleLink($article, $locale) : null;

        if (! $link) {
            return null;
        }

        return $link + [
            'image' => $article->cover_image_path ? Storage::disk('public')->url($article->cover_image_path) : null,
        ];
    }

    /**
     * @return array{title: string, url: string}|null
     */
    private function articleLink(Article $article, string $locale): ?array
    {
        $default = config('ribbon.locales')[0];
        $slug = $article->slug[$locale] ?? ($article->slug[$default] ?? null);

        if (! $slug) {
            return null;
        }

        $route = $article->type === 'news' ? 'storefront.news.show' : 'storefront.articles.show';

        return [
            'title' => $this->localize($article->title, $locale),
            'url' => route($route, ['articleSlug' => $slug]),
        ];
    }

    /**
     * @param  array<string, string>|null  $values
     */
    private function localize(?array $values, string $locale): string
    {
        return (string) (($values[$locale] ?? null) ?: ($values[config('ribbon.locales')[0]] ?? ''));
    }
}
