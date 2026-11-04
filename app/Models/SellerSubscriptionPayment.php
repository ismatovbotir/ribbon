<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only history of manual subscription payments / free grants (amount 0). */
#[Fillable(['seller_id', 'amount', 'months', 'period_start', 'period_end', 'recorded_by', 'note'])]
class SellerSubscriptionPayment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'months' => 'integer',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
