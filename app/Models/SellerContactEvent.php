<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only lead event (a buyer revealed a seller's phone on a product
 * page). Holds no buyer data - see the migration for the visitor_hash
 * rationale.
 */
#[Fillable(['product_id', 'seller_id', 'type', 'visitor_hash', 'occurred_at'])]
class SellerContactEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Daily-rotating, non-reversible visitor fingerprint for dedupe only.
     */
    public static function visitorHash(?string $ip): string
    {
        return substr(hash_hmac('sha256', ($ip ?? '').'|'.now()->utc()->toDateString(), (string) config('app.key')), 0, 32);
    }
}
