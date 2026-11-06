<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" href="/favicon.ico" sizes="any">

        <title>{{ isset($title) ? $title.' · Ribbon Seller' : 'Ribbon Seller' }}</title>

        <x-upload-limit />

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="panel-seller bg-surface text-text-primary text-sm antialiased">
        @php
            // This layout is only ever reached through the `seller.auth`
            // middleware (see EnsureSellerIsAuthenticated), which already
            // guarantees Auth::user() is set AND linked to an `approved`
            // Seller, so sellerOrFail() is safe here.
            $sellerCompany = Auth::user()->sellerOrFail();
        @endphp

        <x-panel.shell
            :tag="trim(\Illuminate\Support\Str::after(__('sellers.nav.brand'), 'Ribbon'))"
            :user-name="Auth::user()->name"
            :user-sub="$sellerCompany->name"
            :breadcrumb="$breadcrumb ?? [['label' => __('sellers.nav.dashboard')]]"
            :logout-label="__('sellers.nav.logout')"
            :open-label="__('sellers.nav.open_menu')"
            :close-label="__('sellers.nav.close_menu')"
        >
            <x-slot:nav><x-seller.nav /></x-slot:nav>
            <x-slot:sidebarFooter>
                <p class="truncate text-xs font-medium text-text-primary">{{ $sellerCompany->name }}</p>
                <p class="text-xs text-text-muted">{{ __('sellers.nav.company_panel_label') }}</p>
            </x-slot:sidebarFooter>
            {{-- The seller's own moderation status stays persistently visible (design doc 02). --}}
            <x-slot:topbarExtra>
                <span class="hidden sm:inline-flex"><x-status-badge :status="$sellerCompany->status">{{ __('sellers.status.'.$sellerCompany->status) }}</x-status-badge></span>
            </x-slot:topbarExtra>
            {{ $slot }}
        </x-panel.shell>

        @livewireScripts
    </body>
</html>
