@props(['seller', 'brand' => null, 'badge' => false, 'labelled' => false])

{{-- Seller identity: plain-text name (no public seller page) + verified icon (+ brand-partner badge when approved data exists). --}}
<p {{ $attributes->class(['flex min-w-0 items-center gap-1.5 text-sm text-text-secondary']) }}>
    <span class="truncate">{{ $seller->name }}</span>
    <x-storefront.verified :labelled="$labelled" />
    @if ($badge && $brand)
        @php $partnerLabel = __('storefront.seller.partner_badge', ['brand' => $brand]); @endphp
        <x-badge variant="brand" class="hidden sm:inline-flex" title="{{ $partnerLabel }}">
            <x-storefront.icon name="certificate" class="size-4 shrink-0 text-accent-700" stroke="2" />
            <span class="min-w-0 truncate">{{ $partnerLabel }}</span>
        </x-badge>
    @endif
</p>
