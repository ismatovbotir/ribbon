<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['commercial_offer_request_id', 'product_id', 'seller_id', 'unit', 'quantity', 'price_at_request'])]
class CommercialOfferRequestItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price_at_request' => 'decimal:2',
        ];
    }

    /**
     * The request header this line item belongs to.
     */
    public function commercialOfferRequest(): BelongsTo
    {
        return $this->belongsTo(CommercialOfferRequest::class);
    }

    /**
     * The product this line item was requested for.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The seller who owns the requested product — denormalized alongside
     * product_id (see the migration), not derived via product->seller, so
     * staff can group/filter the per-seller split without an extra join.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * This line item's total: the requested quantity times the price
     * snapshot taken at request time (not the product's current price,
     * which may have since changed).
     */
    public function lineTotal(): float
    {
        return $this->quantity * (float) $this->price_at_request;
    }
}
