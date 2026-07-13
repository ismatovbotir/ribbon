<?php

namespace App\Livewire\Storefront\OfferRequest;

use App\Jobs\NotifyTelegramOfNewCommercialOfferRequest;
use App\Models\CommercialOfferRequest;
use App\Models\CommercialOfferRequestItem;
use App\Services\OfferSelectionService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Review-and-submit page for the buyer's running Commercial Offer request
 * selection (see OfferSelectionService) — the "Selection" header badge's
 * destination. Buyer-facing, unauthenticated (buyers never register, see
 * CLAUDE.md): only a phone number is required to submit; company/email are
 * optional, matching the CommercialOfferRequest schema exactly.
 *
 * Confirmation is a same-page state flip ($submitted), not a redirect —
 * mirrors Sellers\Register's step-3 static confirmation pattern.
 */
class Show extends Component
{
    public string $phone = '';

    public string $companyName = '';

    public string $email = '';

    public bool $submitted = false;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32'],
            'companyName' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'phone' => __('storefront.offer_request.phone_label'),
            'companyName' => __('storefront.offer_request.company_label'),
            'email' => __('storefront.offer_request.email_label'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'phone.required' => __('storefront.offer_request.validation.phone_required'),
            'email.email' => __('storefront.offer_request.validation.email_invalid'),
        ];
    }

    public function incrementLine(int $sellerId, string $productId, string $unit): void
    {
        $qty = OfferSelectionService::currentQty($sellerId, $productId, $unit) ?? 1;

        OfferSelectionService::updateQty($sellerId, $productId, $unit, $qty + 1);
    }

    public function decrementLine(int $sellerId, string $productId, string $unit): void
    {
        $qty = OfferSelectionService::currentQty($sellerId, $productId, $unit) ?? 1;

        OfferSelectionService::updateQty($sellerId, $productId, $unit, $qty - 1);
    }

    public function removeLine(int $sellerId, string $productId, string $unit): void
    {
        OfferSelectionService::removeLine($sellerId, $productId, $unit);
    }

    /**
     * Creates one CommercialOfferRequest header plus one
     * CommercialOfferRequestItem per line item across every seller in the
     * selection (a single request spans multiple sellers, per CLAUDE.md).
     * price_at_request is the live price resolved by groupedForDisplay()
     * at submit time, not whatever was true when the buyer first added the
     * line — matches the item's own docblock ("a snapshot ... not the
     * product's current price").
     */
    public function submit(): void
    {
        $this->validate();

        $groups = OfferSelectionService::groupedForDisplay();

        if ($groups === []) {
            $this->addError('phone', __('storefront.offer_request.empty_error'));

            return;
        }

        $commercialOfferRequest = DB::transaction(function () use ($groups) {
            $request = CommercialOfferRequest::create([
                'phone' => $this->phone,
                'company_name' => $this->companyName !== '' ? $this->companyName : null,
                'email' => $this->email !== '' ? $this->email : null,
                'status' => 'pending',
            ]);

            foreach ($groups as $group) {
                foreach ($group['lines'] as $line) {
                    CommercialOfferRequestItem::create([
                        'commercial_offer_request_id' => $request->id,
                        'product_id' => $line['product']->id,
                        'seller_id' => $group['seller']->id,
                        'unit' => $line['unit'],
                        'quantity' => $line['qty'],
                        'price_at_request' => $line['unitPrice'],
                    ]);
                }
            }

            return $request;
        });

        OfferSelectionService::clear();

        // Dispatched after the transaction commits (not from inside the
        // closure) so the job — and whoever reads its Telegram message and
        // clicks straight through to /admin/commercial-offers — never race
        // a not-yet-committed row.
        NotifyTelegramOfNewCommercialOfferRequest::dispatch($commercialOfferRequest);

        $this->submitted = true;
    }

    public function render()
    {
        $groups = $this->submitted ? [] : OfferSelectionService::groupedForDisplay();
        $grandTotal = array_sum(array_column($groups, 'subtotal'));

        $locale = app()->getLocale();
        $defaultLocale = config('ribbon.locales')[0];

        return view('livewire.storefront.offer-request.show', [
            'groups' => $groups,
            'grandTotal' => $grandTotal,
            'locale' => $locale,
            'defaultLocale' => $defaultLocale,
        ])->layout('layouts.storefront', [
            'title' => __('storefront.offer_request.title'),
        ]);
    }
}
