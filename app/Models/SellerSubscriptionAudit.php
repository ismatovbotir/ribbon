<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only audit of every change to sellers.subscription_paid_until. */
#[Fillable(['seller_id', 'user_id', 'action', 'old_paid_until', 'new_paid_until', 'amount', 'months', 'note', 'created_at'])]
class SellerSubscriptionAudit extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'old_paid_until' => 'date:Y-m-d',
            'new_paid_until' => 'date:Y-m-d',
            'created_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
