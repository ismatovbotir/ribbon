<?php

namespace App\Livewire\Storefront;

use App\Rules\PhoneNumber;
use App\Jobs\NotifyTelegramOfNewContactMessage;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * /contact — buyer contact details plus a phone-first message form.
 * Public and unauthenticated (buyers have no accounts). Same abuse
 * protection as the offer request page: honeypot `website`, a #[Locked]
 * server-set time trap (>= 3s), 5 submits / 10 min per IP and 3 / hour per
 * digits-only phone. Creates a ContactMessage (buyer PII, Super Admin only
 * — never logged) and pings the Super Admin on Telegram; a notification
 * failure never fails the submit.
 */
class Contact extends Component
{
    public string $phone = '';

    public string $message = '';

    public bool $submitted = false;

    /** Honeypot — real buyers never see or fill it. */
    public string $website = '';

    /** Time trap, set on mount and locked against client rewrites. */
    #[Locked]
    public int $renderedAt = 0;

    /**
     * UTM params + external referrer captured when the page was opened
     * (Livewire update requests no longer carry the original query string).
     *
     * @var array<string, string>
     */
    #[Locked]
    public array $utm = [];

    #[Locked]
    public ?string $referrer = null;

    private const MIN_FILL_SECONDS = 3;

    private const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public function mount(): void
    {
        $this->renderedAt = now()->getTimestamp();

        foreach (self::UTM_KEYS as $key) {
            $value = request()->query($key);

            if (is_string($value) && $value !== '') {
                $this->utm[$key] = Str::limit($value, 100, '');
            }
        }

        $referer = request()->headers->get('referer');

        if (is_string($referer) && $referer !== '' && parse_url($referer, PHP_URL_HOST) !== request()->getHost()) {
            // Origin + path only: query strings can carry tokens/PII.
            $parts = parse_url($referer);
            $this->referrer = isset($parts['host'])
                ? Str::limit(($parts['scheme'] ?? 'https').'://'.$parts['host'].($parts['path'] ?? ''), 255, '')
                : null;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32', new PhoneNumber],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'phone' => __('storefront.contact.phone'),
            'message' => __('storefront.contact.message'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'phone.required' => __('storefront.contact.validation.phone_required'),
        ];
    }

    public function submit(): void
    {
        // Spam traps first: pretend success so bots get no signal.
        if ($this->website !== '' || now()->getTimestamp() - $this->renderedAt < self::MIN_FILL_SECONDS) {
            $this->submitted = true;

            return;
        }

        $this->validate();

        $ipKey = 'contact:ip:'.request()->ip();
        $phoneKey = 'contact:phone:'.preg_replace('/\D+/', '', $this->phone);

        if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($phoneKey, 3)) {
            $this->addError('phone', __('storefront.contact.throttled'));

            return;
        }

        RateLimiter::hit($ipKey, 600);
        RateLimiter::hit($phoneKey, 3600);

        $contactMessage = ContactMessage::create([
            'source' => 'contact',
            'phone' => trim($this->phone),
            'message' => trim($this->message) !== '' ? trim($this->message) : null,
            'locale' => app()->getLocale(),
            'utm' => $this->utm !== [] ? $this->utm : null,
            'referrer' => $this->referrer,
            'status' => 'new',
        ]);

        // Never let a Telegram problem fail the buyer's submit. Log only
        // the row id and exception class — never the phone or message.
        try {
            NotifyTelegramOfNewContactMessage::dispatch($contactMessage);
        } catch (Throwable $e) {
            Log::warning('Contact message notification dispatch failed.', [
                'contact_message_id' => $contactMessage->id,
                'exception' => $e::class,
            ]);
        }

        $this->message = '';
        $this->submitted = true;
    }

