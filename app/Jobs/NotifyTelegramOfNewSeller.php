<?php

namespace App\Jobs;

use App\Models\Seller;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deliberately NOT ShouldQueue — runs synchronously in-request when
 * dispatched. This app has no persistent queue worker guaranteed to be
 * running in every environment (no Supervisor/systemd/Horizon setup), so
 * queuing this silently stranded notifications in the `jobs` table with
 * nothing ever consuming them. A Telegram API call adds a small bit of
 * request latency, which is an acceptable tradeoff for a low-volume
 * "new seller applied" notification actually arriving.
 */
class NotifyTelegramOfNewSeller
{
    use Dispatchable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Seller $seller,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.super_admin_chat_id');

        if (! $botToken || ! $chatId) {
            Log::warning('TELEGRAM_BOT_TOKEN / TELEGRAM_SUPER_ADMIN_CHAT_ID are not set in .env — skipping new seller Telegram notification.');

            return;
        }

        $owner = $this->seller->users()->first();

        $lines = [
            '<b>New seller application</b>',
            "Company: {$this->seller->name}",
            "Phone: {$this->seller->phone}",
            "VAT number: {$this->seller->vat_number}",
        ];

        if ($owner) {
            $lines[] = "Owner: {$owner->name} ({$owner->email})";
        }

        $lines[] = 'Status: pending review';

        $text = implode("\n", $lines);

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            if ($response->failed()) {
                Log::warning('Telegram notification for new seller failed.', [
                    'seller_id' => $this->seller->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Telegram notification for new seller threw an exception.', [
                'seller_id' => $this->seller->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
