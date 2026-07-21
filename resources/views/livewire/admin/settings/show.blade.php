{{--
    Single form editing the one sitewide Setting row — see
    Livewire\Admin\Settings\Show's docblock.
--}}
<div class="max-w-4xl">
    <x-page-header title="Settings" subtitle="Sitewide analytics, contact info, and default SEO tags.">
        <x-slot:actions>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save, ogImageUpload">
                Save Settings
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
                <h2 class="text-lg font-semibold text-text-primary">Analytics &amp; verification</h2>
            </div>
            <div class="grid grid-cols-1 gap-5 px-5 py-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">Google Analytics ID</label>
                    <x-input type="text" wire:model="googleAnalyticsId" :error="$errors->has('googleAnalyticsId')" placeholder="G-XXXXXXXXXX" />
                    @error('googleAnalyticsId')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">Injects the GA4 tracking snippet sitewide when set.</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">Yandex Metrica counter ID</label>
                    <x-input type="text" wire:model="yandexMetricaId" :error="$errors->has('yandexMetricaId')" placeholder="12345678" />
                    @error('yandexMetricaId')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">Injects the Yandex Metrica tag sitewide when set.</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">Google Search Console verification</label>
                    <x-input type="text" wire:model="googleSiteVerification" :error="$errors->has('googleSiteVerification')" placeholder="Verification token" />
                    @error('googleSiteVerification')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">The token from Search Console's HTML tag verification method — not the full &lt;meta&gt; tag.</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">Yandex Webmaster verification</label>
                    <x-input type="text" wire:model="yandexSiteVerification" :error="$errors->has('yandexSiteVerification')" placeholder="Verification token" />
                    @error('yandexSiteVerification')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">The token from Yandex Webmaster's meta tag verification method.</p>
                </div>
            </div>
        </div>

        {{-- Telegram bot --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">Telegram bot</h2>
            </div>
            <div class="px-5 py-5">
                @if ($setting->isTelegramBotConnected())
                    <div class="flex items-center justify-between rounded-sm border border-success-200 bg-success-50 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-success-700">Connected as @{{ $setting->telegram_bot_username }}</p>
                            <p class="mt-0.5 text-xs text-success-700">Webhook registered — the <a href="{{ route('admin.telegram.show') }}" wire:navigate class="underline">Messages</a> inbox will receive anything sent to this bot.</p>
                        </div>
                        <x-button type="button" variant="ghost" wire:click="disconnectTelegramBot" wire:loading.attr="disabled" wire:target="disconnectTelegramBot" wire:confirm="Disconnect the Telegram bot? Notifications and the Messages inbox will stop working until reconnected.">
                            Disconnect
                        </x-button>
                    </div>
                @else
                    <label class="mb-1 block text-sm font-medium text-text-primary">Bot token</label>
                    <div class="flex max-w-lg items-start gap-2">
                        <div class="flex-1">
                            <x-input type="text" wire:model="telegramBotToken" :error="$errors->has('telegramBotToken')" placeholder="123456789:AAExampleTokenFromBotFather" />
                            @error('telegramBotToken')
                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <x-button type="button" variant="primary" wire:click="connectTelegramBot" wire:loading.attr="disabled" wire:target="connectTelegramBot" class="shrink-0">
                            <span wire:loading.remove wire:target="connectTelegramBot">Connect &amp; set webhook</span>
                            <span wire:loading wire:target="connectTelegramBot">Connecting…</span>
                        </x-button>
                    </div>
                    <p class="mt-1 text-xs text-text-muted">From @BotFather on Telegram. Verifies the token and registers this app's webhook in one step — nothing is saved until both succeed.</p>
                @endif
            </div>
        </div>

        {{-- Contact --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">Contact</h2>
            </div>
            <div class="grid grid-cols-1 gap-5 px-5 py-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">Admin phone</label>
                    <x-input type="text" wire:model="adminPhone" :error="$errors->has('adminPhone')" placeholder="+998 90 123 45 67" />
                    @error('adminPhone')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">Admin email</label>
                    <x-input type="email" wire:model="adminEmail" :error="$errors->has('adminEmail')" placeholder="hello@ribbon.uz" />
                    @error('adminEmail')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <p class="px-5 pb-5 text-xs text-text-muted">Shown in the storefront footer when set.</p>
        </div>

        {{-- Sitemap --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">Sitemap</h2>
            </div>
            <div class="flex items-center justify-between px-5 py-5">
                <div>
                    <p class="text-sm text-text-primary">
                        @if ($sitemapGeneratedAt)
                            Last generated {{ $sitemapGeneratedAt->diffForHumans() }}.
                        @else
                            Never generated.
                        @endif
                    </p>
                    @if ($sitemapGeneratedAt)
                        <a href="{{ route('sitemap.xml') }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs text-accent-600 hover:underline">View live sitemap.xml</a>
                    @endif
                </div>
                <x-button type="button" variant="primary" wire:click="regenerateSitemap" wire:loading.attr="disabled" wire:target="regenerateSitemap" class="shrink-0">
                    <span wire:loading.remove wire:target="regenerateSitemap">Regenerate now</span>
                    <span wire:loading wire:target="regenerateSitemap">Regenerating…</span>
                </x-button>
            </div>
        </div>

        {{-- SEO defaults --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">SEO defaults</h2>
            </div>
            <div class="px-5 py-5">
                <label class="mb-1 block text-sm font-medium text-text-primary">Default meta description</label>
                <textarea
                    wire:model="defaultMetaDescription"
                    rows="3"
                    placeholder="Fallback description used when a page doesn't set its own"
                    class="block w-full rounded-sm border bg-surface px-3 py-2 text-base text-text-primary placeholder:text-text-muted focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none {{ $errors->has('defaultMetaDescription') ? 'border-danger-600' : 'border-border' }}"
                ></textarea>
                @error('defaultMetaDescription')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-text-muted">Used only when a storefront page doesn't supply its own meta description.</p>

                <label class="mt-5 mb-1 block text-sm font-medium text-text-primary">Default share image</label>
                @if ($ogImageUpload)
                    <img src="{{ $ogImageUpload->temporaryUrl() }}" alt="New default share image preview" class="mb-2 h-40 w-full max-w-md rounded-sm border border-border object-cover">
                @elseif ($existingOgImagePath)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingOgImagePath) }}" alt="Current default share image" class="mb-2 h-40 w-full max-w-md rounded-sm border border-border object-cover">
                @endif

                <input type="file" wire:model="ogImageUpload" accept="image/*" class="block w-full max-w-md text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

                <div wire:loading wire:target="ogImageUpload" class="mt-1 text-xs text-text-muted">Uploading…</div>

                @error('ogImageUpload')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-text-muted">Used for social share previews (Open Graph/Twitter) when a page doesn't set its own. Optional, max 4MB.</p>

                @if ($existingOgImagePath || $ogImageUpload)
                    <button type="button" wire:click="removeOgImage" class="mt-1 text-xs text-danger-600 hover:underline">Remove image</button>
                @endif
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save, ogImageUpload">
                Save Settings
            </x-button>
        </div>
    </div>
</div>
