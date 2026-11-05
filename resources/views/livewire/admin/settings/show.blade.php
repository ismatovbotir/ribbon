{{--
    Single form editing the one sitewide Setting row — see
    Livewire\Admin\Settings\Show's docblock.
--}}
<div class="max-w-4xl">
    <x-page-header :title="__('admin.ui.settings')" :subtitle="__('admin.ui.sitewide_analytics_contact_info_and_default_seo_tags')">
        <x-slot:actions>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save, ogImageUpload">
                {{ __('admin.ui.save_settings') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-6">
        {{-- Analytics & verification --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.analytics_verification') }}</h2>
            </div>
            <div class="grid grid-cols-1 gap-5 px-5 py-5 sm:grid-cols-2">
                <div>
                    <label for="field-googleAnalyticsId" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.google_analytics_id') }}</label>
                    <x-input id="field-googleAnalyticsId" type="text" wire:model="googleAnalyticsId" :error="$errors->has('googleAnalyticsId')" placeholder="G-XXXXXXXXXX" />
                    @error('googleAnalyticsId')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.injects_the_ga4_tracking_snippet_sitewide_when_set') }}</p>
                </div>

                <div>
                    <label for="field-yandexMetricaId" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.yandex_metrica_counter_id') }}</label>
                    <x-input id="field-yandexMetricaId" type="text" wire:model="yandexMetricaId" :error="$errors->has('yandexMetricaId')" placeholder="12345678" />
                    @error('yandexMetricaId')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.injects_the_yandex_metrica_tag_sitewide_when_set') }}</p>
                </div>

                <div>
                    <label for="field-googleSiteVerification" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.google_search_console_verification') }}</label>
                    <x-input id="field-googleSiteVerification" type="text" wire:model="googleSiteVerification" :error="$errors->has('googleSiteVerification')" :placeholder="__('admin.ui.verification_token')" />
                    @error('googleSiteVerification')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.the_token_from_search_console_s_html_tag_verification_method') }}</p>
                </div>

                <div>
                    <label for="field-yandexSiteVerification" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.yandex_webmaster_verification') }}</label>
                    <x-input id="field-yandexSiteVerification" type="text" wire:model="yandexSiteVerification" :error="$errors->has('yandexSiteVerification')" :placeholder="__('admin.ui.verification_token')" />
                    @error('yandexSiteVerification')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.the_token_from_yandex_webmaster_s_meta_tag_verification_meth') }}</p>
                </div>
            </div>
        </div>

        {{-- Telegram bot --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.telegram_bot') }}</h2>
            </div>
            <div class="px-5 py-5">
                @if ($setting->isTelegramBotConnected())
                    <div class="flex items-center justify-between rounded-sm border border-success-200 bg-success-50 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-success-700">{{ __('admin.ui.connected_as', ['username' => $setting->telegram_bot_username]) }}</p>
                            <p class="mt-0.5 text-xs text-success-700">{{ __('admin.ui.webhook_registered_messages_sent_to_this_bot_appear_in_the_i') }} <a href="{{ route('admin.telegram.show') }}" wire:navigate class="underline">{{ __('admin.ui.open_inbox') }}</a></p>
                        </div>
                        <x-button type="button" variant="ghost" wire:click="disconnectTelegramBot" wire:loading.attr="disabled" wire:target="disconnectTelegramBot" wire:confirm="{{ __('admin.ui.disconnect_the_telegram_bot_notifications_and_the_messages_i') }}">
                            {{ __('admin.ui.disconnect') }}
                        </x-button>
                    </div>
                @else
                    <label for="field-telegramBotToken" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.bot_token') }}</label>
                    <div class="flex max-w-lg items-start gap-2">
                        <div class="flex-1">
                            <x-input id="field-telegramBotToken" type="text" wire:model="telegramBotToken" :error="$errors->has('telegramBotToken')" placeholder="123456789:AAExampleTokenFromBotFather" />
                            @error('telegramBotToken')
                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <x-button type="button" variant="primary" wire:click="connectTelegramBot" wire:loading.attr="disabled" wire:target="connectTelegramBot" class="shrink-0">
                            <span wire:loading.remove wire:target="connectTelegramBot">{{ __('admin.ui.connect_set_webhook') }}</span>
                            <span wire:loading wire:target="connectTelegramBot">{{ __('admin.ui.connecting') }}</span>
                        </x-button>
                    </div>
                    <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.botfather_caption') }}</p>
                @endif
            </div>
        </div>

        {{-- Contact --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.contact') }}</h2>
            </div>
            <div class="grid grid-cols-1 gap-5 px-5 py-5 sm:grid-cols-2">
                <div>
                    <label for="field-adminPhone" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.admin_phone') }}</label>
                    <x-input id="field-adminPhone" type="text" wire:model="adminPhone" :error="$errors->has('adminPhone')" placeholder="+998 90 123 45 67" />
                    @error('adminPhone')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="field-adminEmail" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.admin_email') }}</label>
                    <x-input id="field-adminEmail" type="email" wire:model="adminEmail" :error="$errors->has('adminEmail')" placeholder="hello@ribbon.uz" />
                    @error('adminEmail')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <p class="px-5 pb-5 text-xs text-text-muted">{{ __('admin.ui.shown_in_the_storefront_footer_when_set') }}</p>
        </div>

        {{-- Subscriptions --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.settings.subscriptions') }}</h2>
            </div>
            <div class="grid grid-cols-1 gap-5 px-5 py-5 sm:grid-cols-3">
                <x-field :label="__('admin.settings.price')" for="subscriptionPrice" :hint="__('admin.settings.price_hint')" :error="$errors->first('subscriptionPrice')">
                    <x-input id="subscriptionPrice" type="number" inputmode="numeric" min="0" step="1" class="font-mono" wire:model="subscriptionPrice" :error="$errors->has('subscriptionPrice')" />
                </x-field>
                <x-field :label="__('admin.settings.grace_days')" for="graceDays" :hint="__('admin.settings.grace_hint')" :error="$errors->first('graceDays')">
                    <x-input id="graceDays" type="number" inputmode="numeric" min="0" max="60" step="1" class="font-mono" wire:model="graceDays" :error="$errors->has('graceDays')" />
                </x-field>
                <x-field :label="__('admin.settings.reminder_days')" for="reminderDays" :hint="__('admin.settings.reminder_hint')" :error="$errors->first('reminderDays')">
                    <x-input id="reminderDays" type="number" inputmode="numeric" min="0" max="30" step="1" class="font-mono" wire:model="reminderDays" :error="$errors->has('reminderDays')" />
                </x-field>
            </div>
        </div>

        {{-- Sitemap --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.sitemap') }}</h2>
            </div>
            <div class="flex items-center justify-between px-5 py-5">
                <div>
                    <p class="text-sm text-text-primary">
                        @if ($sitemapGeneratedAt)
                            {{ __('admin.ui.last_generated', ['when' => $sitemapGeneratedAt->diffForHumans()]) }}
                        @else
                            {{ __('admin.ui.never_generated') }}
                        @endif
                    </p>
                    @if ($sitemapGeneratedAt)
                        <a href="{{ route('sitemap.xml') }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs text-accent-600 hover:underline">{{ __('admin.ui.view_live_sitemap_xml') }}</a>
                    @endif
                </div>
                <x-button type="button" variant="primary" wire:click="regenerateSitemap" wire:loading.attr="disabled" wire:target="regenerateSitemap" class="shrink-0">
                    <span wire:loading.remove wire:target="regenerateSitemap">{{ __('admin.ui.regenerate_now') }}</span>
                    <span wire:loading wire:target="regenerateSitemap">{{ __('admin.ui.regenerating') }}</span>
                </x-button>
            </div>
        </div>

        {{-- SEO defaults --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.seo_defaults') }}</h2>
            </div>
            <div class="px-5 py-5">
                <label for="field-defaultMetaDescription" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.default_meta_description') }}</label>
                <textarea id="field-defaultMetaDescription"
                    wire:model="defaultMetaDescription"
                    rows="3"
                    placeholder="{{ __('admin.ui.fallback_description_used_when_a_page_doesn_t_set_its_own') }}"
                    class="block w-full rounded-sm border bg-surface px-3 py-2 text-base text-text-primary placeholder:text-text-muted focus:border-accent-500 {{ $errors->has('defaultMetaDescription') ? 'border-danger-600' : 'border-border' }}"
                ></textarea>
                @error('defaultMetaDescription')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.used_only_when_a_storefront_page_doesn_t_supply_its_own_meta') }}</p>

                <label for="field-ogImageUpload" class="mt-5 mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.default_share_image') }}</label>
                @if ($ogImageUpload)
                    <img src="{{ $ogImageUpload->temporaryUrl() }}" alt="{{ __('admin.ui.new_default_share_image_preview') }}" class="mb-2 h-40 w-full max-w-md rounded-sm border border-border object-cover">
                @elseif ($existingOgImagePath)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingOgImagePath) }}" alt="{{ __('admin.ui.current_default_share_image') }}" class="mb-2 h-40 w-full max-w-md rounded-sm border border-border object-cover">
                @endif

                <input id="field-ogImageUpload" type="file" wire:model="ogImageUpload" accept="image/*" class="block w-full max-w-md text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

                <div wire:loading wire:target="ogImageUpload" class="mt-1 text-xs text-text-muted">{{ __('admin.ui.uploading') }}</div>

                @error('ogImageUpload')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.used_for_social_share_previews_open_graph_twitter_when_a_pag') }}</p>

                @if ($existingOgImagePath || $ogImageUpload)
                    <button type="button" wire:click="removeOgImage" class="mt-1 text-xs text-danger-600 hover:underline">{{ __('admin.ui.remove_image') }}</button>
                @endif
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save, ogImageUpload">
                {{ __('admin.ui.save_settings') }}
            </x-button>
        </div>
    </div>
</div>
