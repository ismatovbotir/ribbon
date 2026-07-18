<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

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
                <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
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
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag() { dataLayer.push(arguments); }
                gtag('js', new Date());
                gtag('config', @js($settings->google_analytics_id));
            </script>
        @endif

        @if ($settings->yandex_metrica_id)
            <script>
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
        Buyer storefront shell — no authenticated actor, no sidebar (buyers
        never register/log in, see CLAUDE.md). Conventional public-site
        header + content + footer, scoped `.storefront` (orange accent,
        roomier type scale) per docs/design/08 & 09. This is NOT
        layouts/public.blade.php (which stays the minimal seller
        auth-flow shell) — the storefront needs real marketplace chrome.
    --}}
    <body class="storefront bg-surface text-base text-text-primary antialiased" x-data="{ mobileMenuOpen: false, catalogMenuOpen: false, mobileSearchOpen: false }">
        {{--
            Header — sticky, spacing-storefront-header (72px) desktop/tablet,
            h-topbar (56px) mobile. See docs/design/09-storefront-layout-shell.md.
        --}}
        <header class="sticky top-0 z-sticky border-b border-border bg-surface">
            <div class="mx-auto flex h-topbar max-w-7xl items-center gap-3 px-4 md:h-storefront-header md:px-6">
                {{-- Mobile: hamburger --}}
                <button
                    type="button"
                    class="text-text-secondary md:hidden"
                    x-on:click="mobileMenuOpen = true"
                    aria-label="{{ __('storefront.nav.open_menu') }}"
                >
                    <svg class="h-6 w-6" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </button>

                {{-- Logo --}}
                <a href="{{ route('storefront.home') }}" wire:navigate class="flex shrink-0 items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-md bg-accent-600 text-lg font-semibold text-white">R</span>
                    <span class="hidden text-xl font-semibold text-text-primary sm:inline">{{ __('storefront.nav.brand') }}</span>
                </a>

                {{--
                    Catalog trigger (desktop/tablet) — toggles the
                    full-width mega-menu panel anchored to the header's
                    bottom edge (see the panel markup just before
                    </header> below; it lives outside this flex row so it
                    can span the full viewport width).
                --}}
                <button
                    type="button"
                    data-catalog-trigger
                    x-on:click="catalogMenuOpen = !catalogMenuOpen"
                    x-bind:aria-expanded="catalogMenuOpen"
                    aria-controls="storefront-catalog-menu"
                    class="hidden h-9 shrink-0 items-center gap-1.5 rounded-lg border border-border px-3 text-sm font-medium text-text-primary hover:bg-surface-hover md:flex"
                >
                    <svg class="h-4 w-4 text-text-secondary" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M3 4.5h14M3 10h14M3 15.5h14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    </svg>
                    {{ __('storefront.nav.catalog_trigger') }}
                    <svg class="h-3.5 w-3.5 text-text-muted transition-transform" x-bind:class="catalogMenuOpen && 'rotate-180'" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>

                {{-- Search (desktop/tablet inline, mobile icon-triggered overlay) --}}
                <form action="/search" method="GET" class="hidden max-w-xl flex-1 md:flex">
                    <label for="storefront-search" class="sr-only">{{ __('storefront.nav.search_submit') }}</label>
                    <div class="relative w-full">
                        <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-text-muted" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle cx="9" cy="9" r="5.5" stroke="currentColor" stroke-width="1.4" />
                            <path d="M17 17l-3.5-3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                        </svg>
                        <input
                            id="storefront-search"
                            type="search"
                            name="q"
                            placeholder="{{ __('storefront.nav.search_placeholder') }}"
                            class="h-9 w-full rounded-lg border border-border bg-surface pr-3 pl-9 text-sm text-text-primary focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none"
                        >
                    </div>
                </form>

                {{-- Mobile: search icon --}}
                <button
                    type="button"
                    class="ml-auto text-text-secondary md:hidden"
                    x-on:click="mobileSearchOpen = true"
                    aria-label="{{ __('storefront.nav.search_submit') }}"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <circle cx="9" cy="9" r="5.5" stroke="currentColor" stroke-width="1.4" />
                        <path d="M17 17l-3.5-3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    </svg>
                </button>

                {{-- Locale switcher (desktop/tablet) — same ?lang= mechanism as layouts/public.blade.php --}}
                <nav aria-label="{{ __('storefront.nav.language') }}" class="hidden shrink-0 items-center gap-1 text-sm md:flex">
                    @foreach (['uz' => 'UZ', 'ru' => 'RU', 'en' => 'EN'] as $locale => $label)
                        <a
                            href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}"
                            class="rounded-sm px-1.5 py-1 {{ app()->getLocale() === $locale ? 'font-semibold text-accent-600' : 'text-text-muted hover:text-text-secondary' }}"
                        >{{ $label }}</a>
                    @endforeach
                </nav>

                {{--
                    Selection indicator — clipboard/list glyph, never a cart
                    icon (see doc 09). Links to the review/submit page (see
                    App\Livewire\Storefront\OfferRequest\Show); count comes
                    from OfferSelectionService so this stays in sync with
                    whatever that class considers a valid line.
                --}}
                @php
                    $selectionCount = \App\Services\OfferSelectionService::count();
                @endphp
                <a
                    href="{{ route('storefront.offer-request.show') }}"
                    wire:navigate
                    class="relative ml-1 shrink-0 text-text-secondary hover:text-text-primary"
                    aria-label="{{ __('storefront.nav.selection_aria', ['count' => $selectionCount]) }}"
                    title="{{ __('storefront.nav.selection_label') }}"
                >
                    <svg class="h-6 w-6" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect x="4.5" y="3.5" width="11" height="14" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                        <path d="M7.5 3.5h5a1 1 0 0 1 1 1v1h-7v-1a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.4" />
                        <path d="M7 9.5h6M7 12.5h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    </svg>
                    @if ($selectionCount > 0)
                        <span class="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent-600 px-1 text-[10px] font-semibold text-white">
                            {{ $selectionCount }}
                        </span>
                    @endif
                </a>
            </div>

            {{--
                Catalog mega-menu (desktop/tablet) — full-width panel
                anchored to the sticky header's bottom edge. Categories are
                a deliberately flat list (no nesting, see CLAUDE.md), so
                this is a single-level grid of category cards — icon, name,
                approved-product count (see Category::navList()) — not a
                two-level category→subcategory flyout. Capped to the
                viewport space below the header and scrollable past that.

                click.outside guard: the panel sits outside the trigger
                button's subtree, so a plain `catalogMenuOpen = false`
                would also fire on the trigger click itself and fight the
                toggle — hence the [data-catalog-trigger] check.
            --}}
            <div
                id="storefront-catalog-menu"
                x-show="catalogMenuOpen"
                x-cloak
                x-transition.opacity.duration.150ms
                x-on:keydown.escape.window="catalogMenuOpen = false"
                x-on:click.outside="$event.target.closest('[data-catalog-trigger]') || (catalogMenuOpen = false)"
                class="absolute inset-x-0 top-full z-dropdown hidden max-h-[calc(100vh-var(--spacing-storefront-header))] overflow-y-auto border-b border-border bg-surface-overlay shadow-sm md:block"
            >
                <div class="mx-auto max-w-7xl px-4 py-5 md:px-6">
                    @if (($navCategories ?? collect())->isEmpty())
                        <p class="text-sm text-text-muted">{{ __('storefront.nav.no_categories') }}</p>
                    @else
                        <p class="mb-3 text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('storefront.nav.catalog_menu_label') }}</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 lg:grid-cols-3 xl:grid-cols-4">
                            @foreach ($navCategories as $navCategory)
                                <a
                                    href="{{ route('storefront.catalog.show', ['categorySlug' => $navCategory->slug[app()->getLocale()] ?? '']) }}"
                                    wire:navigate
                                    x-on:click="catalogMenuOpen = false"
                                    class="group flex items-center gap-3 rounded-lg px-2.5 py-2.5 hover:bg-surface-hover"
                                >
                                    @if ($navCategory->image_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($navCategory->image_path) }}" alt="" class="h-9 w-9 shrink-0 rounded-md border border-border object-cover">
                                    @else
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-surface-subtle text-text-muted">
                                            <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                                <rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.3" />
                                            </svg>
                                        </span>
                                    @endif
                                    <span class="min-w-0 text-sm text-text-secondary group-hover:text-text-primary">
                                        <span class="truncate font-medium">{{ $navCategory->name[app()->getLocale()] ?? '' }}</span>
                                        <span class="ml-1 text-xs text-text-muted">({{ $navCategory->approved_products_count ?? 0 }})</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </header>

        {{-- Mobile off-canvas menu --}}
        <div
            x-show="mobileMenuOpen"
            x-cloak
            class="fixed inset-0 z-modal-backdrop bg-slate-900/40 md:hidden"
            x-on:click="mobileMenuOpen = false"
        ></div>
        <aside
            x-show="mobileMenuOpen"
            x-cloak
            x-transition
            class="fixed inset-y-0 left-0 z-modal flex w-80 max-w-[85vw] flex-col overflow-y-auto border-r border-border bg-surface md:hidden"
        >
            <div class="flex h-topbar shrink-0 items-center justify-between border-b border-border px-4">
                <span class="text-lg font-semibold text-text-primary">{{ __('storefront.nav.brand') }}</span>
                <button type="button" x-on:click="mobileMenuOpen = false" class="text-text-muted" aria-label="{{ __('storefront.nav.close_menu') }}">✕</button>
            </div>
            <nav class="flex-1 px-3 py-4">
                <p class="mb-2 px-1 text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('storefront.nav.catalog_menu_label') }}</p>
                @forelse (($navCategories ?? collect()) as $navCategory)
                    <a
                        href="{{ route('storefront.catalog.show', ['categorySlug' => $navCategory->slug[app()->getLocale()] ?? '']) }}"
                        wire:navigate
                        x-on:click="mobileMenuOpen = false"
                        class="block rounded-lg px-3 py-2.5 text-sm text-text-secondary hover:bg-surface-hover hover:text-text-primary"
                    >{{ $navCategory->name[app()->getLocale()] ?? '' }} <span class="text-xs text-text-muted">({{ $navCategory->approved_products_count ?? 0 }})</span></a>
                @empty
                    <p class="px-3 text-sm text-text-muted">{{ __('storefront.nav.no_categories') }}</p>
                @endforelse

                <div class="mt-4 border-t border-border pt-4">
                    <nav aria-label="{{ __('storefront.nav.language') }}" class="flex items-center gap-2 px-3 text-sm">
                        @foreach (['uz' => 'UZ', 'ru' => 'RU', 'en' => 'EN'] as $locale => $label)
                            <a
                                href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}"
                                class="rounded-sm px-2 py-1 {{ app()->getLocale() === $locale ? 'font-semibold text-accent-600' : 'text-text-muted' }}"
                            >{{ $label }}</a>
                        @endforeach
                    </nav>
                </div>

                <div class="mt-4 border-t border-border pt-4">
                    <a href="{{ route('sellers.register') }}" wire:navigate class="block rounded-lg px-3 py-2.5 text-sm font-medium text-accent-700 hover:bg-surface-hover">{{ __('storefront.nav.become_seller') }}</a>
                </div>
            </nav>
        </aside>

        {{-- Mobile search overlay --}}
        <div
            x-show="mobileSearchOpen"
            x-cloak
            x-transition
            class="fixed inset-x-0 top-0 z-modal border-b border-border bg-surface p-3 md:hidden"
        >
            <form action="/search" method="GET" class="flex items-center gap-2">
                <div class="relative flex-1">
                    <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-text-muted" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <circle cx="9" cy="9" r="5.5" stroke="currentColor" stroke-width="1.4" />
                        <path d="M17 17l-3.5-3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    </svg>
                    <input
                        type="search"
                        name="q"
                        autofocus
                        placeholder="{{ __('storefront.nav.search_placeholder') }}"
                        class="h-9 w-full rounded-lg border border-border bg-surface pr-3 pl-9 text-sm text-text-primary focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none"
                    >
                </div>
                <button type="button" x-on:click="mobileSearchOpen = false" class="shrink-0 text-sm text-text-secondary" aria-label="{{ __('storefront.nav.close_menu') }}">✕</button>
            </form>
        </div>

        {{-- Page content --}}
        <main class="mx-auto max-w-7xl px-4 py-8 md:px-6 md:py-10">
            {{ $slot }}
        </main>

        {{-- Footer — deliberately minimal, no account/order-history links (buyers never register). See doc 09. --}}
        <footer class="border-t border-border bg-surface-subtle">
            <div class="mx-auto max-w-7xl px-4 py-10 md:px-6">
                @php
                    $hasContactInfo = $settings->admin_phone || $settings->admin_email;
                @endphp
                <div class="grid grid-cols-1 gap-8 {{ $hasContactInfo ? 'sm:grid-cols-4' : 'sm:grid-cols-3' }}">
                    <div>
                        <a href="{{ route('storefront.home') }}" wire:navigate class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-accent-600 text-sm font-semibold text-white">R</span>
                            <span class="text-lg font-semibold text-text-primary">{{ __('storefront.nav.brand') }}</span>
                        </a>
                        <p class="mt-3 max-w-xs text-sm text-text-secondary">{{ __('storefront.footer.tagline') }}</p>
                        <nav aria-label="{{ __('storefront.nav.language') }}" class="mt-4 flex items-center gap-2 text-sm">
                            @foreach (['uz' => 'UZ', 'ru' => 'RU', 'en' => 'EN'] as $locale => $label)
                                <a
                                    href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}"
                                    class="rounded-sm px-1.5 py-1 {{ app()->getLocale() === $locale ? 'font-semibold text-accent-600' : 'text-text-muted hover:text-text-secondary' }}"
                                >{{ $label }}</a>
                            @endforeach
                        </nav>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-text-primary">{{ __('storefront.footer.catalog_heading') }}</p>
                        <ul class="mt-3 space-y-2">
                            @forelse (($navCategories ?? collect()) as $navCategory)
                                <li>
                                    <a
                                        href="{{ route('storefront.catalog.show', ['categorySlug' => $navCategory->slug[app()->getLocale()] ?? '']) }}"
                                        wire:navigate
                                        class="text-sm text-text-secondary hover:text-accent-700"
                                    >{{ $navCategory->name[app()->getLocale()] ?? '' }}</a>
                                </li>
                            @empty
                                <li class="text-sm text-text-muted">{{ __('storefront.nav.no_categories') }}</li>
                            @endforelse
                            <li><a href="{{ route('storefront.articles.index') }}" wire:navigate class="text-sm text-text-secondary hover:text-accent-700">{{ __('storefront.articles.title') }}</a></li>
                        </ul>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-text-primary">{{ __('storefront.footer.sellers_heading') }}</p>
                        <ul class="mt-3 space-y-2">
                            <li><a href="{{ route('sellers.register') }}" wire:navigate class="text-sm text-text-secondary hover:text-accent-700">{{ __('storefront.nav.become_seller') }}</a></li>
                            <li><a href="{{ route('login') }}" wire:navigate class="text-sm text-text-secondary hover:text-accent-700">{{ __('storefront.nav.seller_login') }}</a></li>
                        </ul>
                    </div>

                    @if ($hasContactInfo)
                        <div>
                            <p class="text-sm font-semibold text-text-primary">{{ __('storefront.footer.contact_heading') }}</p>
                            <ul class="mt-3 space-y-2">
                                @if ($settings->admin_phone)
                                    <li><a href="tel:{{ $settings->admin_phone }}" class="text-sm text-text-secondary hover:text-accent-700">{{ $settings->admin_phone }}</a></li>
                                @endif
                                @if ($settings->admin_email)
                                    <li><a href="mailto:{{ $settings->admin_email }}" class="text-sm text-text-secondary hover:text-accent-700">{{ $settings->admin_email }}</a></li>
                                @endif
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="mt-8 border-t border-border pt-6">
                    <p class="text-xs text-text-muted">{{ __('storefront.footer.copyright', ['year' => now()->year]) }}</p>
                </div>
            </div>
        </footer>

        @livewireScripts
    </body>
</html>
