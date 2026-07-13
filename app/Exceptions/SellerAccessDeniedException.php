<?php

namespace App\Exceptions;

use App\Models\User;
use RuntimeException;

/**
 * Thrown by {@see User::sellerOrFail()} when the authenticated
 * user isn't allowed into the seller dashboard — either because they aren't
 * linked to any Seller at all, or because their Seller exists but isn't
 * `approved`. Carries the seller's `status` (null when there's no linked
 * seller at all) so callers — the login form and the seller-auth middleware
 * — can render an appropriate message without re-querying the seller
 * relationship themselves.
 */
class SellerAccessDeniedException extends RuntimeException
{
    /**
     * @param  string|null  $status  The linked Seller's status (pending/
     *                               rejected/suspended), or null if the user has no linked seller.
     */
    public function __construct(public readonly ?string $status = null)
    {
        parent::__construct(
            $status === null
                ? 'This user does not belong to any seller.'
                : "This user's seller account is \"{$status}\", not approved."
        );
    }
}
