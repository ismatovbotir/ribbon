<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" href="/favicon.ico" sizes="any">

        <title>{{ isset($title) ? $title.' · Ribbon' : 'Ribbon' }}</title>

        {{--
            Generic SEO head block, driven entirely by data each storefront
            page passes into ->layout('layouts.storefront', [...]) —
            centralized here (rather than duplicated per-page via
            @push('meta')) so every future buyer-facing page (product,
            search, ...) gets the same tag shape just by supplying the same
            variable names. Page components own the actual URLs/copy/schema;
            this block is presentation-only. See seo-engineer notes on
            Storefront\Home and Storefront\Catalog\Show for the query-string
            (filters/sort/pagination) canonicalization rules.

            $effectiveMetaDescription/$effectiveOgImage fall back to the
            sitewide Setting row (admin.settings.show) when a page doesn't
            supply its own — $settings itself comes from AppServiceProvider's
            layouts.storefront view composer, not any individual page.
        --}}
        @php
            $effectiveMetaDescription = $metaDescription ?? $settings->default_meta_description ?? null;
            $effectiveOgImage = $ogImage ?? ($settings->default_og_image_path
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($settings->default_og_image_path)
                : null);
        @endphp

        @if ($effectiveMetaDescription)
            <meta name="description" content="{{ $effectiveMetaDescription }}">
        @endif

        <meta name="robots" content="{{ $robots ?? 'index,follow' }}">

        @isset($canonicalUrl)
            <link rel="canonical" href="{{ $canonicalUrl }}">
        @endisset

        @isset($hreflangAlternates)
            @foreach ($hreflangAlternates as $hreflang => $altUrl)
                <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $altUrl }}">
            @endforeach
        @endisset

        @php
            // og:locale wants underscore region-tagged codes, not the bare
            // uz/ru/en locale keys used everywhere else in this app.
            $ogLocaleMap = ['uz' => 'uz_UZ', 'ru' => 'ru_RU', 'en' => 'en_US'];
            $currentOgLocale = $ogLocaleMap[app()->getLocale()] ?? 'en_US';
        @endphp

        <meta property="og:site_name" content="{{ __('storefront.nav.brand') }}">
        <meta property="og:type" content="{{ $ogType ?? 'website' }}">
        <meta property="og:title" content="{{ $title ?? __('storefront.nav.brand') }}">
        <meta property="og:locale" content="{{ $currentOgLocale }}">
        @isset($hreflangAlternates)
            @foreach ($hreflangAlternates as $hreflang => $altUrl)
                @continue($hreflang === app()->getLocale() || $hreflang === 'x-default')
                <meta property="og:locale:alternate" content="{{ $ogLocaleMap[$hreflang] ?? $hreflang }}">
            @endforeach
        @endisset
        @if ($effectiveMetaDescription)
            <meta property="og:description" content="{{ $effectiveMetaDescription }}">
        @endif
        @isset($canonicalUrl)
            <meta property="og:url" content="{{ $canonicalUrl }}">
        @endisset
        @if ($effectiveOgImage)
            <meta property="og:image" content="{{ $effectiveOgImage }}">
        @endif

        <meta name="twitter:card" content="{{ $effectiveOgImage ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $title ?? __('storefront.nav.brand') }}">
        @if ($effectiveMetaDescription)
            <meta name="twitter:description" content="{{ $effectiveMetaDescription }}">
        @endif
        @if ($effectiveOgImage)
            <meta name="twitter:image" content="{{ $effectiveOgImage }}">
        @endif

        @isset($structuredData)
            @foreach ($structuredData as $schema)
                <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            @endforeach
        @endisset

        {{--
            Sitewide tracking/verification, sourced from the one Setting row
            (admin.settings.show) rather than any per-page data — every
            storefront page gets these whenever an admin has actually set
            them, no per-page opt-in needed.
        --}}
        @if ($settings->google_site_verification)
            <meta name="google-site-verification" content="{{ $settings->google_site_verification }}">
        @endif
        @if ($settings->yandex_site_verification)
            <meta name="yandex-verification" content="{{ $settings->yandex_site_verification }}">
        @endif

        @stack('meta')

        @if ($settings->google_analytics_id)
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $settings->google_analytics_id }}"></script>
            <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
                window.dataLayer = window.dataLayer || [];
                function gtag() { dataLayer.push(arguments); }
                gtag('js', new Date());
                gtag('config', @js($settings->google_analytics_id));
            </script>
        @endif

        @if ($settings->yandex_metrica_id)
            <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
                (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
                m[i].l=1*new Date();
                for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
                k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
                (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

                ym(@js($settings->yandex_metrica_id), "init", {
                    clickmap: true,
                    trackLinks: true,
                    accurateTrackBounce: true,
                });
            </script>
            <noscript>
                <div><img src="https://mc.yandex.ru/watch/{{ $settings->yandex_metrica_id }}" style="position:absolute; left:-9999px;" alt=""></div>
            </noscript>
        @endif

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    {{--
        Buyer storefront shell (specs 18, 19, 20): enterprise look (slight radius, borders first, elev-* only on
        header-after-scroll / mega panel / drawer / bottom bars). 56/64px sticky header with the lg+ Catalog mega
        trigger, non-sticky content strip (lg+), search row + category chips below lg, left drawer below lg,
        exactly one fixed bottom bar per page chosen by $bottomBar ('selection' | 'product' | 'none').
        Layout variables: $hideCategoryNav (chip row), $hideContentNav (lg+ strip; request page).
    --}}
    @php
        $bottomBar = $bottomBar ?? 'selection';
        $hideCategoryNav = $hideCategoryNav ?? false;
        $hideContentNav = $hideContentNav ?? false;
        $selectionCount = \App\Services\OfferSelectionService::count();
        $navCategories = $navCategories ?? \App\Models\Category::navList();
        $currentLocale = app()->getLocale();
        $defaultLocale = config('ribbon.locales')[0];
        $activeCategorySlug = request()->route('categorySlug');
        $searchQuery = request()->routeIs('storefront.search') && is_string(request('q')) ? request('q') : '';
        $catalogActive = request()->routeIs('storefront.catalog.show', 'storefront.products.show');

        // Content menu (spec 19 3.3): Articles, News, header-flagged CMS pages (max 3), Contact. Home is added by the strip / drawer.
        $currentPath = '/'.ltrim(request()->path(), '/');
        $contentLinks = [
            ['label' => __('storefront.articles.title'), 'url' => route('storefront.articles.index'), 'active' => request()->routeIs('storefront.articles.*'), 'icon' => 'book'],
            ['label' => __('storefront.nav.news'), 'url' => route('storefront.news.index'), 'active' => request()->routeIs('storefront.news.*'), 'icon' => 'article'],
        ];
        foreach ($menuPages['header'] as $headerPage) {
            if ($headerPage['key'] === 'for-sellers') {
                continue;
            }
            $contentLinks[] = [
                'label' => $headerPage['title'],
                'url' => $headerPage['url'],
                'active' => '/'.ltrim((string) parse_url($headerPage['url'], PHP_URL_PATH), '/') === $currentPath,
                'icon' => 'document',
            ];
        }
        $contentLinks[] = ['label' => __('storefront.nav.contact'), 'url' => route('storefront.contact'), 'active' => request()->routeIs('storefront.contact'), 'icon' => 'email', 'iconOnly' => true];
        $stripLinks = [['label' => __('storefront.nav.home'), 'url' => route('storefront.home'), 'active' => request()->routeIs('storefront.home'), 'icon' => 'home', 'iconOnly' => true], ...$contentLinks];

        $forSellersPublished = \App\Models\Page::query()->isPublished()->where('key', 'for-sellers')->exists();
        $forSellersUrl = $forSellersPublished ? route('storefront.pages.for-sellers') : route('sellers.register');
        $forSellersActive = request()->routeIs('storefront.pages.for-sellers');

        $legalAddress = $settings->office_address[$currentLocale] ?? ($settings->office_address[$defaultLocale] ?? '');
        $legalAddress = implode(' · ', array_filter(array_map('trim', preg_split('/\R/', (string) $legalAddress) ?: [])));
        $footerLink = 'inline-flex min-h-11 items-center text-sm text-text-secondary hover:text-accent-700 hover:underline lg:min-h-8';
        $footerHeading = 'text-sm font-semibold text-text-primary';
        $hasContactInfo = $settings->admin_phone || $settings->admin_email || $settings->isTelegramBotConnected() || $settings->telegram_channel_url;
    @endphp
    <body
        class="storefront bg-surface text-base text-text-primary antialiased {{ $bottomBar === 'selection' && $selectionCount > 0 ? 'pb-[calc(var(--spacing-bar-slim)+env(safe-area-inset-bottom))] lg:pb-0' : '' }} {{ $bottomBar === 'product' ? 'pb-[calc(var(--spacing-bar-action)+env(safe-area-inset-bottom))] lg:pb-0' : '' }}"
        x-data="{ drawerOpen: false, filtersOpen: false, scrolled: false, selectionCount: {{ $selectionCount }} }"
        x-on:scroll.window.passive="scrolled = window.scrollY > 8"
        x-on:keydown.escape.window="drawerOpen = false"
        x-on:selection-changed.window="selectionCount = $event.detail.count"
        x-on:livewire:navigated.window="drawerOpen = false"
    >
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-(--z-toast) focus:rounded-sm focus:border focus:border-accent-700 focus:bg-surface focus:px-4 focus:py-3">{{ __('storefront.nav.skip') }}</a>

        <header
            class="sticky top-0 z-(--z-sticky) border-b border-border bg-surface transition-shadow motion-reduce:transition-none"
            x-bind:class="scrolled ? 'elev-1 border-border-strong!' : ''"
        >
            <div class="mx-auto flex h-storefront-header max-w-7xl items-center gap-1 px-2 md:gap-4 md:px-6">
                <button
                    type="button"
                    class="flex size-11 shrink-0 items-center justify-center rounded-sm text-text-primary lg:hidden"
                    x-ref="drawerOpener"
                    x-on:click="drawerOpen = true"
                    aria-label="{{ __('storefront.nav.open_menu') }}"
                    aria-controls="storefront-drawer"
                    x-bind:aria-expanded="drawerOpen"
                >
                    <x-storefront.icon name="menu" class="size-6" />
                </button>

                {{-- Logo and Catalog sit together. No `relative` here: the sticky header positions the mega panel. --}}
                <div class="flex shrink-0 items-center gap-3">
                    <a href="{{ route('storefront.home') }}" wire:navigate class="flex h-11 shrink-0 items-center px-2 md:px-0">
                        <x-logo size="md" />
                    </a>

                    <x-storefront.mega-panel :mega="$mega" :catalog-active="$catalogActive" />
                </div>

                <x-storefront.search-form id="sf-search-d" :value="$searchQuery" class="hidden min-w-0 flex-1 md:block" />

                <div class="flex-1 md:hidden"></div>

                <nav aria-label="{{ __('storefront.nav.language') }}" class="hidden lg:block">
                    <x-storefront.locale-switch compact />
                </nav>

                <a
                    href="{{ route('storefront.offer-request.show') }}"
                    wire:navigate
                    class="relative flex size-11 shrink-0 items-center justify-center rounded-sm text-text-primary hover:bg-surface-hover"
                    title="{{ __('storefront.nav.selection_label') }}"
                >
                    {{-- Accessible name = label + the live badge count (an aria-label would freeze the count at page load). --}}
                    <span class="sr-only">{{ __('storefront.nav.selection_label') }}</span>
                    <x-storefront.icon name="list-checked" class="size-6" />
                    <span
                        x-show="selectionCount > 0"
                        x-text="selectionCount"
                        @if ($selectionCount < 1) x-cloak @endif
                        class="absolute top-0.5 right-0.5 flex h-5 min-w-5 items-center justify-center rounded-xs bg-accent-700 px-1 text-xs font-medium text-white tabular-nums"
                    >{{ $selectionCount }}</span>
                </a>
            </div>
        </header>

        @unless ($hideContentNav)
            <x-storefront.content-strip :links="$stripLinks" :for-sellers-url="$forSellersUrl" :for-sellers-active="$forSellersActive" />
        @endunless

            <div class="border-b border-border bg-surface px-4 py-2 md:hidden">
                <x-storefront.search-form id="sf-search-m" :value="$searchQuery" compact />
            </div>

        {{-- Category chip row: phones and tablets only (below lg); the mega panel carries categories at lg+. --}}
        @if (! $hideCategoryNav && $navCategories->isNotEmpty())
            <nav aria-label="{{ __('storefront.nav.catalog_menu_label') }}" class="border-b border-border bg-surface-band lg:hidden">
                <div
                    class="no-scrollbar mx-auto flex max-w-7xl snap-x gap-2 overflow-x-auto px-4 py-2 md:px-6"
                    x-init="$el.querySelector('[aria-current]')?.scrollIntoView({ inline: 'center', block: 'nearest' })"
                >
                    @foreach ($navCategories as $navCategory)
                        @php
                            $navSlug = $navCategory->slug[$currentLocale] ?? '';
                            $navActive = $activeCategorySlug !== null && $activeCategorySlug === $navSlug;
                        @endphp
                        <a
                            href="{{ route('storefront.catalog.show', ['categorySlug' => $navSlug]) }}"
                            wire:navigate
                            @if ($navActive) aria-current="page" @endif
                            class="hit inline-flex h-11 shrink-0 snap-start items-center rounded-sm border px-3 text-sm font-medium whitespace-nowrap {{ $navActive ? 'border-accent-700 bg-accent-700 text-white' : 'border-border-input bg-surface text-text-primary hover:bg-surface-hover' }}"
                        >{{ $navCategory->name[$currentLocale] ?? '' }}</a>
                    @endforeach
                </div>
            </nav>
        @endif

        {{-- Drawer (below lg) --}}
        <div x-show="drawerOpen" x-cloak x-transition.opacity class="fixed inset-0 z-(--z-nav-backdrop) bg-scrim lg:hidden" x-on:click="drawerOpen = false"></div>
        <aside
            id="storefront-drawer"
            x-show="drawerOpen"
            x-cloak
            x-trap.noscroll="drawerOpen"
            {{-- Return focus to the opener only on an open→closed change.
                 x-effect also ran on init and after each wire:navigate, which
                 stole focus from the skip link on every page (QA N-03). --}}
            x-init="$watch('drawerOpen', (open, wasOpen) => { if (! open && wasOpen) $nextTick(() => $refs.drawerOpener?.focus({ preventScroll: true })) })"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('storefront.nav.menu') }}"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="elev-3 fixed inset-y-0 left-0 z-(--z-nav) flex w-drawer flex-col border-r border-border-strong bg-surface pb-[env(safe-area-inset-bottom)] motion-reduce:transition-none lg:hidden"
        >
            <div class="flex h-14 shrink-0 items-center justify-between border-b border-border-strong pr-1 pl-4">
                <x-logo size="md" />
                <button type="button" x-on:click="drawerOpen = false" class="flex size-11 items-center justify-center rounded-sm text-text-primary" aria-label="{{ __('storefront.nav.close_menu') }}">
                    <x-storefront.icon name="close" class="size-5" />
                </button>
            </div>

            @php
                $drawerRow = 'flex min-h-11 items-center justify-between gap-2 rounded-sm px-3 py-2 text-sm';
                $drawerOn = 'bg-accent-50 font-semibold text-accent-800';
                $drawerOff = 'text-text-primary hover:bg-surface-hover';
            @endphp
            <div class="flex-1 overflow-y-auto overscroll-contain py-2">
                <div class="px-2">
                    <a href="{{ route('storefront.home') }}" wire:navigate x-on:click="drawerOpen = false" @if (request()->routeIs('storefront.home')) aria-current="page" @endif class="{{ $drawerRow }} {{ request()->routeIs('storefront.home') ? $drawerOn : $drawerOff }}"><span class="flex min-w-0 items-center gap-3"><x-storefront.icon name="home" class="size-5 shrink-0 text-text-muted" /><span>{{ __('storefront.nav.home') }}</span></span></a>
                </div>

                <p class="px-4 pt-4 pb-1 text-xs font-semibold text-text-muted uppercase tracking-[0.04em]">{{ __('storefront.nav.catalog') }}</p>
                @if ($mega['categories'])
                    <x-storefront.drawer-catalog :mega="$mega" />
                @else
                    <p class="px-4 text-sm text-text-muted">{{ __('storefront.nav.no_categories') }}</p>
                @endif

                <hr class="my-2 border-border">

                <div class="px-2">
                    @foreach ($contentLinks as $l)
                        <a href="{{ $l['url'] }}" wire:navigate x-on:click="drawerOpen = false" @if ($l['active']) aria-current="page" @endif class="{{ $drawerRow }} {{ $l['active'] ? $drawerOn : $drawerOff }}"><span class="flex min-w-0 items-center gap-3"><x-storefront.icon :name="$l['icon'] ?? 'document-text'" class="size-5 shrink-0 text-text-muted" /><span>{{ $l['label'] }}</span></span></a>
                    @endforeach
                </div>

                <hr class="my-2 border-border">

                <p class="px-4 pt-2 pb-1 text-xs font-semibold text-text-muted uppercase tracking-[0.04em]">{{ __('storefront.footer.sellers_heading') }}</p>
                <div class="px-2">
                    <a href="{{ $forSellersUrl }}" wire:navigate x-on:click="drawerOpen = false" @if ($forSellersActive) aria-current="page" @endif class="{{ $drawerRow }} {{ $forSellersActive ? $drawerOn : $drawerOff }}"><span class="flex min-w-0 items-center gap-3"><x-storefront.icon name="store" class="size-5 shrink-0 text-text-muted" /><span>{{ __('storefront.nav.for_sellers') }}</span></span></a>
                    <a href="{{ route('sellers.register') }}" wire:navigate x-on:click="drawerOpen = false" class="{{ $drawerRow }} {{ $drawerOff }}"><span class="flex min-w-0 items-center gap-3"><x-storefront.icon name="user-add" class="size-5 shrink-0 text-text-muted" /><span>{{ __('storefront.nav.become_seller') }}</span></span></a>
                    <a href="{{ route('login') }}" wire:navigate x-on:click="drawerOpen = false" class="{{ $drawerRow }} {{ $drawerOff }}"><span class="flex min-w-0 items-center gap-3"><x-storefront.icon name="login" class="size-5 shrink-0 text-text-muted" /><span>{{ __('storefront.nav.seller_login') }}</span></span></a>
                </div>
            </div>

            <div class="shrink-0 space-y-3 border-t border-border-strong p-4">
                <nav aria-label="{{ __('storefront.nav.language') }}"><x-storefront.locale-switch full /></nav>
                @if ($settings->admin_phone)
                    <a href="tel:{{ $settings->admin_phone }}" class="flex h-11 items-center font-mono text-sm text-text-primary tabular-nums hover:text-accent-700">{{ $settings->admin_phone }}</a>
                @endif
                @if ($settings->isTelegramBotConnected())
                    <a href="https://t.me/{{ $settings->telegram_bot_username }}" target="_blank" rel="noopener" class="flex h-11 items-center text-sm text-text-primary hover:text-accent-700">{{ __('storefront.footer.chat_telegram') }}</a>
                @endif
            </div>
        </aside>

        <main id="main" class="mx-auto max-w-7xl px-4 py-6 md:px-6 md:py-12">
            {{ $slot }}
        </main>

        {{-- Footer (spec 19 section 6 + spec 20 8.4) - no account/order links (buyers never register). --}}
        <footer class="mt-16 border-t border-border bg-surface-band">
            <div class="mx-auto max-w-7xl px-4 py-10 md:px-6 md:py-12">
                {{-- lg+: columns spread edge to edge, however many of the optional ones exist. --}}
                <div class="grid grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-3 lg:flex lg:justify-between lg:gap-x-8">
                    <div class="col-span-2 sm:col-span-3 lg:max-w-xs">
                        <a href="{{ route('storefront.home') }}" wire:navigate class="inline-flex min-h-11 items-center">
                            <x-logo size="md" />
                        </a>
                        <p class="mt-2 max-w-xs text-sm text-text-secondary">{{ __('storefront.footer.tagline') }}</p>
                        <nav aria-label="{{ __('storefront.nav.language') }}" class="mt-4 inline-block"><x-storefront.locale-switch /></nav>
                    </div>

                    <div class="col-span-2 sm:col-span-1">
                        <p class="{{ $footerHeading }}">{{ __('storefront.footer.catalog_heading') }}</p>
                        <ul class="mt-2 grid grid-cols-2 gap-x-4 sm:block">
                            @forelse ($navCategories as $navCategory)
                                <li>
                                    <a
                                        href="{{ route('storefront.catalog.show', ['categorySlug' => $navCategory->slug[$currentLocale] ?? '']) }}"
                                        wire:navigate
                                        class="{{ $footerLink }}"
                                    >{{ $navCategory->name[$currentLocale] ?? '' }}</a>
                                </li>
                            @empty
                                <li class="text-sm text-text-muted">{{ __('storefront.nav.no_categories') }}</li>
                            @endforelse
                        </ul>
                    </div>

                    <div>
                        <p class="{{ $footerHeading }}">{{ __('storefront.footer.company_heading') }}</p>
                        <ul class="mt-2">
                            @foreach ($menuPages['footer']['company'] as $footerPage)
                                <li><a href="{{ $footerPage['url'] }}" wire:navigate class="{{ $footerLink }}">{{ $footerPage['title'] }}</a></li>
                            @endforeach
                            <li><a href="{{ route('storefront.contact') }}" wire:navigate class="{{ $footerLink }}">{{ __('storefront.nav.contact') }}</a></li>
                            <li><a href="{{ route('storefront.articles.index') }}" wire:navigate class="{{ $footerLink }}">{{ __('storefront.articles.title') }}</a></li>
                            <li><a href="{{ route('storefront.news.index') }}" wire:navigate class="{{ $footerLink }}">{{ __('storefront.nav.news') }}</a></li>
                            @unless (collect($menuPages['footer']['company'])->contains('key', 'for-sellers'))
                                <li><a href="{{ $forSellersUrl }}" wire:navigate class="{{ $footerLink }}">{{ __('storefront.nav.for_sellers') }}</a></li>
                            @endunless
                            <li><a href="{{ route('sellers.register') }}" wire:navigate class="{{ $footerLink }}">{{ __('storefront.nav.become_seller') }}</a></li>
                            <li><a href="{{ route('login') }}" wire:navigate class="{{ $footerLink }}">{{ __('storefront.nav.seller_login') }}</a></li>
                        </ul>
                    </div>

                    @if ($menuPages['footer']['legal'])
                        <div>
                            <p class="{{ $footerHeading }}">{{ __('storefront.footer.legal_heading') }}</p>
                            <ul class="mt-2">
                                @foreach ($menuPages['footer']['legal'] as $footerPage)
                                    <li><a href="{{ $footerPage['url'] }}" wire:navigate class="{{ $footerLink }}">{{ $footerPage['title'] }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($hasContactInfo)
                        <div class="col-span-2 sm:col-span-1">
                            <p class="{{ $footerHeading }}">{{ __('storefront.footer.contact_heading') }}</p>
                            <ul class="mt-2">
                                @if ($settings->admin_phone)
                                    <li><a href="tel:{{ $settings->admin_phone }}" class="{{ $footerLink }} font-mono tabular-nums">{{ $settings->admin_phone }}</a></li>
                                @endif
                                @if ($settings->admin_email)
                                    <li><a href="mailto:{{ $settings->admin_email }}" class="{{ $footerLink }}">{{ $settings->admin_email }}</a></li>
                                @endif
                                @if ($settings->isTelegramBotConnected())
                                    <li><a href="https://t.me/{{ $settings->telegram_bot_username }}" target="_blank" rel="noopener" class="{{ $footerLink }}">{{ __('storefront.footer.chat_telegram') }}</a></li>
                                @endif
                                @if ($settings->telegram_channel_url)
                                    <li><a href="{{ $settings->telegram_channel_url }}" target="_blank" rel="noopener" class="{{ $footerLink }}">{{ __('storefront.contact.channel') }}</a></li>
                                @endif
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Legal identity + marketplace disclaimer (spec 20 8.4). Never prints empty labels. --}}
            <div class="border-t border-border bg-surface">
                <div class="mx-auto flex max-w-7xl flex-col items-center gap-2 px-4 py-4 text-center md:px-6">
                    <p class="flex flex-wrap items-baseline justify-center gap-x-3 gap-y-1 text-xs text-text-secondary">
                        @if ($settings->legal_name)
                            <span class="font-medium text-text-primary">© {{ now()->year }} {{ $settings->legal_name }}</span>
                        @else
                            <span class="font-medium text-text-primary">{{ __('storefront.footer.copyright', ['year' => now()->year]) }}</span>
                        @endif
                        @if ($settings->legal_inn)
                            <span>{{ __('storefront.footer.inn') }} <span class="font-mono tabular-nums">{{ $settings->legal_inn }}</span></span>
                        @endif
                        @if ($legalAddress !== '')
                            <span>{{ $legalAddress }}</span>
                        @endif
                    </p>
                    <p class="max-w-3xl text-xs text-text-muted">{{ __('storefront.footer.disclaimer') }}</p>
                </div>
            </div>
        </footer>

        {{-- The single fixed bottom bar: selection mode here; product mode is rendered by the product page. --}}
        @if ($bottomBar === 'selection')
            <x-storefront.selection-bar :count="$selectionCount" />
        @endif

        {{-- The Ribbon consultant: on every public page. --}}
        <livewire:storefront.chat-widget />

        @livewireScripts
    </body>
</html>
