<?php

namespace App\Livewire\Admin\Offers;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\OfferRequest;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * State machine for `offer_requests.status` (no spec exists for this
 * beyond the 4-value enum, so this is the chosen shape — see
 * OfferRequest::markContacted()/markFulfilled()/cancel() for why there's
 * no actor/reason to capture, unlike Seller/Product moderation):
 *
 *   pending ──► contacted ──► fulfilled
 *      │            │
 *      └──────► cancelled ◄──┘
 *
 * - `pending`: a lead just came in, needs first touch. Can move to
 *   `contacted` (staff called/messaged the buyer), `fulfilled` (staff
 *   already knows the buyer was reached and the inquiry is resolved —
 *   e.g. an order was placed off-platform in the same call — so requiring
 *   a separate "contacted" click first would just be busywork), or
 *   `cancelled` (dead lead, wrong number, etc.).
 * - `contacted`: staff has reached out. Can move to `fulfilled` (normal
 *   path) or `cancelled` (buyer went elsewhere / request fell through
 *   after contact).
 * - `fulfilled` / `cancelled`: terminal — no further transitions. A
 *   fulfilled request shouldn't be reopened to "cancelled", and a
 *   cancelled one shouldn't later be marked "fulfilled"; if staff made a
 *   mistake that's a data-correction concern, not a normal state change.
 */
class Show extends Component
{
    use AuthorizesAdmin;

    protected function adminAccess(): ?string
    {
        return 'super-admin';
    }

    public OfferRequest $offerRequest;

    public function mount(OfferRequest $offerRequest): void
    {
        $this->authorizeSuperAdmin();

        $this->offerRequest = $offerRequest;
    }

    /**
     * This request's line items, eager-loaded once with the seller/product
     * relations the detail view needs (grouping + the grand total both
     * derive from this same collection, so it's fetched a single time).
     */
    #[Computed]
    public function items()
    {
        return $this->offerRequest->items()
            ->with(['seller', 'product'])
            ->get();
    }

    /**
     * Line items grouped by seller — a request can span multiple sellers
     * (see OfferRequestItem::seller()), so staff need the per-seller split
     * visible, not one flat list.
     */
    #[Computed]
    public function itemsBySeller()
    {
        return $this->items->groupBy(fn ($item) => $item->seller?->name ?? 'Unknown seller');
    }

    #[Computed]
    public function grandTotal(): float
    {
        return $this->items->sum(fn ($item) => $item->lineTotal());
    }

    public function markContacted(): void
    {
        $this->authorizeSuperAdmin();

        if ($this->offerRequest->status !== 'pending') {
            return;
        }

        $this->offerRequest->markContacted();

        session()->flash('status', 'Request marked as contacted.');
    }

    public function markFulfilled(): void
    {
        $this->authorizeSuperAdmin();

        if (! in_array($this->offerRequest->status, ['pending', 'contacted'], true)) {
            return;
        }

        $this->offerRequest->markFulfilled();

        session()->flash('status', 'Request marked as fulfilled.');
    }

    public function cancel(): void
    {
        $this->authorizeSuperAdmin();

        if (! in_array($this->offerRequest->status, ['pending', 'contacted'], true)) {
            return;
        }

        $this->offerRequest->cancel();

        session()->flash('status', 'Request cancelled.');
    }

    public function render()
    {
        return view('livewire.admin.offers.show', [
            'items' => $this->items,
            'itemsBySeller' => $this->itemsBySeller,
            'grandTotal' => $this->grandTotal,
        ])->layout('layouts.admin', [
            'title' => 'Commercial Offer #'.$this->offerRequest->id,
            'breadcrumb' => [
                ['label' => 'Commercial Offers', 'url' => route('admin.offers.index')],
                ['label' => '#'.$this->offerRequest->id],
            ],
        ]);
    }
}
