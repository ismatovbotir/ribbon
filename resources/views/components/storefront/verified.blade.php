@props(['labelled' => false])

{{-- Verified seller (spec 20 8.1): compact = outline shield icon; labelled = cobalt chip with visible text. --}}
@if ($labelled)
    <span {{ $attributes->class(['inline-flex h-6 items-center gap-1 rounded-sm border border-cobalt-200 bg-cobalt-50 px-2 text-xs font-medium text-cobalt-800']) }}>
        <x-storefront.icon name="security" class="size-4" stroke="2" />
        {{ __('storefront.seller.verified') }}
    </span>
@else
    <span {{ $attributes->class(['inline-flex shrink-0 items-center text-accent-700']) }} title="{{ __('storefront.seller.verified') }}">
        <x-storefront.icon name="security" class="size-4" stroke="2" />
        <span class="sr-only">{{ __('storefront.seller.verified') }}</span>
    </span>
@endif
