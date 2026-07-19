<?php

namespace App\Jobs;

use App\Models\Seller;
use App\Models\Setting;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

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
    public function handle(TelegramBotService $telegram): void
    {
        $botToken = Setting::current()->effectiveTelegramBotToken();
        $chatId = config('services.telegram.super_admin_chat_id');

        if (! $botToken || ! $chatId) {
            Log::warning('No Telegram bot token (Settings or TELEGRAM_BOT_TOKEN) / TELEGRAM_SUPER_ADMIN_CHAT_ID is set — skipping new seller Telegram notification.');

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

        $telegram->sendMessage($botToken, $chatId, implode("\n", $lines), 'HTML');
    }
}
