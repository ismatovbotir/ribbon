{{-- Seller navigation, defined once (sidebar + drawer). Employees: Owner only (existing gate, unchanged). --}}
@php($seller = Auth::user()->sellerOrFail())

<x-panel.nav-group :label="__('sellers.nav.section_overview')" :first="true" />
<x-panel.nav-item :href="route('seller.dashboard')" :active="request()->routeIs('seller.dashboard')" :label="__('sellers.nav.dashboard')" icon="dashboard" />

<x-panel.nav-group :label="__('sellers.nav.section_catalog')" />
<x-panel.nav-item :href="route('seller.products.index')" :active="request()->routeIs('seller.products.*')" :label="__('sellers.nav.products')" icon="table" />
<x-panel.nav-item :href="route('seller.analytics')" :active="request()->routeIs('seller.analytics')" :label="__('sellers.nav.analytics')" icon="chart" />

<x-panel.nav-group :label="__('sellers.nav.section_company')" />
<x-panel.nav-item :href="route('seller.profile')" :active="request()->routeIs('seller.profile')" :label="__('sellers.nav.profile')" icon="building" />

@if (Auth::user()->isOwnerOf($seller))
    <x-panel.nav-group :label="__('sellers.nav.section_team')" />
    <x-panel.nav-item :href="route('seller.employees')" :active="request()->routeIs('seller.employees')" :label="__('sellers.nav.employees')" icon="users" />
@endif
