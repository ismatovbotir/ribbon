<?php

namespace App\Jobs;

use App\Models\OfferRequest;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\User;
use App\Services\SellerTelegramService;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells each seller's linked users about a new request, in their own language.
 * Subscribers get the buyer's details, their own items and outcome buttons.
 * Free sellers get a notification with no buyer details. A seller never sees
 * another seller's items. One failed delivery never affects the others.
 */
class NotifySellersOfOfferRequest
{
    use Dispatchable;

    private const MAX_ITEM_LINES = 15;

    public function __construct(
        public OfferRequest $offerRequest,
    ) {}

    public function handle(TelegramBotService $telegram, SellerTelegramService $sellerTelegram): void
    {
        $token = Setting::current()->effectiveTelegramBotToken();

        if (! $token) {
            return;
        }

        $this->offerRequest->items()->with(['seller', 'product'])->get()
            ->groupBy('seller_id')
            ->each(function (Collection $items) use ($telegram, $sellerTelegram, $token) {
                $seller = $items->first()->seller;

                if ($seller?->status !== 'approved') {
                    return;
                }

                $full = $sellerTelegram->getsFullAlerts($seller);

                foreach ($this->recipients($seller) as $user) {
                    try {
                        $telegram->sendMessage(
                            $token,
                            $user->telegramLink->chat_id,
                            $full ? $this->fullText($items, $user) : $this->notificationText($user),
                            'HTML',
                            $full ? SellerTelegramService::outcomeKeyboard($this->offerRequest->id, $user->locale) : null,
                        );
                    } catch (Throwable $e) {
                        // The exception class only: its message could contain buyer details.
                        Log::warning('Seller Telegram alert failed.', ['seller_id' => $seller->id, 'error' => $e::class]);
                    }
                }
            });
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Seller $seller): Collection
    {
        return $seller->users()->whereHas('telegramLink')->with('telegramLink')->get();
    }

    private function notificationText(User $user): string
    {
        return implode("\n", [
            '<b>'.__('sellers.telegram.alert_title', [], $user->locale).'</b>',
            __('sellers.telegram.alert_open_panel', [], $user->locale),
            route('seller.requests'),
        ]);
    }

    private function fullText(Collection $items, User $user): string
    {
        $locale = $user->locale;
        $request = $this->offerRequest;

        $lines = [
            '<b>'.__('sellers.telegram.alert_title', [], $locale).' #'.$request->id.'</b>',
            __('sellers.requests.phone', [], $locale).': '.e($request->phone),
        ];

        if ($request->company_name) {
            $lines[] = __('sellers.requests.company', [], $locale).': '.e($request->company_name);
        }

        if ($request->email) {
            $lines[] = __('sellers.requests.email', [], $locale).': '.e($request->email);
        }

        if ($request->buyer_type) {
            $lines[] = __('sellers.requests.buyer_type', [], $locale).': '.e(__('storefront.offer_request.buyer_type.'.$request->buyer_type, [], $locale));
        }

        $lines[] = '';

        foreach ($items->take(self::MAX_ITEM_LINES) as $item) {
            $name = $item->product?->localizedName($locale) ?: '—';
            $lines[] = '• '.e($name).' — '.$item->quantity.' '.__('sellers.products.unit.'.$item->unit, [], $locale);
        }

        if ($items->count() > self::MAX_ITEM_LINES) {
            $lines[] = '• … +'.($items->count() - self::MAX_ITEM_LINES);
        }

        $lines[] = number_format($items->sum(fn ($item) => $item->lineTotal()), 0, '.', ' ').' UZS';
        $lines[] = '';
        $lines[] = route('seller.requests');

        return implode("\n", $lines);
    }
}
