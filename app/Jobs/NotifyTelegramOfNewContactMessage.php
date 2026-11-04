<?php

namespace App\Jobs;

use App\Models\ContactMessage;
use App\Models\Setting;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Super-Admin-only Telegram ping for a new /contact message — same shape
 * as NotifyTelegramOfNewOfferRequest (same bot/chat config, deliberately
 * not ShouldQueue). The text carries the buyer's phone and message, which
 * is acceptable only because the chat is the Super Admin's own; nothing
 * here, and nothing it logs, ever includes that PII. A failure must never
 * break the buyer's submit, so every error is swallowed and logged by id.
 */
class NotifyTelegramOfNewContactMessage
{
    use Dispatchable;

    public function __construct(
        public ContactMessage $contactMessage,
    ) {}

    public function handle(TelegramBotService $telegram): void
    {
        try {
            $botToken = Setting::current()->effectiveTelegramBotToken();
            $chatId = config('services.telegram.super_admin_chat_id');

            if (! $botToken || ! $chatId) {
                Log::warning('No Telegram bot token / TELEGRAM_SUPER_ADMIN_CHAT_ID is set — skipping new contact message Telegram notification.');

                return;
            }

            $lines = [
                '<b>New contact message</b>',
                'Phone: '.e($this->contactMessage->phone),
            ];

            if (filled($this->contactMessage->message)) {
                $lines[] = 'Message: '.e($this->contactMessage->message);
            }

            $lines[] = route('admin.contact-messages.index');

            $telegram->sendMessage($botToken, $chatId, implode("\n", $lines), 'HTML');
        } catch (Throwable $e) {
            Log::warning('Contact message Telegram notification failed.', [
                'contact_message_id' => $this->contactMessage->id,
                'exception' => $e::class,
            ]);
        }
    }
}
