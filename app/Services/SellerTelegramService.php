<?php

namespace App\Services;

use App\Exceptions\SellerAccessDeniedException;
use App\Models\OfferRequest;
use App\Models\OfferRequestOutcome;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\TelegramLink;
use App\Models\TelegramLinkToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Connects seller users to their own Telegram chat, and handles what they send
 * back. Every incoming update is treated as untrusted: a button press is only
 * honoured for the linked user's own seller and that seller's own requests.
 */
class SellerTelegramService
{
    private const TOKEN_MINUTES = 15;

    public function __construct(
        private TelegramBotService $telegram,
        private SubscriptionService $subscriptions,
    ) {}

    /** Full alerts need a live subscription, including the grace period. */
    public function getsFullAlerts(Seller $seller): bool
    {
        return $this->subscriptions->isActive($seller) || $this->subscriptions->inGrace($seller);
    }

    /** A fresh single-use deep link, or null when no bot is connected. */
    public function linkUrlFor(User $user): ?string
    {
        $username = Setting::current()->telegram_bot_username;

        if (! $username) {
            return null;
        }

        $token = Str::random(40);

        DB::transaction(function () use ($user, $token) {
            TelegramLinkToken::where('user_id', $user->id)->delete();
            TelegramLinkToken::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(self::TOKEN_MINUTES),
            ]);
        });

        return "https://t.me/{$username}?start={$token}";
    }

    public function unlink(User $user): void
    {
        TelegramLink::where('user_id', $user->id)->delete();
    }

    /** Handles "/start <token>" from a private chat. The token is never stored. */
    public function handleStart(string $token, int|string $chatId, ?string $username): void
    {
        $user = $this->consumeToken($token, $chatId, $username);

        $this->send($chatId, $user
            ? __('sellers.telegram.linked', ['name' => e($user->name)], $user->locale)
            : __('sellers.telegram.link_invalid'));
    }

    private function consumeToken(string $token, int|string $chatId, ?string $username): ?User
    {
        return DB::transaction(function () use ($token, $chatId, $username) {
            $row = TelegramLinkToken::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();

            if (! $row) {
                return null;
            }

            $row->delete();

            $user = User::find($row->user_id);

            if ($row->expires_at->isPast() || ! $user?->sellers()->exists()) {
                return null;
            }

            // A chat belongs to one user, and a user to one chat.
            TelegramLink::where('chat_id', (string) $chatId)->where('user_id', '!=', $user->id)->delete();
            TelegramLink::updateOrCreate(['user_id' => $user->id], ['chat_id' => (string) $chatId, 'username' => $username]);

            return $user;
        });
    }

    /**
     * Handles an outcome button press. Always answers the callback, otherwise
     * Telegram keeps the button spinning.
     *
     * @param  array<string, mixed>  $callback
     */
    public function handleCallback(array $callback): void
    {
        $token = Setting::current()->effectiveTelegramBotToken();
        $callbackId = $callback['id'] ?? null;

        if (! $token || ! $callbackId) {
            return;
        }

        $result = $this->applyOutcome($callback);
        $locale = $result['locale'] ?? null;

        $this->telegram->answerCallbackQuery($token, $callbackId, $result
            ? __('sellers.telegram.outcome_saved', ['outcome' => __('sellers.requests.outcome.'.$result['outcome'], [], $locale)], $locale)
            : __('sellers.telegram.outcome_refused'));

        if ($result) {
            $this->telegram->editMessageReplyMarkup(
                $token,
                $callback['message']['chat']['id'],
                $callback['message']['message_id'],
                self::outcomeKeyboard($result['request_id'], $locale, $result['outcome']),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $callback
     * @return array{request_id: int, outcome: string, locale: ?string}|null
     */
    private function applyOutcome(array $callback): ?array
    {
        $fromId = $callback['from']['id'] ?? null;
        $data = (string) ($callback['data'] ?? '');

        if (! $fromId
            || ($callback['message']['chat']['id'] ?? null) !== $fromId
            || ! isset($callback['message']['message_id'])
            || ! preg_match('/^ro:(\d+):([a-z]+)$/', $data, $match)
            || ! in_array($match[2], OfferRequestOutcome::OUTCOMES, true)) {
            return null;
        }

        $user = TelegramLink::where('chat_id', (string) $fromId)->first()?->user;

        if (! $user) {
            return null;
        }

        try {
            $seller = $user->sellerOrFail();
        } catch (SellerAccessDeniedException) {
            return null;
        }

        $request = OfferRequest::forSeller($seller)->find((int) $match[1]);

        if (! $request) {
            return null;
        }

        $request->markOutcome($seller, $match[2], $user);

        return ['request_id' => $request->id, 'outcome' => $match[2], 'locale' => $user->locale];
    }

    /**
     * @return array{inline_keyboard: array<int, array<int, array{text: string, callback_data: string}>>}
     */
    public static function outcomeKeyboard(int $requestId, ?string $locale, ?string $current = null): array
    {
        $buttons = array_map(fn (string $outcome) => [
            'text' => ($outcome === $current ? '✓ ' : '').__('sellers.requests.outcome.'.$outcome, [], $locale),
            'callback_data' => "ro:{$requestId}:{$outcome}",
        ], OfferRequestOutcome::OUTCOMES);

        return ['inline_keyboard' => array_chunk($buttons, 2)];
    }

    private function send(int|string $chatId, string $text): void
    {
        $token = Setting::current()->effectiveTelegramBotToken();

        if ($token) {
            $this->telegram->sendMessage($token, $chatId, $text, 'HTML');
        }
    }
}
