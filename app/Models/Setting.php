<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton — always exactly one row, fetched/created on demand via
 * current() rather than looked up by a route-bound ID anywhere (there is
 * no "settings list", just the one sitewide record Super Admin edits at
 * admin.settings.show).
 */
#[Fillable([
    'google_analytics_id',
    'yandex_metrica_id',
    'google_site_verification',
    'yandex_site_verification',
    'admin_phone',
    'admin_email',
    'default_meta_description',
    'default_og_image_path',
    'telegram_bot_token',
    'telegram_bot_username',
    'telegram_webhook_secret',
    'sitemap_generated_at',
    'subscription_price_uzs',
    'subscription_grace_days',
    'subscription_reminder_days',
    'working_hours',
    'office_address',
    'legal_name',
    'legal_inn',
    'telegram_channel_url',
])]
class Setting extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sitemap_generated_at' => 'datetime',
            'subscription_price_uzs' => 'integer',
            'subscription_grace_days' => 'integer',
            'subscription_reminder_days' => 'integer',
            // JSON keyed by locale, multiline strings.
            'working_hours' => 'array',
            'office_address' => 'array',
        ];
    }

    /**
     * The one settings row, creating it with all-null columns on first
     * access if it doesn't exist yet — avoids needing a seeder for what is
     * purely optional, admin-entered config with no required values.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /**
     * The token that actually authenticates Telegram API calls —
     * DB-editable value takes priority, falling back to the original
     * .env-based TELEGRAM_BOT_TOKEN so existing deployments keep working
     * until an admin saves one here. See the settings migration's
     * docblock.
     */
    public function effectiveTelegramBotToken(): ?string
    {
        return $this->telegram_bot_token ?: config('services.telegram.bot_token');
    }

    public function graceDays(): int
    {
        return max(0, (int) ($this->subscription_grace_days ?? 7));
    }

    public function reminderDays(): int
    {
        return max(0, (int) ($this->subscription_reminder_days ?? 3));
    }

    public function isTelegramBotConnected(): bool
    {
        return filled($this->telegram_bot_username);
    }
}
