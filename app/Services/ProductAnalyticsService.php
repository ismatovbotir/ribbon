<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductViewEvent;
use App\Models\Seller;

/**
 * Records and aggregates the two seller-facing analytics events: a buyer
 * opening a product's detail page (`view`) and a product appearing in a
 * catalog/search/home results grid (`search_appearance`, i.e. an
 * impression). Recording and aggregation live in one class so the event
 * shape (see ProductViewEvent/its migration) only has one writer and one
 * reader to keep in sync.
 *
 * Impressions are recorded once per real page load, not on every Livewire
 * re-render a filter change or pagination click triggers within that same
 * visit — see the call sites in Home/Catalog\Show/Search::render(), which
 * guard on `! request()->hasHeader('X-Livewire')`. This is a deliberate v1
 * scope cut: a buyer paging through results or adjusting filters doesn't
 * generate additional impression events for the newly-shown products.
 */
class ProductAnalyticsService
{
    private const SOURCES = ['direct', 'google', 'yandex', 'internal_search', 'other'];

    public static function recordView(Product $product): void
    {
        ProductViewEvent::create([
            'product_id' => $product->id,
            'seller_id' => $product->seller_id,
            'type' => 'view',
            'source' => self::resolveSource(),
            'occurred_at' => now(),
        ]);
    }

    /**
     * Bulk-inserts one `search_appearance` row per product — a single
     * query for the whole rendered grid rather than one insert per
     * product, since a single catalog/search page can show a couple dozen
     * products at once.
     *
     * @param  iterable<Product>  $products
     */
    public static function recordSearchAppearances(iterable $products): void
    {
        $now = now();
        $rows = [];

        foreach ($products as $product) {
            $rows[] = [
                'product_id' => $product->id,
                'seller_id' => $product->seller_id,
                'type' => 'search_appearance',
                'source' => null,
                'occurred_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        ProductViewEvent::insert($rows);
    }

    /**
     * Buckets the current request's `Referer` header into one of the four
     * source categories the seller dashboard charts against — see
     * `product_view_events`' migration for why `search_appearance` rows
     * never call this (source only means something for an arriving view).
     */
    private static function resolveSource(): string
    {
        $referer = request()->headers->get('referer');

        if (! $referer) {
            return 'direct';
        }

        $host = parse_url($referer, PHP_URL_HOST) ?? '';
        $path = parse_url($referer, PHP_URL_PATH) ?? '';
        $ownHost = request()->getHost();

        if (str_contains($host, 'google.')) {
            return 'google';
        }

        if (str_contains($host, 'yandex.')) {
            return 'yandex';
        }

        if ($host === '' || $host === $ownHost) {
            return (str_starts_with($path, '/catalog') || str_starts_with($path, '/search'))
                ? 'internal_search'
                : 'direct';
        }

        return 'other';
    }

    /**
     * Day-by-day view/appearance counts for the last $days days (today
     * inclusive), zero-filled for days with no events so the trend chart
     * gets a continuous series rather than gaps.
     *
     * @return array{labels: array<int, string>, views: array<int, int>, appearances: array<int, int>}
     */
    public static function dailyCounts(Seller $seller, int $days = 30): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = ProductViewEvent::query()
            ->where('seller_id', $seller->id)
            ->where('occurred_at', '>=', $start)
            ->selectRaw('DATE(occurred_at) as day, type, COUNT(*) as total')
            ->groupBy('day', 'type')
            ->get();

        $views = [];
        $appearances = [];
        $labels = [];

        for ($i = 0; $i < $days; $i++) {
            $key = $start->copy()->addDays($i)->format('Y-m-d');
            $labels[] = $key;
            $views[$key] = 0;
            $appearances[$key] = 0;
        }

        foreach ($rows as $row) {
            if (! array_key_exists($row->day, $views)) {
                continue;
            }

            if ($row->type === 'view') {
                $views[$row->day] = (int) $row->total;
            } else {
                $appearances[$row->day] = (int) $row->total;
            }
        }

        return [
            'labels' => $labels,
            'views' => array_values($views),
            'appearances' => array_values($appearances),
        ];
    }

    /**
     * View counts by traffic source over the last $days days, zero-filled
     * across all four buckets so the chart's category set never shifts
     * based on what happened to occur.
     *
     * @return array<string, int>
     */
    public static function sourceBreakdown(Seller $seller, int $days = 30): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $counts = ProductViewEvent::query()
            ->where('seller_id', $seller->id)
            ->where('type', 'view')
            ->where('occurred_at', '>=', $start)
            ->selectRaw('source, COUNT(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $breakdown = [];

        foreach (self::SOURCES as $source) {
            $breakdown[$source] = (int) ($counts[$source] ?? 0);
        }

        return $breakdown;
    }

    /**
     * Per-product view/appearance totals over the last $days days, sorted
     * by views descending — only products with at least one event in the
     * window are included.
     *
     * @return array<int, array{product: Product, displayName: string, views: int, appearances: int}>
     */
    public static function perProductTotals(Seller $seller, int $days = 30): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = ProductViewEvent::query()
            ->where('seller_id', $seller->id)
            ->where('occurred_at', '>=', $start)
            ->selectRaw('product_id, type, COUNT(*) as total')
            ->groupBy('product_id', 'type')
            ->get()
            ->groupBy('product_id');

        if ($rows->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', $rows->keys())
            ->with(['brand', 'parameterValues.categoryParameter', 'parameterValues.options.categoryParameterOption'])
            ->get()
            ->keyBy('id');

        $locale = app()->getLocale();

        return $rows->map(function ($productRows, $productId) use ($products, $locale) {
            $product = $products->get($productId);

            if (! $product) {
                return null;
            }

            $displayName = $product->localizedName($locale);
            $displayName = $displayName !== '' ? $displayName : ($product->name ?? '');

            return [
                'product' => $product,
                'displayName' => $displayName,
                'views' => (int) ($productRows->firstWhere('type', 'view')->total ?? 0),
                'appearances' => (int) ($productRows->firstWhere('type', 'search_appearance')->total ?? 0),
            ];
        })
            ->filter()
            ->sortByDesc('views')
            ->values()
            ->all();
    }
}
