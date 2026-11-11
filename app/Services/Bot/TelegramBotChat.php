<?php

namespace App\Services\Bot;

use App\Models\Setting;
use App\Models\TelegramLink;
use App\Services\TelegramBotService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The rule-based bot (no AI) in Telegram private chats with buyers. Same
 * BotEngine as the website; this class only translates Telegram updates to
 * engine calls and replies to inline keyboards.
 *
 * Not handled here (left to the existing webhook paths): the bot switched
 * off, group chats, chats linked to a seller account, seller outcome buttons
 * ("ro:" callbacks) and seller linking tokens. "Talk to a person" / "Leave
 * your phone" put the chat in hand-off mode for HANDOFF_MINUTES, during
 * which messages go to the Admin Telegram inbox as before and staff reply
 * from there; /start or /menu brings the bot back. Each chat is limited to
 * PER_MINUTE messages and presses.
 */
final class TelegramBotChat
{
    public const CALLBACK_PREFIX = 'b|';

    private const HANDOFF_MINUTES = 30;

    /** Messages and button presses per chat per minute; more are ignored (no reply, nothing stored). */
    private const PER_MINUTE = 20;

    /** Telegram rejects callback_data longer than 64 bytes. */
    private const MAX_CALLBACK_BYTES = 64;

    public function __construct(
        private readonly BotEngine $engine,
        private readonly TelegramBotService $telegram,
    ) {}

    /**
     * A plain text message. Returns true when the bot answered it, false when
     * the webhook should store it in the Admin inbox as before.
     *
     * @param  array<string, mixed>  $message
     */
    public function handleMessage(array $message): bool
    {
        $chatId = $message['chat']['id'] ?? null;
        $text = trim((string) ($message['text'] ?? ''));

        if (! $chatId || $text === '' || ! $this->appliesTo($message['chat'] ?? [])) {
            return false;
        }

        $locale = $this->locale($message['from'] ?? []);
        $isMenu = (bool) preg_match('/^\/(start|menu)(@\w+)?$/i', $text);

        // /start and /menu always bring the bot back, even while talking to a person.
        if ($isMenu) {
            Cache::forget($this->handoffKey($chatId));
            Cache::forget($this->handoffKey($chatId).':ack');
        }

        if ($this->inHandoff($chatId)) {
            Cache::put($this->handoffKey($chatId), true, now()->addMinutes(self::HANDOFF_MINUTES));

            // Confirm only the first message of a hand-off, then stay quiet while staff reply.
            if (Cache::add($this->handoffKey($chatId).':ack', true, now()->addMinutes(self::HANDOFF_MINUTES))) {
                $this->send($chatId, __('storefront.bot.telegram_sent', [], $locale));
            }

            return false;
        }

        if ($this->throttled($chatId)) {
            return true;
        }

        $reply = $isMenu
            ? $this->engine->start($locale)
            : $this->engine->ask($text, $locale, 'telegram');

        $this->reply($chatId, $reply, $locale);

        return true;
    }

    /**
     * A pressed inline button. Returns true when it was a bot button ("b|"),
     * false for anything else (seller outcome buttons keep their own handler).
     *
     * @param  array<string, mixed>  $callback
     */
    public function handleCallback(array $callback): bool
    {
        $data = (string) ($callback['data'] ?? '');

        if (! str_starts_with($data, self::CALLBACK_PREFIX)) {
            return false;
        }

        $token = Setting::current()->effectiveTelegramBotToken();
        $chat = $callback['message']['chat'] ?? [];
        $chatId = $chat['id'] ?? null;

        if ($token && isset($callback['id'])) {
            $this->telegram->answerCallbackQuery($token, (string) $callback['id'], '');
        }

        // Only in the buyer's own private chat, and only while the bot is on.
        if (! $chatId || ($callback['from']['id'] ?? null) !== $chatId || ! $this->appliesTo($chat)) {
            return true;
        }

        if ($this->throttled($chatId)) {
            return true;
        }

        [, $action, $target] = array_pad(explode('|', $data, 3), 3, '');
        $locale = $this->locale($callback['from'] ?? []);

        $this->reply($chatId, $this->engine->press($action, $target, $locale), $locale);

        return true;
    }

    /** Private chat, bot switched on, and not a seller's linked chat. */
    private function appliesTo(array $chat): bool
    {
        return ($chat['type'] ?? null) === 'private'
            && Setting::current()->bot_telegram_enabled
            && ! TelegramLink::where('chat_id', (string) ($chat['id'] ?? ''))->exists();
    }

    private function reply(int|string $chatId, BotReply $reply, string $locale): void
    {
        if ($reply->form !== null) {
            Cache::put($this->handoffKey($chatId), true, now()->addMinutes(self::HANDOFF_MINUTES));
            Cache::forget($this->handoffKey($chatId).':ack');

            $this->send($chatId, __('storefront.bot.telegram_handoff', [], $locale));

            return;
        }

        $rows = [];
        $links = [];

        foreach ($reply->buttons as $button) {
            if ($button['url']) {
                // Telegram only accepts valid https link buttons (one bad one drops the whole reply); other links go in the text.
                if (str_starts_with($button['url'], 'https://') && BotEngine::safeUrl($button['url']) !== null) {
                    $rows[] = [['text' => $button['label'], 'url' => $button['url']]];
                } else {
                    $links[] = $button['label'].': '.$button['url'];
                }

                continue;
            }

            $data = self::CALLBACK_PREFIX.$button['action'].'|'.$button['target'];

            if (strlen($data) <= self::MAX_CALLBACK_BYTES) {
                $rows[] = [['text' => $button['label'], 'callback_data' => $data]];
            }
        }

        $text = trim($reply->text."\n\n".implode("\n", $links));

        $this->send($chatId, $text, $rows === [] ? null : ['inline_keyboard' => $this->pairShort($rows)]);
    }

    /** Two short buttons share a row; long ones get their own. */
    private function pairShort(array $rows): array
    {
        $out = [];
        $pending = null;

        foreach ($rows as $row) {
            $short = count($row) === 1 && mb_strlen($row[0]['text']) <= 18 && isset($row[0]['callback_data']);

            if ($short && $pending !== null) {
                $out[] = [$pending, $row[0]];
                $pending = null;
            } elseif ($short) {
                $pending = $row[0];
            } else {
                if ($pending !== null) {
                    $out[] = [$pending];
                    $pending = null;
                }
                $out[] = $row;
            }
        }

        if ($pending !== null) {
            $out[] = [$pending];
        }

        return $out;
    }

    private function send(int|string $chatId, string $text, ?array $keyboard = null): void
    {
        $token = Setting::current()->effectiveTelegramBotToken();

        if ($token) {
            $this->telegram->sendMessage($token, $chatId, mb_substr($text, 0, 4000), null, $keyboard);
        }
    }

    /** Telegram's language_code ("ru", "uz", "en-US") mapped to a site locale. */
    private function locale(array $from): string
    {
        $code = strtolower(substr((string) ($from['language_code'] ?? ''), 0, 2));

        return in_array($code, config('ribbon.locales'), true) ? $code : config('ribbon.locales')[0];
    }

    private function throttled(int|string $chatId): bool
    {
        $key = 'bot:tg:'.$chatId;

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

    private function inHandoff(int|string $chatId): bool
    {
        return (bool) Cache::get($this->handoffKey($chatId));
    }

    private function handoffKey(int|string $chatId): string
    {
        return 'bot:tg:handoff:'.$chatId;
    }
}
