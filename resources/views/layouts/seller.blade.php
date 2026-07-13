<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ isset($title) ? $title.' · Ribbon Seller' : 'Ribbon Seller' }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="panel-seller bg-surface text-text-primary text-sm antialiased" x-data="{ mobileNavOpen: false }">
        @php
            // This layout is only ever reached through the `seller.auth`
            // middleware (see EnsureSellerIsAuthenticated), which already
            // guarantees Auth::user() is set AND linked to an `approved`
            // Seller — calling sellerOrFail() directly here is therefore
            // safe and won't throw for anyone who actually renders this.
            $sellerCompany = Auth::user()->sellerOrFail();
        @endphp

        <div class="flex h-screen overflow-hidden">
            {{-- Sidebar (desktop) --}}
            <aside class="hidden lg:flex lg:flex-col w-sidebar shrink-0 border-r border-border bg-surface-subtle">
                <div class="flex h-topbar items-center gap-2 border-b border-border px-4">
                    <span class="flex h-6 w-6 items-center justify-center rounded-sm bg-accent-600 text-xs font-semibold text-white">R</span>
                    <span class="text-sm font-semibold text-text-primary">{{ __('sellers.nav.brand') }}</span>
                </div>
                <nav class="flex-1 overflow-y-auto px-2 py-4">
                    <p class="mb-2 px-3 text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('sellers.nav.section_overview') }}</p>
                    <a
                        href="{{ route('seller.dashboard') }}"
                        wire:navigate
                        title="{{ __('sellers.nav.dashboard') }}"
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm transition-colors {{ request()->routeIs('seller.dashboard') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M3 10.5 10 4l7 6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M5 9v6.5A1.5 1.5 0 0 0 6.5 17h7a1.5 1.5 0 0 0 1.5-1.5V9" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                        </svg>
                        <span>{{ __('sellers.nav.dashboard') }}</span>
                    </a>

                    <p class="mt-4 mb-2 px-3 text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('sellers.nav.section_catalog') }}</p>
                    <a
                        href="{{ route('seller.products.index') }}"
                        wire:navigate
                        title="{{ __('sellers.nav.products') }}"
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm transition-colors {{ request()->routeIs('seller.products.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect x="2.5" y="4" width="15" height="12" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                            <path d="M2.5 8h15" stroke="currentColor" stroke-width="1.4" />
                        </svg>
                        <span>{{ __('sellers.nav.products') }}</span>
                    </a>
                    <a
                        href="{{ route('seller.analytics') }}"
                        wire:navigate
                        title="{{ __('sellers.nav.analytics') }}"
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm transition-colors {{ request()->routeIs('seller.analytics') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M3 16.5V7l4.5 3.5L12 5l5 5.5v6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>{{ __('sellers.nav.analytics') }}</span>
                    </a>

                    <p class="mt-4 mb-2 px-3 text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('sellers.nav.section_company') }}</p>
                    <a
                        href="{{ route('seller.profile') }}"
                        wire:navigate
                        title="{{ __('sellers.nav.profile') }}"
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm transition-colors {{ request()->routeIs('seller.profile') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect x="3" y="4" width="14" height="12" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                            <circle cx="7.5" cy="8.5" r="1.5" stroke="currentColor" stroke-width="1.4" />
                            <path d="M11 8h4M11 11h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                        </svg>
                        <span>{{ __('sellers.nav.profile') }}</span>
                    </a>

                    @if (Auth::user()->isOwnerOf($sellerCompany))
                        <p class="mt-4 mb-2 px-3 text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('sellers.nav.section_team') }}</p>
                        <a
                            href="{{ route('seller.employees') }}"
                            wire:navigate
                            title="{{ __('sellers.nav.employees') }}"
                            class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm transition-colors {{ request()->routeIs('seller.employees') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}"
                        >
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <circle cx="7.5" cy="7" r="2.5" stroke="currentColor" stroke-width="1.4" />
                                <path d="M3 16c0-2.5 2-4 4.5-4S12 13.5 12 16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                <circle cx="14" cy="7.5" r="2" stroke="currentColor" stroke-width="1.4" />
                                <path d="M13 12.2c2 .2 3.5 1.6 3.5 3.8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                            </svg>
                            <span>{{ __('sellers.nav.employees') }}</span>
                        </a>
                    @endif
                </nav>
                <div class="border-t border-border px-3 py-3">
                    <p class="truncate text-xs font-medium text-text-primary">{{ $sellerCompany->name }}</p>
                    <p class="text-xs text-text-muted">{{ __('sellers.nav.company_panel_label') }}</p>
                </div>
            </aside>

            {{-- Sidebar (mobile off-canvas) --}}
            <div
                x-show="mobileNavOpen"
                x-cloak
                class="fixed inset-0 z-modal-backdrop bg-slate-900/40 lg:hidden"
                x-on:click="mobileNavOpen = false"
            ></div>
            <aside
                x-show="mobileNavOpen"
                x-cloak
                x-transition
                class="fixed inset-y-0 left-0 z-modal flex w-sidebar flex-col border-r border-border bg-surface-subtle lg:hidden"
            >
                <div class="flex h-topbar items-center justify-between border-b border-border px-4">
                    <span class="text-sm font-semibold text-text-primary">{{ __('sellers.nav.brand') }}</span>
                    <button type="button" x-on:click="mobileNavOpen = false" class="text-text-muted" aria-label="{{ __('sellers.nav.close_menu') }}">✕</button>
                </div>
                <nav class="flex-1 overflow-y-auto px-2 py-4">
                    <a
                        href="{{ route('seller.dashboard') }}"
                        wire:navigate
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm {{ request()->routeIs('seller.dashboard') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary' }}"
                    >
                        {{ __('sellers.nav.dashboard') }}
                    </a>
                    <a
                        href="{{ route('seller.products.index') }}"
                        wire:navigate
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm {{ request()->routeIs('seller.products.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary' }}"
                    >
                        {{ __('sellers.nav.products') }}
                    </a>
                    <a
                        href="{{ route('seller.analytics') }}"
                        wire:navigate
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm {{ request()->routeIs('seller.analytics') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary' }}"
                    >
                        {{ __('sellers.nav.analytics') }}
                    </a>
                    <a
                        href="{{ route('seller.profile') }}"
                        wire:navigate
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm {{ request()->routeIs('seller.profile') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary' }}"
                    >
                        {{ __('sellers.nav.profile') }}
                    </a>
                    @if (Auth::user()->isOwnerOf($sellerCompany))
                        <a
                            href="{{ route('seller.employees') }}"
                            wire:navigate
                            class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm {{ request()->routeIs('seller.employees') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary' }}"
                        >
                            {{ __('sellers.nav.employees') }}
                        </a>
                    @endif
                </nav>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                {{-- Topbar --}}
                <header class="sticky top-0 z-sticky flex h-topbar shrink-0 items-center justify-between border-b border-border bg-surface px-4 lg:px-6">
                    <div class="flex items-center gap-3">
                        <button type="button" class="text-text-secondary lg:hidden" x-on:click="mobileNavOpen = true" aria-label="{{ __('sellers.nav.open_menu') }}">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                        </button>
                        <nav class="flex items-center gap-1.5 text-sm" aria-label="Breadcrumb">
                            @foreach (($breadcrumb ?? [['label' => __('sellers.nav.dashboard')]]) as $crumb)
                                @if (! $loop->last)
                                    @if (! empty($crumb['url']))
                                        <a href="{{ $crumb['url'] }}" wire:navigate class="text-text-secondary hover:text-text-primary">{{ $crumb['label'] }}</a>
                                    @else
                                        <span class="text-text-secondary">{{ $crumb['label'] }}</span>
                                    @endif
                                    <span class="text-text-muted">/</span>
                                @else
                                    <span class="font-medium text-text-primary">{{ $crumb['label'] }}</span>
                                @endif
                            @endforeach
                        </nav>
                    </div>

                    <div class="flex items-center gap-3">
                        {{-- Identity strip: the seller's own moderation status,
                             persistently visible per design doc 02 — reaching
                             this layout at all already implies `approved`
                             (seller.auth gates on it), but the badge stays
                             here so the pattern holds if that ever changes. --}}
                        @php
                            $sellerStatusVariant = match ($sellerCompany->status) {
                                'approved' => 'success',
                                'pending' => 'warning',
                                'rejected' => 'danger',
                                'suspended' => 'muted',
                                default => 'muted',
                            };
                        @endphp
                        <x-badge :variant="$sellerStatusVariant" dot class="hidden sm:inline-flex">
                            {{ __('sellers.status.'.$sellerCompany->status) }}
                        </x-badge>

                        <x-dropdown align="right">
                            <x-slot:trigger>
                                <button type="button" class="flex items-center gap-2 rounded-sm px-1.5 py-1 hover:bg-surface-hover">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-accent-100 text-sm font-medium text-accent-700">
                                        {{ \Illuminate\Support\Str::of(Auth::user()->name)->substr(0, 1)->upper() }}
                                    </span>
                                    <span class="hidden text-left sm:block">
                                        <span class="block text-sm font-medium text-text-primary">{{ Auth::user()->name }}</span>
                                        <span class="block text-xs text-text-muted">{{ $sellerCompany->name }}</span>
                                    </span>
                                    <svg class="h-4 w-4 text-text-muted" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </x-slot:trigger>

                            <div class="border-b border-border px-3 py-2 sm:hidden">
                                <p class="text-sm font-medium text-text-primary">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-text-muted">{{ $sellerCompany->name }}</p>
                            </div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-text-secondary hover:bg-surface-hover hover:text-text-primary">
                                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M8 4H5.5A1.5 1.5 0 0 0 4 5.5v9A1.5 1.5 0 0 0 5.5 16H8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                        <path d="M12.5 13.5 16 10l-3.5-3.5M16 10H7.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    {{ __('sellers.nav.logout') }}
                                </button>
                            </form>
                        </x-dropdown>
                    </div>
                </header>

                <main class="flex-1 overflow-y-auto">
                    <div class="px-4 py-6 lg:px-6">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
