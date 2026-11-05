<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use LogicException;

#[Fillable(['phone', 'company_name', 'email', 'buyer_type', 'utm', 'referrer', 'status'])]
class OfferRequest extends Model
{
    public const BUYER_TYPES = ['retail', 'logistics', 'manufacturing', 'small_business'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['utm' => 'array'];
    }

    /**
     * The per-seller/per-product line items making up this request.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OfferRequestItem::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(OfferRequestOutcome::class);
    }

    /**
     * Requests with at least one item from this seller. Sellers only ever reach
     * a request through this scope.
     */
    public function scopeForSeller(Builder $query, Seller $seller): void
    {
        $query->whereHas('items', fn (Builder $items) => $items->where('seller_id', $seller->id));
    }

    /** Requests this seller hasn't answered yet: the "New" count. */
    public static function unansweredCountFor(Seller $seller): int
    {
        return static::forSeller($seller)
            ->whereDoesntHave('outcomes', fn (Builder $outcomes) => $outcomes->where('seller_id', $seller->id))
            ->count();
    }

    /**
     * Record a seller's answer to their part of this request. It can be changed
     * later (a quote becomes won or lost), but first_marked_at stays put.
     */
    public function markOutcome(Seller $seller, string $outcome, User $by): OfferRequestOutcome
    {
        if (! in_array($outcome, OfferRequestOutcome::OUTCOMES, true)) {
            throw new InvalidArgumentException("Unknown request outcome [{$outcome}].");
        }

        if (! $this->items()->where('seller_id', $seller->id)->exists()) {
            throw new LogicException('A seller can only answer a request that includes their items.');
        }

        $row = $this->outcomes()->firstOrNew(['seller_id' => $seller->id]);
        $row->first_marked_at ??= now();
        $row->outcome = $outcome;
        $row->marked_by = $by->id;
        $row->save();

        return $row;
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
