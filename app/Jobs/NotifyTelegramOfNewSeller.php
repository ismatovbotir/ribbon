<?php

namespace App\Jobs;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyTelegramOfNewSeller implements ShouldQueue
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
