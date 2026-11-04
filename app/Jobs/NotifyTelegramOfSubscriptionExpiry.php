<?php

namespace App\Jobs;

use App\Models\Seller;
use App\Models\Setting;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Subscription expiry reminder. Synchronous like the other NotifyTelegram*
 * jobs (no guaranteed queue worker on shared hosting).
 *
 * Seller-to-Telegram linking is not built yet, so for now the reminder goes
 * to the Super Admin chat, who relays it. HOOK: when linking exists, extend
 * recipientChatIds() to return the seller's own linked chat(s) (and keep or
 * drop the admin copy); nothing else in the flow has to change.
 *
 * handle() returns true when at least one message was delivered so the
 * caller only marks the reminder as sent on success.
 */
class NotifyTelegramOfSubscriptionExpiry
{
    use Dispatchable;

    /**
     * @param  'expiring'|'grace_started'  $kind
     */
    public function __construct(
        public Seller $seller,
        public string $kind,
        public Carbon $paidUntil,
    ) {}

    /**
     * @return array<int, int|string>
     */
    protected function recipientChatIds(): array
    {
        // TODO(seller-telegram-linking): add the seller's linked chat id(s) here.
        return array_filter([config('services.telegram.super_admin_chat_id')]);
    }

    public function handle(TelegramBotService $telegram): bool
    {
        $botToken = Setting::current()->effectiveTelegramBotToken();
        $chatIds = $this->recipientChatIds();

        if (! $botToken || $chatIds === []) {
            Log::warning('No Telegram bot token / chat id - skipping subscription reminder.');

            return false;
        }

        $date = $this->paidUntil->format('Y-m-d');
        $headline = $this->kind === 'grace_started'
            ? '<b>Subscription expired - grace period started</b>'
            : '<b>Subscription expires soon</b>';

        $text = implode("\n", [
            $headline,
            'Seller: '.e($this->seller->name),
            'Phone: '.e($this->seller->phone),
            "Paid until: {$date}",
        ]);

        $sent = false;

        foreach ($chatIds as $chatId) {
            $sent = $telegram->sendMessage($botToken, $chatId, $text, 'HTML') !== null || $sent;
        }

        return $sent;
    }
}
