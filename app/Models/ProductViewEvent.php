<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only analytics event — a product page view or a search/catalog
 * result-grid appearance. Written by App\Services\ProductAnalyticsService,
 * which is also the only place these get aggregated for the seller
 * analytics dashboard; this model stays a thin record, no business logic.
 */
#[Fillable(['product_id', 'seller_id', 'type', 'source', 'occurred_at'])]
class ProductViewEvent extends Model
{
    /**
     * No updated_at — rows are never mutated after insert, and
     * `occurred_at` (not created_at) is the timestamp every query groups
     * by, so the default timestamp pair would just be dead columns.
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
