{{-- Admin navigation, defined once (sidebar + drawer). Platform group omitted entirely for non-Super-Admin. --}}
@php($super = Auth::user()->isSuperAdmin())

<x-panel.nav-group label="Catalog" :first="true" />
<x-panel.nav-item :href="route('admin.categories.index')" :active="request()->routeIs('admin.categories.*')" label="Categories" icon="grid" />
<x-panel.nav-item :href="route('admin.brands.index')" :active="request()->routeIs('admin.brands.*')" label="Brands" icon="shield" />
<x-panel.nav-item :href="route('admin.geography.countries.index')" :active="request()->routeIs('admin.geography.*')" label="Geography" icon="map-pin" />

<x-panel.nav-group label="Moderation" />
<x-panel.nav-item :href="route('admin.sellers.index')" :active="request()->routeIs('admin.sellers.*')" label="Sellers" icon="store" />
<x-panel.nav-item :href="route('admin.products.index')" :active="request()->routeIs('admin.products.*')" label="Products" icon="cube" />
{{-- Buyers' raw contact details live behind this link: Super Admin only (EnsureUserIsSuperAdmin). Omitted, not disabled. --}}
@if ($super)
    <x-panel.nav-item :href="route('admin.offers.index')" :active="request()->routeIs('admin.offers.*')" label="Commercial offers" icon="document" />
@endif

<x-panel.nav-group label="Content" />
<x-panel.nav-item :href="route('admin.banners.index')" :active="request()->routeIs('admin.banners.*')" label="Banners" icon="photo" />
<x-panel.nav-item :href="route('admin.articles.index')" :active="request()->routeIs('admin.articles.*')" label="Articles" icon="article" />
<x-panel.nav-item :href="route('admin.pages.index')" :active="request()->routeIs('admin.pages.*')" label="Pages" icon="page" />

@if ($super)
    <x-panel.nav-group label="Platform" />
    <x-panel.nav-item :href="route('admin.contact-messages.index')" :active="request()->routeIs('admin.contact-messages.*')" label="Contact messages" icon="mail" />
    <x-panel.nav-item :href="route('admin.telegram.show')" :active="request()->routeIs('admin.telegram.*')" label="Telegram inbox" icon="chat" />
    <x-panel.nav-item :href="route('admin.analytics.show')" :active="request()->routeIs('admin.analytics.*')" label="Analytics" icon="chart" />
    <x-panel.nav-item :href="route('admin.settings.show')" :active="request()->routeIs('admin.settings.*')" label="Settings" icon="settings" />
@endif
