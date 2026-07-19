<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\TelegramContact;
use App\Models\TelegramMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single-action controller receiving Telegram's webhook POSTs — this is
 * the only real HTTP controller in the app (everything else is Livewire),
 * since it has to speak Telegram's plain-JSON Update format rather than
 * render a component. Registered CSRF-exempt in bootstrap/app.php;
 * authenticity is instead verified via the secret Telegram echoes back in
 * X-Telegram-Bot-Api-Secret-Token (see Setting::telegram_webhook_secret,
 * set together with the webhook URL in Admin\Settings\Show::
 * connectTelegramBot()).
 *
 * Only plain text messages are handled — anything else Telegram might
 * send (photos, stickers, edited_message, callback_query, ...) is
 * silently ignored (still a 200, since Telegram will keep retrying a
 * non-2xx response) rather than erroring, since this app only supports
 * a plain text conversation.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $setting = Setting::current();
        $expectedSecret = $setting->telegram_webhook_secret;

        if (! $expectedSecret || $request->header('X-Telegram-Bot-Api-Secret-Token') !== $expectedSecret) {
            abort(403);
        }

        $message = $request->input('message');
        $text = $message['text'] ?? null;
        $chatId = $message['chat']['id'] ?? null;

        if (! $text || ! $chatId) {
            return response()->json(['ok' => true]);
        }

        $from = $message['from'] ?? [];

        $contact = TelegramContact::query()->updateOrCreate(
            ['chat_id' => $chatId],
            [
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'last_message_at' => now(),
            ],
        );

        TelegramMessage::create([
            'telegram_contact_id' => $contact->id,
            'direction' => 'in',
            'body' => $text,
            'telegram_message_id' => $message['message_id'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }
}
