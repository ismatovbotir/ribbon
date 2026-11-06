<?php

namespace App\Console\Commands;

use App\Models\ContactMessage;
use App\Models\Setting;
use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Delivers waiting chat button messages to the Super Admin on Telegram. A
 * message is marked sent only after Telegram accepts it, so a failure is simply
 * retried on the next run.
 */
class SendChatMessages extends Command
{
    private const BATCH = 20;

    protected $signature = 'chat-messages:send';

    protected $description = 'Send waiting chat button messages to the Super Admin on Telegram';

    public function handle(TelegramBotService $telegram): int
    {
        $waiting = ContactMessage::query()
            ->where('source', 'chat')
            ->whereNull('telegram_sent_at')
            ->orderBy('id')
            ->limit(self::BATCH)
            ->get();

        if ($waiting->isEmpty()) {
            return self::SUCCESS;
        }

        $token = Setting::current()->effectiveTelegramBotToken();
        $chatId = config('services.telegram.super_admin_chat_id');

        if (! $token || ! $chatId) {
            Log::warning('Chat messages are waiting, but no Telegram bot token or Super Admin chat id is set.');

            return self::SUCCESS;
        }

        foreach ($waiting as $message) {
            if ($telegram->sendMessage($token, $chatId, $this->text($message), 'HTML') === null) {
                // Stop here: the next run retries, keeping messages in order.
                return self::SUCCESS;
            }

            $message->forceFill(['telegram_sent_at' => now()])->save();
        }

        return self::SUCCESS;
    }

    private function text(ContactMessage $message): string
    {
        return implode("\n", array_filter([
            '<b>New chat message</b>',
            'Phone: '.e($message->phone),
            'Message: '.e($message->message),
            $message->page ? 'Page: '.e($message->page) : null,
            route('admin.contact-messages.index'),
        ]));
    }
}
