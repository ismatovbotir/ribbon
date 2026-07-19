<?php

namespace App\Jobs;

use App\Models\OfferRequest;
use App\Models\Setting;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors NotifyTelegramOfNewSeller exactly (same bot/chat config, same
 * fire-and-forget-with-logging error handling, and deliberately NOT
 * ShouldQueue for the same reason — see that class's docblock) — this is
 * the Super-Admin-only counterpart for buyers' Commercial Offer requests,
 * which is why admin/offers is itself gated behind EnsureUserIsSuperAdmin:
 * whoever gets this Telegram message is the only staff role that can act
 * on it.
 */
class NotifyTelegramOfNewOfferRequest
{
    use Dispatchable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public OfferRequest $offerRequest,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TelegramBotService $telegram): void
    {
        $botToken = Setting::current()->effectiveTelegramBotToken();
        $chatId = config('services.telegram.super_admin_chat_id');

        if (! $botToken || ! $chatId) {
            Log::warning('No Telegram bot token (Settings or TELEGRAM_BOT_TOKEN) / TELEGRAM_SUPER_ADMIN_CHAT_ID is set — skipping new commercial offer request Telegram notification.');

            return;
        }

        $this->offerRequest->loadMissing('items.seller');

        $itemCount = $this->offerRequest->items->count();
        $sellerNames = $this->offerRequest->items
            ->pluck('seller.name')
            ->filter()
            ->unique()
            ->values();
        $grandTotal = $this->offerRequest->items->sum(fn ($item) => $item->lineTotal());

        $lines = [
            '<b>New commercial offer request</b>',
            "Phone: {$this->offerRequest->phone}",
        ];

        if ($this->offerRequest->company_name) {
            $lines[] = "Company: {$this->offerRequest->company_name}";
        }

        if ($this->offerRequest->email) {
            $lines[] = "Email: {$this->offerRequest->email}";
        }

        $lines[] = "Items: {$itemCount} across {$sellerNames->count()} seller(s) (".$sellerNames->implode(', ').')';
        $lines[] = 'Total: '.number_format($grandTotal).' UZS';
        $lines[] = route('admin.offers.show', $this->offerRequest);

        $telegram->sendMessage($botToken, $chatId, implode("\n", $lines), 'HTML');
    }
}
