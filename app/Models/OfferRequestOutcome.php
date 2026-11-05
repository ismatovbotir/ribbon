<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A seller's answer to their own part of a request. See OfferRequest::markOutcome(). */
#[Fillable(['offer_request_id', 'seller_id', 'outcome', 'first_marked_at', 'marked_by'])]
class OfferRequestOutcome extends Model
{
    public const OUTCOMES = ['replied', 'quoted', 'won', 'lost'];

    protected function casts(): array
    {
        return ['first_marked_at' => 'datetime'];
    }
}
