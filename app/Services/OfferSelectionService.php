<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The buyer's running Commercial Offer request selection — session-backed
 * (buyers never register, see CLAUDE.md, so there's no account to persist
 * it against). Shape: `session('offer_selection')[seller_id][] = [
 * 'product_id', 'unit', 'qty']`, grouped by seller because CLAUDE.md's
 * buyer flow explicitly spans multiple sellers in one request. Storefront\
 * Products\Show::addToRequest() is the only writer today; this class is
 * the single place that shape is read, written, and reconciled against
 * live product/price/seller data, so the two don't drift apart.
 */
class OfferSelectionService
{
    private const SESSION_KEY = 'offer_selection';

    public const MIN_QTY = 1;

    public const MAX_QTY = 100000;

    /** Max lines (product+unit) in one selection, across all sellers. */
    public const MAX_LINES = 50;

    /** Per-IP cap on selection mutations (quickAdd / addToRequest / requestOffer) per minute. */
    public const THROTTLE_PER_MINUTE = 60;

    public static function clampQty(int|float|string|null $qty): int
    {
        return min(self::MAX_QTY, max(self::MIN_QTY, (int) $qty));
    }

    public static function isFull(): bool
    {
        return self::count() >= self::MAX_LINES;
    }

    /**
     * Hits the per-IP selection limiter; returns true when the visitor is
     * over 60 mutations/min and the action should be dropped.
     */
    public static function throttled(): bool
    {
        $key = 'offer-selection:ip:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::THROTTLE_PER_MINUTE)) {
            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

    /**
     * Adds a line, or increments its qty if this product+unit is already
     * in the selection for this product's seller. Qty is clamped to
     * 1..MAX_QTY; a new line is rejected (false) once MAX_LINES is reached.
     *
     * @return bool false if the line was rejected by the line cap
     */
    public static function add(Product $product, string $unit, int $qty): bool
    {
        $qty = self::clampQty($qty);
        $selection = session(self::SESSION_KEY, []);
        $sellerId = $product->seller_id;

        $selection[$sellerId] ??= [];

        $existingIndex = null;

        foreach ($selection[$sellerId] as $index => $line) {
            if (($line['product_id'] ?? null) === $product->id && ($line['unit'] ?? null) === $unit) {
                $existingIndex = $index;

                break;
            }
        }

        if ($existingIndex !== null) {
            $selection[$sellerId][$existingIndex]['qty'] = self::clampQty($selection[$sellerId][$existingIndex]['qty'] + $qty);
        } else {
            if (self::isFull()) {
                return false;
            }

            $selection[$sellerId][] = [
                'product_id' => $product->id,
                'unit' => $unit,
                'qty' => $qty,
            ];
        }

        session([self::SESSION_KEY => $selection]);

        return true;
    }

    /**
     * Idempotently puts a product's line into the selection: a no-op when
     * the product+unit line already exists (no duplicate, no qty change).
     * The product id and unit come from the client, so they are NEVER
     * trusted: the product is re-queried through the shared storefront
     * visibility scope and the unit must be one of its own price rows
     * (null unit = the is_vitrin row). The qty is raised to at least the
     * MOQ-satisfying minimum for that unit.
     *
     * @return bool true if the line is in the selection afterwards; false if
     *              the product is not visible, the unit is not valid, or the
     *              line cap rejected it (check isFull() to tell them apart)
     */
    public static function ensure(string $productId, ?string $unit = null, ?int $qty = null): bool
    {
        $product = Product::query()
            ->visibleOnStorefront()
            ->with('prices')
            ->find($productId);

        if (! $product) {
            return false;
        }

        $price = $unit === null
            ? ($product->prices->firstWhere('is_vitrin', true) ?? $product->prices->firstWhere('unit', 'pcs'))
            : $product->prices->firstWhere('unit', $unit);

        if (! $price) {
            return false;
        }

        if (self::currentQty($product->seller_id, $product->id, $price->unit) === null) {
            return self::add($product, $price->unit, max($product->minQtyForPrice($price), self::clampQty($qty)));
        }

        return true;
    }

    /**
     * The qty currently stored for this product+unit line, or null if it
     * isn't in the selection (e.g. removed in another tab).
     */
    public static function currentQty(int $sellerId, string $productId, string $unit): ?int
    {
        $selection = session(self::SESSION_KEY, []);

        foreach ($selection[$sellerId] ?? [] as $line) {
            if (($line['product_id'] ?? null) === $productId && ($line['unit'] ?? null) === $unit) {
                return (int) ($line['qty'] ?? 1);
            }
        }

        return null;
    }

    public static function updateQty(int $sellerId, string $productId, string $unit, int $qty): void
    {
        $selection = session(self::SESSION_KEY, []);
        $qty = self::clampQty($qty);

        foreach ($selection[$sellerId] ?? [] as $index => $line) {
            if (($line['product_id'] ?? null) === $productId && ($line['unit'] ?? null) === $unit) {
                $selection[$sellerId][$index]['qty'] = $qty;

                break;
            }
        }

        session([self::SESSION_KEY => $selection]);
    }

    public static function removeLine(int $sellerId, string $productId, string $unit): void
    {
        $selection = session(self::SESSION_KEY, []);

        $selection[$sellerId] = array_values(array_filter(
            $selection[$sellerId] ?? [],
            fn (array $line) => ! (($line['product_id'] ?? null) === $productId && ($line['unit'] ?? null) === $unit)
        ));

        if ($selection[$sellerId] === []) {
            unset($selection[$sellerId]);
        }

        session([self::SESSION_KEY => $selection]);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Total line-item count across all sellers — matches the header
     * badge's pre-existing `flatten(1)->count()` read exactly.
     */
    public static function count(): int
    {
        return collect(session(self::SESSION_KEY, []))->flatten(1)->count();
    }

    /**
     * Resolves the raw session selection into seller-grouped data with
     * live Product/ProductPrice/Seller records, silently dropping any line
     * whose product is no longer approved, whose seller is no longer
     * approved, or whose unit is no longer enabled on that product — the
     * session only ever stores product_id/unit/qty, so anything that
     * changed after the buyer added it (product rejected, price row
     * removed, seller suspended) has to be reconciled against current
     * data, not trusted from session. Also re-persists the cleaned
     * selection back to session so stale/dropped lines don't linger for
     * count() or the next call here.
     *
     * @return array<int, array{seller: Seller, lines: array<int, array{product: Product, displayName: string, unit: string, qty: int, unitPrice: float, lineTotal: float}>, subtotal: float}>
     */
    public static function groupedForDisplay(): array
    {
        $selection = session(self::SESSION_KEY, []);
        $cleaned = [];
        $groups = [];
        $locale = app()->getLocale();

        foreach ($selection as $sellerId => $lines) {
            $seller = Seller::find($sellerId);

            if (! $seller || $seller->status !== 'approved') {
                continue;
            }

            $resolvedLines = [];
            $cleanedLines = [];

            foreach ($lines as $line) {
                $product = Product::query()
                    ->visibleOnStorefront()
                    ->with(['prices', 'images', 'brand', 'parameterValues.categoryParameter', 'parameterValues.options.categoryParameterOption'])
                    ->find($line['product_id'] ?? null);

                if (! $product) {
                    continue;
                }

                $price = $product->prices->firstWhere('unit', $line['unit'] ?? null);

                if (! $price) {
                    continue;
                }

                $qty = self::clampQty($line['qty'] ?? 1);
                $unitPrice = (float) $price->price;

                // localizedName() can legitimately return '' (no brand, no
                // filled specs, no name_extra) — falls back to the plain
                // `name` field, same as Storefront\Products\Show::render().
                $displayName = $product->localizedName($locale);
                $displayName = $displayName !== '' ? $displayName : ($product->name ?? '');

                $resolvedLines[] = [
                    'product' => $product,
                    'displayName' => $displayName,
                    'unit' => $price->unit,
                    'qty' => $qty,
                    'unitPrice' => $unitPrice,
                    'lineTotal' => $unitPrice * $qty,
                ];

                $cleanedLines[] = [
                    'product_id' => $product->id,
                    'unit' => $price->unit,
                    'qty' => $qty,
                ];
            }

            if ($resolvedLines === []) {
                continue;
            }

            $cleaned[$sellerId] = $cleanedLines;

            $groups[] = [
                'seller' => $seller,
                'lines' => $resolvedLines,
                'subtotal' => array_sum(array_column($resolvedLines, 'lineTotal')),
            ];
        }

        session([self::SESSION_KEY => $cleaned]);

        return $groups;
    }
}
