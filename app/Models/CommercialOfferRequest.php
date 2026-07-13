<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['phone', 'company_name', 'email', 'status'])]
class CommercialOfferRequest extends Model
{
    /**
     * The per-seller/per-product line items making up this request.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CommercialOfferRequestItem::class);
    }

    /**
     * Mark this request as contacted (staff has reached out to the buyer).
     * No actor column exists on this table (unlike Seller::approve()/
     * Product::approve()'s moderated_by/approved_by) — there's no admin
     * auth to attribute it to yet, so this only transitions `status`.
     */
    public function markContacted(): void
    {
        $this->status = 'contacted';
        $this->save();
    }

    /**
     * Mark this request as fulfilled (the buyer's inquiry was resolved,
     * e.g. an order was placed off-platform). Status-only, see
     * markContacted() for why there's no actor column to record.
     */
    public function markFulfilled(): void
    {
        $this->status = 'fulfilled';
        $this->save();
    }

    /**
     * Cancel this request. Status-only, see markContacted() for why
     * there's no actor column to record.
     */
    public function cancel(): void
    {
        $this->status = 'cancelled';
        $this->save();
    }
}
