<?php

namespace App\Jobs;

use App\Models\Seller;
use App\Models\Setting;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Tells the Super Admin (only) on Telegram that a company applied as a
 * seller: contact details to check, plus a link to the admin seller page
 * where it is approved (approval stays on the website, after the INN and
 * phone are checked).
 *
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

    public function __construct(
        public Seller $seller,
    ) {}

    public function handle(TelegramBotService $telegram): void
    {
        $botToken = Setting::current()->effectiveTelegramBotToken();
        $chatId = config('services.telegram.super_admin_chat_id');

        if (! $botToken || ! $chatId) {
            Log::warning('No Telegram bot token (Settings or TELEGRAM_BOT_TOKEN) / TELEGRAM_SUPER_ADMIN_CHAT_ID is set — skipping new seller Telegram notification.');

            return;
        }

        $seller = $this->seller->loadMissing('city');
        $owner = $seller->users()->first();
        $city = $seller->city?->name;
        $city = is_array($city) ? ($city['ru'] ?? $city[config('ribbon.locales')[0]] ?? reset($city)) : $city;
        $url = route('admin.sellers.show', $seller);

        // Seller-entered text is escaped: a "<" or "&" in a name would make Telegram reject the whole message.
        $e = fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = [
            '<b>New seller application</b>',
            'Company: '.$e($seller->name),
            'INN: <code>'.$e($seller->vat_number).'</code>',
            'Phone: '.$e($seller->phone),
            'Address: '.$e(trim(($city ? $city.', ' : '').$seller->address)),
        ];

        if ($owner) {
            $lines[] = 'Owner: '.$e($owner->name).' ('.$e($owner->email).')';
        }

        $lines[] = 'Status: pending review';

        // Telegram only accepts https link buttons; elsewhere (local) the link goes in the text.
        $https = str_starts_with($url, 'https://');

        if (! $https) {
            $lines[] = $e($url);
        }

        $telegram->sendMessage(
            $botToken,
            $chatId,
            implode("\n", $lines),
            'HTML',
            $https ? ['inline_keyboard' => [[['text' => 'Open and approve', 'url' => $url]]]] : null,
        );
    }
}
