<?php

namespace App\Jobs;

use App\Models\CommercialOfferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mirrors NotifyTelegramOfNewSeller exactly (same bot/chat config, same
 * fire-and-forget-with-logging error handling) — this is the Super-Admin-
 * only counterpart for buyers' Commercial Offer requests, which is why
 * admin/commercial-offers is itself gated behind EnsureUserIsSuperAdmin:
 * whoever gets this Telegram message is the only staff role that can act
 * on it.
 */
class NotifyTelegramOfNewCommercialOfferRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * A bad token or a Telegram API hiccup is handled inline (logged and
     * swallowed), not by retrying — one attempt is enough.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public CommercialOfferRequest $commercialOfferRequest,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.super_admin_chat_id');

        if (! $botToken || ! $chatId) {
            Log::warning('TELEGRAM_BOT_TOKEN / TELEGRAM_SUPER_ADMIN_CHAT_ID are not set in .env — skipping new commercial offer request Telegram notification.');

            return;
        }

        $this->commercialOfferRequest->loadMissing('items.seller');

        $itemCount = $this->commercialOfferRequest->items->count();
        $sellerNames = $this->commercialOfferRequest->items
            ->pluck('seller.name')
            ->filter()
            ->unique()
            ->values();
        $grandTotal = $this->commercialOfferRequest->items->sum(fn ($item) => $item->lineTotal());

        $lines = [
            '<b>New commercial offer request</b>',
            "Phone: {$this->commercialOfferRequest->phone}",
        ];

        if ($this->commercialOfferRequest->company_name) {
            $lines[] = "Company: {$this->commercialOfferRequest->company_name}";
        }

        if ($this->commercialOfferRequest->email) {
            $lines[] = "Email: {$this->commercialOfferRequest->email}";
        }

        $lines[] = "Items: {$itemCount} across {$sellerNames->count()} seller(s) (".$sellerNames->implode(', ').')';
        $lines[] = 'Total: '.number_format($grandTotal).' UZS';
        $lines[] = route('admin.commercial-offers.show', $this->commercialOfferRequest);

        $text = implode("\n", $lines);

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            if ($response->failed()) {
                Log::warning('Telegram notification for new commercial offer request failed.', [
                    'commercial_offer_request_id' => $this->commercialOfferRequest->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Telegram notification for new commercial offer request threw an exception.', [
                'commercial_offer_request_id' => $this->commercialOfferRequest->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
