<?php

namespace App\Livewire\Storefront\Concerns;

use App\Services\OfferSelectionService;

/**
 * quickAdd() for catalog / search / home product rows. All rules live in
 * OfferSelectionService::ensure() (visibility re-check, vitrin unit, MOQ
 * quantity, idempotency) so web and the future Telegram Web App share them.
 */
trait QuickAddsToSelection
{
    public function quickAdd(string $productId): void
    {
        if (OfferSelectionService::throttled()) {
            $this->addError('selection', __('storefront.selection.throttled'));

            return;
        }

        if (! OfferSelectionService::ensure($productId) && OfferSelectionService::isFull()) {
            $this->addError('selection', __('storefront.selection.line_limit', ['max' => OfferSelectionService::MAX_LINES]));
        }

        $this->dispatch('selection-changed', count: OfferSelectionService::count());
    }
}
