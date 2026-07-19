<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper around the three Telegram Bot API calls this app makes
 * (getMe, setWebhook, sendMessage) — centralized so the connection-test-
 * and-register flow on the Settings page, the admin inbox's reply action,
 * and the existing notification jobs all share one place that knows how
 * to talk to Telegram, rather than each hand-rolling Http::post() calls
 * (as the two notification jobs originally did before this existed).
 */
class TelegramBotService
{
    /**
     * Verifies a token actually works and fetches the bot's own username —
     * used both to validate what an admin just typed into the Settings
     * form and to build the t.me/{username} deep-link shown on the
     * storefront.
     *
     * @return array{id: int, username: string}|null
     */
    public function getMe(string $token): ?array
    {
        try {
            $response = Http::get("https://api.telegram.org/bot{$token}/getMe");

            if ($response->failed() || ! $response->json('ok')) {
                return null;
            }

            $result = $response->json('result');

            return isset($result['id'], $result['username'])
                ? ['id' => $result['id'], 'username' => $result['username']]
                : null;
        } catch (Throwable $e) {
            Log::warning('Telegram getMe threw an exception.', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Registers $url as this bot's webhook, with $secretToken as the value
     * Telegram will echo back in the X-Telegram-Bot-Api-Secret-Token
     * header on every subsequent call to it — see TelegramWebhookController
     * for the verification side of that pairing.
     */
    public function setWebhook(string $token, string $url, string $secretToken): bool
    {
        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $url,
                'secret_token' => $secretToken,
            ]);

            return $response->successful() && $response->json('ok') === true;
        } catch (Throwable $e) {
            Log::warning('Telegram setWebhook threw an exception.', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Unregisters the webhook — called when an admin disconnects the bot
     * from Settings, so Telegram stops trying to deliver updates to a URL
     * this app no longer has a valid secret/token pairing for.
     */
    public function deleteWebhook(string $token): bool
    {
        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/deleteWebhook");

            return $response->successful() && $response->json('ok') === true;
        } catch (Throwable $e) {
            Log::warning('Telegram deleteWebhook threw an exception.', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Sends a plain-text message to a chat — used for both the fire-and-
     * forget admin notifications (sellers/offer requests) and the admin
     * inbox's actual two-way replies. Returns the sent message's Telegram-
     * side ID (for TelegramMessage::telegram_message_id) on success, null
     * on failure — logged either way, never thrown, matching this app's
     * existing "don't let a Telegram hiccup break the real request" rule.
     */
    public function sendMessage(string $token, int|string $chatId, string $text, ?string $parseMode = null): ?int
    {
        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", array_filter([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ]));

            if ($response->failed() || ! $response->json('ok')) {
                Log::warning('Telegram sendMessage failed.', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('result.message_id');
        } catch (Throwable $e) {
            Log::warning('Telegram sendMessage threw an exception.', [
                'chat_id' => $chatId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
