@props(['price', 'unit', 'moq' => 1])

{{-- Quiet price (decision 9): mono, regular weight, secondary colour, always "/ unit" + MOQ. --}}
<p {{ $attributes->class(['flex flex-wrap items-baseline gap-x-2 font-mono text-sm font-normal text-text-secondary tabular-nums']) }}>
    <span>{{ number_format((float) $price, 0, '', "\u{00A0}") }} UZS<span class="text-text-muted"> / {{ __('storefront.unit.'.$unit) }}</span></span>
    <span class="text-text-muted">&middot; {{ __('storefront.moq') }} {{ $moq }} {{ __('storefront.unit.pcs') }}</span>
</p>
