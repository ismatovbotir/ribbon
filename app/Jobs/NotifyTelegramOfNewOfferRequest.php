<?php

namespace App\Jobs;

use App\Models\OfferRequest;
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
 * admin/offers is itself gated behind EnsureUserIsSuperAdmin: whoever gets
 * this Telegram message is the only staff role that can act on it.
 */
class NotifyTelegramOfNewOfferRequest implements ShouldQueue
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
        public OfferRequest $offerRequest,
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

        $text = implode("\n", $lines);

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            if ($response->failed()) {
                Log::warning('Telegram notification for new commercial offer request failed.', [
                    'offer_request_id' => $this->offerRequest->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Telegram notification for new commercial offer request threw an exception.', [
                'offer_request_id' => $this->offerRequest->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
