<?php

namespace App\Livewire\Sellers\Requests;

use App\Livewire\Concerns\AuthorizesSeller;
use App\Models\OfferRequest;
use App\Models\OfferRequestOutcome;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A seller's inbox of buyer requests. Every query goes through
 * OfferRequest::forSeller() and loads only this seller's items, so another
 * seller's part of a shared request is never sent to the browser.
 */
class Index extends Component
{
    use AuthorizesSeller;
    use WithPagination;

    public const NEW = 'new';

    /** '' (all), 'new', or one of OfferRequestOutcome::OUTCOMES. */
    #[Url(history: true)]
    public string $filter = '';

    public function setFilter(string $filter): void
    {
        $this->filter = $this->isValidFilter($filter) ? $filter : '';
        $this->resetPage();
    }

    public function setOutcome(int $offerRequestId, string $outcome): void
    {
        $seller = $this->authorizeSeller();

        abort_unless(in_array($outcome, OfferRequestOutcome::OUTCOMES, true), 422);

        OfferRequest::forSeller($seller)->findOrFail($offerRequestId)
            ->markOutcome($seller, $outcome, Auth::user());

        $this->dispatch('new-requests-count', count: OfferRequest::unansweredCountFor($seller));
    }

    private function isValidFilter(string $filter): bool
    {
        return $filter === '' || $filter === self::NEW || in_array($filter, OfferRequestOutcome::OUTCOMES, true);
    }

    public function render()
    {
        $seller = $this->authorizeSeller();
        $filter = $this->isValidFilter($this->filter) ? $this->filter : '';

        $outcomeCounts = OfferRequestOutcome::where('seller_id', $seller->id)
            ->selectRaw('outcome, count(*) as total')
            ->groupBy('outcome')
            ->pluck('total', 'outcome');

        $total = OfferRequest::forSeller($seller)->count();

        $requests = OfferRequest::forSeller($seller)
            ->with([
                'items' => fn ($query) => $query->where('seller_id', $seller->id)->with('product'),
                'outcomes' => fn ($query) => $query->where('seller_id', $seller->id),
            ])
            ->when($filter === self::NEW, fn ($query) => $query->whereDoesntHave('outcomes', fn ($outcomes) => $outcomes->where('seller_id', $seller->id)))
            ->when($filter !== '' && $filter !== self::NEW, fn ($query) => $query->whereHas('outcomes', fn ($outcomes) => $outcomes->where('seller_id', $seller->id)->where('outcome', $filter)))
            ->latest()
            ->latest('id')
            ->paginate(20);

        // An out-of-range ?page= shouldn't look like an empty inbox.
        if ($requests->isEmpty() && $requests->currentPage() > 1) {
            $this->resetPage();

            return $this->render();
        }

        return view('livewire.sellers.requests.index', [
            'requests' => $requests,
            'outcomeCounts' => $outcomeCounts,
            'total' => $total,
            'newCount' => $total - $outcomeCounts->sum(),
        ])->layout('layouts.seller', [
            'title' => __('sellers.nav.requests'),
            'breadcrumb' => [['label' => __('sellers.nav.requests')]],
        ]);
    }
}
