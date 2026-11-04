<?php

namespace App\Livewire\Sellers\Products;

use App\Livewire\Concerns\AuthorizesSeller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Services\SubscriptionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The seller's own products, grouped by category: every active category (in
 * admin sort order) as a collapsible section. Only the open one loads its
 * products. Paid categories are "locked" for a lapsed seller: no Add button,
 * and Create refuses them via SubscriptionService::canListIn(). Existing
 * products there stay visible and editable (CLAUDE.md).
 *
 * Everything is scoped via Seller::products(), so another seller's catalog
 * is never reachable from here (see also Edit::mount()'s 404 guard).
 */
class Index extends Component
{
    use AuthorizesSeller;
    use WithPagination;

    private const SESSION_KEY = 'seller.products.open_category';

    /**
     * Open category id. null = not chosen yet (fall back to the last one
     * used, then the first category with products). 0 = all collapsed.
     */
    #[Url(as: 'category', history: true)]
    public ?int $open = null;

    public function seller(): Seller
    {
        return Auth::user()->sellerOrFail();
    }

    public function mount(): void
    {
        if ($this->open === null && is_int($last = session(self::SESSION_KEY))) {
            $this->open = $last;
        }
    }

    public function toggle(int $categoryId): void
    {
        $this->open = $this->open === $categoryId ? 0 : $categoryId;

        if ($this->open !== 0) {
            session([self::SESSION_KEY => $this->open]);
        }

        $this->resetPage();
    }

    public function render(SubscriptionService $subscriptions)
    {
        $seller = $this->seller();

        $counts = $this->countsByCategory($seller);

        // Active categories, plus any inactive one the seller still has
        // products in, so those products never become unreachable.
        $categories = Category::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $counts->keys()))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $canList = $categories->mapWithKeys(fn (Category $c) => [$c->id => $subscriptions->canListIn($seller, $c)]);

        $openId = $this->resolveOpen($categories, $counts);

        $products = null;
        $hiddenIds = collect();

        if ($openId) {
            $products = $seller->products()
                ->where('category_id', $openId)
                ->with('brand')
                // Only the vitrin row is needed for the list column.
                ->with(['prices' => fn ($query) => $query->where('is_vitrin', true)])
                ->latest()
                ->paginate(20);

            $hiddenIds = Product::query()
                ->whereIn('id', $products->getCollection()->modelKeys())
                ->hiddenBySubscription()
                ->pluck('id')
                ->flip();
        }

        return view('livewire.sellers.products.index', [
            'categories' => $categories,
            'counts' => $counts,
            'canList' => $canList,
            'openId' => $openId,
            'products' => $products,
            'hiddenIds' => $hiddenIds,
            'locale' => app()->getLocale(),
            'fallbackLocale' => config('ribbon.locales')[0],
        ])->layout('layouts.seller', [
            'title' => __('sellers.products.index.title'),
            'breadcrumb' => [
                ['label' => __('sellers.nav.products')],
            ],
        ]);
    }

    /**
     * Per-category totals for the section headers: all products, pending,
     * rejected, and approved-but-hidden-by-subscription. Two grouped queries.
     *
     * @return Collection<int, array{total: int, pending: int, rejected: int, hidden: int}>
     */
    private function countsByCategory(Seller $seller): Collection
    {
        $rows = $seller->products()
            ->toBase()
            ->selectRaw('category_id')
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'pending' then 1 else 0 end) as pending")
            ->selectRaw("sum(case when status = 'rejected' then 1 else 0 end) as rejected")
            ->groupBy('category_id')
            ->get();

        $hidden = Product::query()
            ->where('seller_id', $seller->id)
            ->hiddenBySubscription()
            ->toBase()
            ->selectRaw('category_id, count(*) as hidden')
            ->groupBy('category_id')
            ->pluck('hidden', 'category_id');

        return $rows->mapWithKeys(fn ($row) => [(int) $row->category_id => [
            'total' => (int) $row->total,
            'pending' => (int) $row->pending,
            'rejected' => (int) $row->rejected,
            'hidden' => (int) ($hidden[$row->category_id] ?? 0),
        ]]);
    }

    /**
     * The section to show open: an explicit choice (URL or last used) if it
     * is still listed, otherwise the first category with products, otherwise
     * the first category. 0 keeps everything collapsed.
     */
    private function resolveOpen(Collection $categories, Collection $counts): ?int
    {
        if ($this->open === 0) {
            return null;
        }

        if ($this->open !== null && $categories->contains('id', $this->open)) {
            return $this->open;
        }

        $first = $categories->first(fn (Category $c) => $counts->has($c->id)) ?? $categories->first();

        return $first?->id;
    }
}
