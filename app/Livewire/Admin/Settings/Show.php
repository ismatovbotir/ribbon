<?php

namespace App\Livewire\Admin\Settings;

use App\Models\Setting;
use App\Services\TelegramBotService;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Single form editing the one sitewide Setting row (see Setting::current())
 * — no create/index/edit routes, just this one page at admin.settings.show,
 * Super Admin only (see routes/web.php and EnsureUserIsSuperAdmin).
 */
class Show extends Component
{
    use WithFileUploads;

    public Setting $setting;

    public ?string $googleAnalyticsId = null;

    public ?string $yandexMetricaId = null;

    public ?string $googleSiteVerification = null;

    public ?string $yandexSiteVerification = null;

    public ?string $adminPhone = null;

    public ?string $adminEmail = null;

    public ?string $defaultMetaDescription = null;

    public $ogImageUpload = null;

    public ?string $existingOgImagePath = null;

    public ?string $telegramBotToken = null;

    public function mount(): void
    {
        $this->setting = Setting::current();
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->googleAnalyticsId = $this->setting->google_analytics_id;
        $this->yandexMetricaId = $this->setting->yandex_metrica_id;
        $this->googleSiteVerification = $this->setting->google_site_verification;
        $this->yandexSiteVerification = $this->setting->yandex_site_verification;
        $this->adminPhone = $this->setting->admin_phone;
        $this->adminEmail = $this->setting->admin_email;
        $this->defaultMetaDescription = $this->setting->default_meta_description;
        $this->ogImageUpload = null;
        $this->existingOgImagePath = $this->setting->default_og_image_path;
        $this->telegramBotToken = $this->setting->telegram_bot_token;
    }

    public function removeOgImage(): void
    {
        $this->ogImageUpload = null;
        $this->existingOgImagePath = null;
    }

    /**
     * Verifies the typed token against Telegram's own getMe endpoint (so a
     * typo or revoked token is caught immediately, not discovered later
     * when notifications silently stop working), then registers this
     * app's webhook URL with a freshly generated secret. Only persists
     * anything once both steps succeed — a bad token never gets saved as
     * if it were good.
     */
    public function connectTelegramBot(TelegramBotService $telegram): void
    {
        $this->validate([
            'telegramBotToken' => ['required', 'string', 'max:255'],
        ]);

        $me = $telegram->getMe($this->telegramBotToken);

        if (! $me) {
            $this->addError('telegramBotToken', 'Could not verify this token with Telegram — double-check it and try again.');

            return;
        }

        $webhookSecret = Str::random(40);

        if (! $telegram->setWebhook($this->telegramBotToken, route('telegram.webhook'), $webhookSecret)) {
            $this->addError('telegramBotToken', 'The token is valid, but registering the webhook with Telegram failed. Try again.');

            return;
        }

        $this->setting->update([
            'telegram_bot_token' => $this->telegramBotToken,
            'telegram_bot_username' => $me['username'],
            'telegram_webhook_secret' => $webhookSecret,
        ]);

        session()->flash('status', "Connected as @{$me['username']} — webhook registered.");
    }

    public function disconnectTelegramBot(TelegramBotService $telegram): void
    {
        if ($token = $this->setting->telegram_bot_token) {
            $telegram->deleteWebhook($token);
        }

        $this->setting->update([
            'telegram_bot_token' => null,
            'telegram_bot_username' => null,
            'telegram_webhook_secret' => null,
        ]);

        $this->telegramBotToken = null;

        session()->flash('status', 'Telegram bot disconnected.');
    }

    public function save(): void
    {
        $this->validate([
            'googleAnalyticsId' => ['nullable', 'string', 'max:64'],
            'yandexMetricaId' => ['nullable', 'string', 'max:32'],
            'googleSiteVerification' => ['nullable', 'string', 'max:255'],
            'yandexSiteVerification' => ['nullable', 'string', 'max:255'],
            'adminPhone' => ['nullable', 'string', 'max:32'],
            'adminEmail' => ['nullable', 'email', 'max:255'],
            'defaultMetaDescription' => ['nullable', 'string', 'max:300'],
            'ogImageUpload' => ['nullable', 'image', 'max:4096'],
        ]);

        $ogImagePath = $this->ogImageUpload
            ? $this->ogImageUpload->store('settings', 'public')
            : $this->existingOgImagePath;

        $this->setting->update([
            'google_analytics_id' => $this->googleAnalyticsId,
            'yandex_metrica_id' => $this->yandexMetricaId,
            'google_site_verification' => $this->googleSiteVerification,
            'yandex_site_verification' => $this->yandexSiteVerification,
            'admin_phone' => $this->adminPhone,
            'admin_email' => $this->adminEmail,
            'default_meta_description' => $this->defaultMetaDescription,
            'default_og_image_path' => $ogImagePath,
        ]);

        $this->resetForm();

        session()->flash('status', 'Settings saved.');
    }

    public function render()
    {
        return view('livewire.admin.settings.show')->layout('layouts.admin', [
            'title' => 'Settings',
            'breadcrumb' => [
                ['label' => 'Settings'],
            ],
        ]);
    }
}
