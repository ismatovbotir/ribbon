<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Idempotency ledger for subscriptions:remind (seller + kind + paid_until period). */
#[Fillable(['seller_id', 'kind', 'paid_until', 'sent_at'])]
class SellerSubscriptionReminder extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'paid_until' => 'date:Y-m-d',
            'sent_at' => 'datetime',
        ];
    }
}
