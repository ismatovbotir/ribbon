<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['product_id', 'unit', 'qty_in_pcs', 'price', 'is_vitrin'])]
class ProductPrice extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_in_pcs' => 'integer',
            'price' => 'decimal:2',
            'is_vitrin' => 'boolean',
        ];
    }

    #[Boot]
    protected static function bootProductPrice(): void
    {
        // `pcs` is mandatory and its qty_in_pcs is always 1 (pack/box enter
        // their own pcs-count independently). Also guards against a `pcs`
        // row being renamed away from `pcs` to sneak past the deletion
        // block below — that would silently violate the "every product has
        // a pcs row" invariant just as deleting it would.
        static::saving(function (ProductPrice $price) {
            if ($price->unit === 'pcs') {
                $price->qty_in_pcs = 1;
            }

            if ($price->exists && $price->getOriginal('unit') === 'pcs' && $price->unit !== 'pcs') {
                throw new LogicException('The pcs price row cannot be changed to another unit; pcs is mandatory on every product.');
            }

            // Exactly one row per product may be flagged is_vitrin. Unset
            // any previously-flagged row *before* this one is written, so
            // two rows are never simultaneously true. Any enabled unit
            // (pcs/pack/box) may be the vitrin row — sellers choose freely.
            if ($price->is_vitrin) {
                static::query()
                    ->where('product_id', $price->product_id)
                    ->when($price->exists, fn ($query) => $query->where('id', '!=', $price->id))
                    ->where('is_vitrin', true)
                    ->update(['is_vitrin' => false]);

                // Belt-and-braces for updates: Eloquent's own UPDATE only
                // writes attributes it considers dirty, diffed against this
                // instance's cached "original". If this instance's
                // is_vitrin was already true in memory (e.g. loaded before
                // a sibling row's flip touched the DB directly, as above),
                // save() below could otherwise see no change and silently
                // skip writing it. Write the flag directly so the DB is
                // always correct regardless of instance staleness, then
                // resync so the subsequent save() doesn't redo the work.
                if ($price->exists) {
                    static::query()->where('id', $price->id)->update(['is_vitrin' => true]);
                    $price->syncOriginalAttribute('is_vitrin');
                }
            }
        });

        // The pcs row is the mandatory baseline price for a product; it can
        // never be removed (Product::bootProduct() guarantees one exists on
        // creation, and this guard keeps that promise true for the rest of
        // the product's life).
        static::deleting(function (ProductPrice $price) {
            if ($price->unit === 'pcs') {
                throw new LogicException('The pcs price row cannot be deleted; pcs is mandatory on every product.');
            }

            // Design spec 07-product-pricing-editor.md flagged a real
            // invariant gap: nothing re-assigned is_vitrin when the row
            // holding it (e.g. a pack/box row) got deleted, which could
            // leave a product with zero vitrin rows and nothing for the
            // storefront to render as the default price. Fall back to the
            // pcs row — guaranteed to exist and undeletable per the guard
            // above — via a direct query update rather than
            // loading-then-saving the pcs model, so we don't re-trigger the
            // saving hook's unset-siblings logic while this deletion is
            // still in flight.
            if ($price->is_vitrin) {
                DB::transaction(function () use ($price) {
                    $price->product->prices()->where('unit', 'pcs')->update(['is_vitrin' => true]);
                });
            }
        });
    }

    /**
     * The product this price row belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Flag this row as the storefront default price for its product,
     * atomically unsetting whichever row previously held that flag (the
     * actual unset happens in the `saving` event above; the transaction
     * here just makes the whole operation atomic against failures).
     */
    public function makeVitrin(): bool
    {
        return DB::transaction(function () {
            $this->is_vitrin = true;

            return $this->save();
        });
    }
}
