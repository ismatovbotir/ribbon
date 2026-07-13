<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ isset($title) ? $title.' · Ribbon Admin' : 'Ribbon Admin' }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="panel-admin bg-surface text-text-primary text-sm antialiased" x-data="{ mobileNavOpen: false }">
        @php
            // This layout is only ever reached through the `admin.auth`
            // middleware (see EnsureAdminIsAuthenticated), which already
            // guarantees Auth::user() is set AND holds an admin role —
            // calling adminRoleOrFail() directly here is therefore safe and
            // won't throw for anyone who actually renders this.
            $adminRole = Auth::user()->adminRoleOrFail();
        @endphp

        <div class="flex h-screen overflow-hidden">
            {{-- Sidebar (desktop) --}}
            <aside class="hidden lg:flex lg:flex-col w-sidebar shrink-0 border-r border-border bg-surface-subtle">
                <div class="flex h-topbar items-center gap-2 border-b border-border px-4">
                    <span class="flex h-6 w-6 items-center justify-center rounded-sm bg-accent-600 text-xs font-semibold text-white">R</span>
                    <span class="text-sm font-semibold text-text-primary">Ribbon Admin</span>
                </div>
                <nav class="flex-1 overflow-y-auto px-2 py-4">
                    <p class="mb-2 px-3 text-xs font-medium tracking-wide text-text-muted uppercase">Catalog</p>
                    <a
                        href="{{ route('admin.categories.index') }}"
                        wire:navigate
                        title="Categories"
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm transition-colors {{ request()->routeIs('admin.categories.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M3 5.5A1.5 1.5 0 0 1 4.5 4h4A1.5 1.5 0 0 1 10 5.5v4A1.5 1.5 0 0 1 8.5 11h-4A1.5 1.5 0 0 1 3 9.5v-4Z" stroke="currentColor" stroke-width="1.4" />
                            <path d="M10 14.5A1.5 1.5 0 0 1 11.5 13h4a1.5 1.5 0 0 1 1.5 1.5v0A1.5 1.5 0 0 1 15.5 16h-4a1.5 1.5 0 0 1-1.5-1.5v0Z" stroke="currentColor" stroke-width="1.4" />
                            <path d="M3 14.5A1.5 1.5 0 0 1 4.5 13h2A1.5 1.5 0 0 1 8 14.5v0A1.5 1.5 0 0 1 6.5 16h-2A1.5 1.5 0 0 1 3 14.5v0Z" stroke="currentColor" stroke-width="1.4" />
                            <path d="M13 5.5A1.5 1.5 0 0 1 14.5 4h1A1.5 1.5 0 0 1 17 5.5v4a1.5 1.5 0 0 1-1.5 1.5h-1A1.5 1.5 0 0 1 13 9.5v-4Z" stroke="currentColor" stroke-width="1.4" />
                        </svg>
                        <span>Categories</span>
                    </a>
                    <a
                        href="{{ route('admin.brands.index') }}"
                        wire:navigate
                        title="Brands"
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm transition-colors {{ request()->routeIs('admin.brands.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M10 3l6 3v4c0 4-2.5 6.5-6 7-3.5-.5-6-3-6-7V6l6-3Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                        </svg>
                        <span>Brands</span>
                    </a>
                    <a
                        href="{{ route('admin.geography.countries.index') }}"
                        wire:navigate
                        title="Geography"
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm transition-colors {{ request()->routeIs('admin.geography.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M10 17.5S16 12.5 16 8a6 6 0 1 0-12 0c0 4.5 6 9.5 6 9.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                            <circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.4" />
                        </svg>
                        <span>Geography</span>
                    </a>

                    <p class="mt-4 mb-2 px-3 text-xs font-medium tracking-wide text-text-muted uppercase">Moderation</p>
                    <a
                        href="{{ route('admin.sellers.index') }}"
                        wire:navigate
                        title="Sellers"
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm transition-colors {{ request()->routeIs('admin.sellers.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M4 8l6-4.5L16 8v7a1 1 0 0 1-1 1h-2.5v-4.5h-5V16H5a1 1 0 0 1-1-1V8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                        </svg>
                        <span>Sellers</span>
                    </a>
                    <a
                        href="{{ route('admin.products.index') }}"
                        wire:navigate
                        title="Products"
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm transition-colors {{ request()->routeIs('admin.products.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M3 6.5L10 3l7 3.5-7 3.5-7-3.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                            <path d="M3 6.5V13l7 3.5 7-3.5V6.5" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                            <path d="M10 10v6.5" stroke="currentColor" stroke-width="1.4" />
                        </svg>
                        <span>Products</span>
                    </a>
                    {{-- Buyers' raw contact details live behind this link — Super
                         Admin only, see EnsureUserIsSuperAdmin. Hidden rather
                         than shown-then-403'd for any lesser admin role. --}}
                    @if (Auth::user()->isSuperAdmin())
                        <a
                            href="{{ route('admin.offers.index') }}"
                            wire:navigate
                            title="Commercial Offers"
                            class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm transition-colors {{ request()->routeIs('admin.offers.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}"
                        >
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M4 4.5h9l3 3V15a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5.5a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                                <path d="M6.5 8h5M6.5 11h3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                            </svg>
                            <span>Commercial Offers</span>
                        </a>
                    @endif

                    <p class="mt-4 mb-2 px-3 text-xs font-medium tracking-wide text-text-muted uppercase">Content</p>
                    <a
                        href="{{ route('admin.banners.index') }}"
                        wire:navigate
                        title="Banners"
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm transition-colors {{ request()->routeIs('admin.banners.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect x="2.5" y="5" width="15" height="10" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                            <path d="M2.5 12.5l4-3.5 3 2.5 3.5-4 4.5 5" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                        </svg>
                        <span>Banners</span>
                    </a>
                </nav>
                <div class="border-t border-border px-3 py-3 text-xs text-text-muted">
                    No admin auth yet — this panel is unprotected.
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
                    <span class="text-sm font-semibold text-text-primary">Ribbon Admin</span>
                    <button type="button" x-on:click="mobileNavOpen = false" class="text-text-muted">✕</button>
                </div>
                <nav class="flex-1 overflow-y-auto px-2 py-4">
                    <a
                        href="{{ route('admin.categories.index') }}"
                        wire:navigate
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm {{ request()->routeIs('admin.categories.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary' }}"
                    >
                        Categories
                    </a>
                    <a
                        href="{{ route('admin.brands.index') }}"
                        wire:navigate
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm {{ request()->routeIs('admin.brands.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary' }}"
                    >
                        Brands
                    </a>
                    <a
                        href="{{ route('admin.geography.countries.index') }}"
                        wire:navigate
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm {{ request()->routeIs('admin.geography.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary' }}"
                    >
                        Geography
                    </a>
                    <a
                        href="{{ route('admin.sellers.index') }}"
                        wire:navigate
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm {{ request()->routeIs('admin.sellers.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary' }}"
                    >
                        Sellers
                    </a>
                    <a
                        href="{{ route('admin.products.index') }}"
                        wire:navigate
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm {{ request()->routeIs('admin.products.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary' }}"
                    >
                        Products
                    </a>
                    @if (Auth::user()->isSuperAdmin())
                        <a
                            href="{{ route('admin.offers.index') }}"
                            wire:navigate
                            class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm {{ request()->routeIs('admin.offers.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary' }}"
                        >
                            Commercial Offers
                        </a>
                    @endif
                    <a
                        href="{{ route('admin.banners.index') }}"
                        wire:navigate
                        class="flex h-row-comfortable items-center gap-2.5 rounded-sm border-l-2 px-3 text-sm {{ request()->routeIs('admin.banners.*') ? 'border-accent-600 bg-accent-50 text-accent-700 font-medium' : 'border-transparent text-text-secondary' }}"
                    >
                        Banners
                    </a>
                </nav>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                {{-- Topbar --}}
                <header class="sticky top-0 z-sticky flex h-topbar shrink-0 items-center justify-between border-b border-border bg-surface px-4 lg:px-6">
                    <div class="flex items-center gap-3">
                        <button type="button" class="text-text-secondary lg:hidden" x-on:click="mobileNavOpen = true" aria-label="Open navigation">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                        </button>
                        <nav class="flex items-center gap-1.5 text-sm" aria-label="Breadcrumb">
                            @foreach (($breadcrumb ?? []) as $crumb)
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
                        <x-dropdown align="right">
                            <x-slot:trigger>
                                <button type="button" class="flex items-center gap-2 rounded-sm px-1.5 py-1 hover:bg-surface-hover">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-accent-100 text-sm font-medium text-accent-700">
                                        {{ \Illuminate\Support\Str::of(Auth::user()->name)->substr(0, 1)->upper() }}
                                    </span>
                                    <span class="hidden text-left sm:block">
                                        <span class="block text-sm font-medium text-text-primary">{{ Auth::user()->name }}</span>
                                        <span class="block text-xs text-text-muted">{{ $adminRole->name }}</span>
                                    </span>
                                    <svg class="h-4 w-4 text-text-muted" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </x-slot:trigger>

                            <div class="border-b border-border px-3 py-2 sm:hidden">
                                <p class="text-sm font-medium text-text-primary">{{ Auth::user()->name }}</p>
                                <p class="text-xs text-text-muted">{{ $adminRole->name }}</p>
                            </div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-text-secondary hover:bg-surface-hover hover:text-text-primary">
                                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M8 4H5.5A1.5 1.5 0 0 0 4 5.5v9A1.5 1.5 0 0 0 5.5 16H8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                        <path d="M12.5 13.5 16 10l-3.5-3.5M16 10H7.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    Log out
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
