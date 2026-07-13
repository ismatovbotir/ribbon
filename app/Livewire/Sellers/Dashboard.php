<?php

namespace App\Livewire\Sellers;

use App\Models\Seller;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The seller dashboard landing page. Deliberately minimal for now — there
 * are no orders or Commercial Offer requests wired to sellers yet, so this
 * is a welcome/status screen plus a quick pointer to the real Products
 * list (app/Livewire/Sellers/Products/Index.php), not a real KPI
 * dashboard.
 */
class Dashboard extends Component
{
    /**
     * Reached only via the `seller.auth` middleware, which already
     * guarantees the authenticated user is linked to an `approved` Seller
     * (see EnsureSellerIsAuthenticated / User::sellerOrFail()) — calling
     * sellerOrFail() directly here is safe and won't throw.
     */
    public function seller(): Seller
    {
        return Auth::user()->sellerOrFail();
    }

    /**
     * Drives whether the dashboard shows the "no products yet" empty state
     * or a quick summary linking to the real Products list
     * (app/Livewire/Sellers/Products/Index.php) — that screen is now the
     * single source of truth for the seller's catalog, this is just a
     * landing-page pointer to it.
     */
    public function productsCount(): int
    {
        return $this->seller()->products()->count();
    }

    public function render()
    {
        return view('livewire.sellers.dashboard', [
            'seller' => $this->seller(),
            'productsCount' => $this->productsCount(),
        ])->layout('layouts.seller', [
            'title' => __('sellers.dashboard.title'),
        ]);
    }
}