    /**
     * Details rows for the view, only those with data:
     * ['type' => phone|email|telegram|channel|hours|address, 'label', 'value', 'href' (nullable)].
     *
     * @return list<array{type: string, label: string, value: string, href: ?string}>
     */
    private function detailRows(Setting $settings, string $locale): array
    {
        $default = config('ribbon.locales')[0];
        $localized = fn (?array $values) => trim((string) (($values[$locale] ?? null) ?: ($values[$default] ?? '')));
        $rows = [];

        if (filled($settings->admin_phone)) {
            $rows[] = ['type' => 'phone', 'label' => __('storefront.contact.phone'), 'value' => $settings->admin_phone, 'href' => 'tel:'.preg_replace('/[^\d+]/', '', $settings->admin_phone)];
        }

        if (filled($settings->admin_email)) {
            $rows[] = ['type' => 'email', 'label' => __('storefront.contact.email'), 'value' => $settings->admin_email, 'href' => 'mailto:'.$settings->admin_email];
        }

        if ($settings->isTelegramBotConnected()) {
            $rows[] = ['type' => 'telegram', 'label' => __('storefront.contact.telegram'), 'value' => '@'.$settings->telegram_bot_username, 'href' => 'https://t.me/'.$settings->telegram_bot_username];
        }

        if (filled($settings->telegram_channel_url) && str_starts_with($settings->telegram_channel_url, 'https://')) {
            $rows[] = ['type' => 'channel', 'label' => __('storefront.contact.channel'), 'value' => $settings->telegram_channel_url, 'href' => $settings->telegram_channel_url];
        }

        if (($hours = $localized($settings->working_hours)) !== '') {
            $rows[] = ['type' => 'hours', 'label' => __('storefront.contact.hours'), 'value' => $hours, 'href' => null];
        }

        if (($address = $localized($settings->office_address)) !== '') {
            $rows[] = ['type' => 'address', 'label' => __('storefront.contact.address'), 'value' => $address, 'href' => null];
        }

        return $rows;
    }

    public function render()
    {
        $locale = app()->getLocale();
        $default = config('ribbon.locales')[0];
        $settings = Setting::current();

        $intro = Page::query()->isPublished()->where('key', 'contact')->first();
        $title = $intro ? $intro->localized('title', $locale) : '';
        $title = $title !== '' ? $title : __('storefront.contact.title');
        $lead = $intro ? $intro->localized('lead', $locale) : '';
        $lead = $lead !== '' ? $lead : __('storefront.contact.lead');

        $langSuffix = $locale !== $default ? '?lang='.$locale : '';
        $hreflang = [];

        foreach (config('ribbon.locales') as $loc) {
            $hreflang[$loc] = url('/contact').($loc !== $default ? '?lang='.$loc : '');
        }

        $hreflang['x-default'] = $hreflang[$default];

        $address = trim((string) (($settings->office_address[$locale] ?? null) ?: ($settings->office_address[$default] ?? '')));

        return view('livewire.storefront.contact', [
            'title' => $title,
            'lead' => $lead,
            'introBody' => $intro ? $intro->localized('body', $locale) : '',
            'rows' => $this->detailRows($settings, $locale),
            'phoneHref' => filled($settings->admin_phone) ? preg_replace('/[^\d+]/', '', $settings->admin_phone) : null,
            'adminPhone' => $settings->admin_phone,
            'legalName' => $settings->legal_name,
            'legalInn' => $settings->legal_inn,
            'officeAddress' => $address,
        ])->layout('layouts.storefront', [
            'title' => $title,
            'metaDescription' => $intro && ($d = $intro->localized('meta_description', $locale)) !== '' ? $d : Str::limit($lead, 155),
            'canonicalUrl' => url('/contact').$langSuffix,
            'hreflangAlternates' => $hreflang,
            'navCategories' => Category::navList(),
            'structuredData' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => __('storefront.catalog.breadcrumb_home'), 'item' => url('/').$langSuffix],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => url('/contact').$langSuffix],
                    ],
                ],
            ],
        ]);
    }
}
