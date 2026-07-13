<?php

namespace App\Livewire\Sellers\Products;

use App\Models\Seller;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lists the authenticated seller's own products only — scoped via
 * Seller::products() so another seller's catalog is never reachable from
 * here (see also Edit::mount()'s 404 guard for the direct-URL case).
 */
class Index extends Component
{
    use WithPagination;

    public function seller(): Seller
    {
        return Auth::user()->sellerOrFail();
    }

    public function render()
    {
        $products = $this->seller()->products()
            ->with(['category', 'brand'])
            // Only the vitrin row is needed for the list column — avoid
            // pulling all (up to 3) price rows per product here.
            ->with(['prices' => fn ($query) => $query->where('is_vitrin', true)])
            ->latest()
            ->paginate(20);

        return view('livewire.sellers.products.index', [
            'products' => $products,
        ])->layout('layouts.seller', [
            'title' => __('sellers.products.index.title'),
            'breadcrumb' => [
                ['label' => __('sellers.nav.products')],
            ],
        ]);
    }
}
