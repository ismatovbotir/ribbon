<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductViewEvent;
use App\Models\SearchQueryEvent;

/**
 * Platform-wide counterpart to ProductAnalyticsService — that class always
 * scopes to one Seller (the seller dashboard); everything here aggregates
 * across all sellers at once, for the Super-Admin-only analytics page.
 * Reads the same product_view_events table (no new tracking needed for
 * "what products were viewed" / "where traffic came from") plus the new
 * search_queries table (see Storefront\Search) for "what buyers searched".
 */
class PlatformAnalyticsService
{
    private const SOURCES = ['direct', 'google', 'yandex', 'internal_search', 'other'];

    /**
     * Day-by-day product-view and search-query counts for the last $days
     * days (today inclusive), zero-filled so the trend chart gets a
     * continuous series rather than gaps — mirrors
     * ProductAnalyticsService::dailyCounts() exactly, just unscoped.
     *
     * @return array{labels: array<int, string>, views: array<int, int>, searches: array<int, int>}
     */
    public static function dailyCounts(int $days = 30): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $viewRows = ProductViewEvent::query()
            ->where('type', 'view')
            ->where('occurred_at', '>=', $start)
            ->selectRaw('DATE(occurred_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $searchRows = SearchQueryEvent::query()
            ->where('occurred_at', '>=', $start)
            ->selectRaw('DATE(occurred_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $views = [];
        $searches = [];

        for ($i = 0; $i < $days; $i++) {
            $key = $start->copy()->addDays($i)->format('Y-m-d');
            $labels[] = $key;
            $views[] = (int) ($viewRows[$key] ?? 0);
            $searches[] = (int) ($searchRows[$key] ?? 0);
        }

        return ['labels' => $labels, 'views' => $views, 'searches' => $searches];
    }

    /**
     * Platform-wide view counts by traffic source over the last $days days
     * — same four buckets/zero-fill as ProductAnalyticsService::
     * sourceBreakdown(), just not scoped to one seller.
     *
     * @return array<string, int>
     */
    public static function sourceBreakdown(int $days = 30): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $counts = ProductViewEvent::query()
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
     * Top viewed products platform-wide over the last $days days, most
     * viewed first — includes the seller name (unlike
     * ProductAnalyticsService::perProductTotals(), which is already
     * scoped to a single seller so that'd be redundant there).
     *
     * @return array<int, array{product: Product, displayName: string, sellerName: string, views: int}>
     */
    public static function topViewedProducts(int $days = 30, int $limit = 10): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = ProductViewEvent::query()
            ->where('type', 'view')
            ->where('occurred_at', '>=', $start)
            ->selectRaw('product_id, COUNT(*) as total')
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'product_id');

        if ($rows->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', $rows->keys())
            ->with('seller')
            ->get()
            ->keyBy('id');

        $locale = app()->getLocale();

        return $rows->map(function (int $views, $productId) use ($products, $locale) {
            $product = $products->get($productId);

            if (! $product) {
                return null;
            }

            $displayName = $product->localizedName($locale);
            $displayName = $displayName !== '' ? $displayName : ($product->name ?? '');

            return [
                'product' => $product,
                'displayName' => $displayName,
                'sellerName' => $product->seller->name ?? '',
                'views' => $views,
            ];
        })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Most frequent search terms over the last $days days, most frequent
     * first — grouped case-insensitively (so "ribbon" and "Ribbon" count
     * as the same query) since buyers don't consistently match each
     * other's capitalization for what's conceptually the same search.
     *
     * @return array<int, array{query: string, count: int}>
     */
    public static function topSearchQueries(int $days = 30, int $limit = 10): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        return SearchQueryEvent::query()
            ->where('occurred_at', '>=', $start)
            ->selectRaw('LOWER(query) as normalized_query, COUNT(*) as total')
            ->groupBy('normalized_query')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['query' => $row->normalized_query, 'count' => (int) $row->total])
            ->all();
    }
}
