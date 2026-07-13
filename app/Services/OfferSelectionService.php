<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Seller;

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

    /**
     * Adds a line, or increments its qty if this product+unit is already
     * in the selection for this product's seller.
     */
    public static function add(Product $product, string $unit, int $qty): void
    {
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
            $selection[$sellerId][$existingIndex]['qty'] += $qty;
        } else {
            $selection[$sellerId][] = [
                'product_id' => $product->id,
                'unit' => $unit,
                'qty' => $qty,
            ];
        }

        session([self::SESSION_KEY => $selection]);
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
        $qty = max(1, $qty);

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
                    ->where('status', 'approved')
                    ->with(['prices', 'images', 'brand', 'parameterValues.categoryParameter', 'parameterValues.options.categoryParameterOption'])
                    ->find($line['product_id'] ?? null);

                if (! $product) {
                    continue;
                }

                $price = $product->prices->firstWhere('unit', $line['unit'] ?? null);

                if (! $price) {
                    continue;
                }

                $qty = max(1, (int) ($line['qty'] ?? 1));
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
