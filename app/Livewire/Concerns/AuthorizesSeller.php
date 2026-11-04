<?php

namespace App\Livewire\Concerns;

use App\Exceptions\SellerAccessDeniedException;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * In-component authorization for seller-dashboard Livewire components.
 * Route middleware does not run on `POST /livewire/update`, and public
 * properties / action arguments are client-controlled, so each action
 * re-resolves the seller from the authenticated user and re-scopes records
 * through it.
 *
 * API:
 *  - authorizeSeller(): Seller   (gate `seller`; returns the current seller)
 *  - authorizeOwner(): Seller    (gate `seller-owner`; Owner-only actions)
 *  - authorizeOwnProduct(Product): Seller  (seller + product ownership)
 *
 * `bootAuthorizesSeller` runs on every request as a safety net.
 */
trait AuthorizesSeller
{
    public function bootAuthorizesSeller(): void
    {
        $this->authorizeSeller();
    }

    protected function authorizeSeller(): Seller
    {
        Gate::authorize('seller');

        try {
            return Auth::user()->sellerOrFail();
        } catch (SellerAccessDeniedException) {
            abort(403);
        }
    }

    protected function authorizeOwner(): Seller
    {
        $seller = $this->authorizeSeller();

        Gate::authorize('seller-owner', $seller);

        return $seller;
    }

    protected function authorizeOwnProduct(Product $product): Seller
    {
        $seller = $this->authorizeSeller();

        abort_unless($product->seller_id === $seller->id, 403);

        return $seller;
    }
}
