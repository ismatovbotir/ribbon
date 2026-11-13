{{-- Admin navigation, defined once (sidebar + drawer). Platform group omitted entirely for non-Super-Admin. --}}
@php($super = Auth::user()->isSuperAdmin())

<x-panel.nav-group :label="__('admin.ui.catalog')" :first="true" />
<x-panel.nav-item :href="route('admin.categories.index')" :active="request()->routeIs('admin.categories.*')" :label="__('admin.ui.categories')" icon="grid" />
@if ($super)
    <x-panel.nav-item :href="route('admin.compatibility.show')" :active="request()->routeIs('admin.compatibility.*')" :label="__('admin.compatibility.nav')" icon="label-roll" />
@endif
<x-panel.nav-item :href="route('admin.brands.index')" :active="request()->routeIs('admin.brands.*')" :label="__('admin.ui.brands')" icon="shield" />
<x-panel.nav-item :href="route('admin.geography.countries.index')" :active="request()->routeIs('admin.geography.*')" :label="__('admin.ui.geography')" icon="map-pin" />

<x-panel.nav-group :label="__('admin.ui.moderation')" />
<x-panel.nav-item :href="route('admin.sellers.index')" :active="request()->routeIs('admin.sellers.*')" :label="__('admin.ui.sellers')" icon="store" />
<x-panel.nav-item :href="route('admin.products.index')" :active="request()->routeIs('admin.products.*')" :label="__('admin.ui.products')" icon="cube" />
{{-- Buyers' raw contact details live behind this link: Super Admin only (EnsureUserIsSuperAdmin). Omitted, not disabled. --}}
@if ($super)
    <x-panel.nav-item :href="route('admin.offers.index')" :active="request()->routeIs('admin.offers.*')" :label="__('admin.ui.commercial_offers')" icon="document" />
@endif

<x-panel.nav-group :label="__('admin.ui.content')" />
<x-panel.nav-item :href="route('admin.banners.index')" :active="request()->routeIs('admin.banners.*')" :label="__('admin.ui.banners')" icon="photo" />
<x-panel.nav-item :href="route('admin.articles.index')" :active="request()->routeIs('admin.articles.*')" :label="__('admin.ui.articles')" icon="article" />
<x-panel.nav-item :href="route('admin.pages.index')" :active="request()->routeIs('admin.pages.*')" :label="__('admin.ui.pages')" icon="page" />
@can('admin-permission', 'bot.manage')
    <x-panel.nav-item :href="route('admin.bot.index')" :active="request()->routeIs('admin.bot.*')" :label="__('admin.bot.nav')" icon="chat" />
@endcan

@if ($super)
    <x-panel.nav-group :label="__('admin.ui.platform')" />
    <x-panel.nav-item :href="route('admin.contact-messages.index')" :active="request()->routeIs('admin.contact-messages.*')" :label="__('admin.ui.contact_messages')" icon="mail" />
    <x-panel.nav-item :href="route('admin.telegram.show')" :active="request()->routeIs('admin.telegram.*')" :label="__('admin.ui.telegram_inbox')" icon="chat" />
    <x-panel.nav-item :href="route('admin.analytics.show')" :active="request()->routeIs('admin.analytics.*')" :label="__('admin.ui.analytics')" icon="chart" />
    <x-panel.nav-item :href="route('admin.settings.show')" :active="request()->routeIs('admin.settings.*')" :label="__('admin.ui.settings')" icon="settings" />
@endif
