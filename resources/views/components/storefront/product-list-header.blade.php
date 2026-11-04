{{-- Selection limit / throttle message from quickAdd (OfferSelectionService), all breakpoints. --}}
@error('selection')
    <p class="border-b border-border-strong bg-surface px-4 py-2 text-sm font-medium text-danger-700" role="alert">{{ $message }}</p>
@enderror
{{-- lg-only column header for the five-column product row (spec 18 5.3). Same grid template as product-row. --}}
<div class="hidden border-b border-border-strong bg-surface-band px-4 py-2 text-xs font-semibold text-text-secondary lg:grid lg:grid-cols-[6rem_minmax(0,1fr)_12rem_10rem_2.75rem] lg:gap-x-6" aria-hidden="true">
    <span class="col-start-2">{{ __('storefront.list.product') }}</span>
    <span class="col-start-3">{{ __('storefront.list.seller') }}</span>
    <span class="col-start-4">{{ __('storefront.list.price') }}</span>
</div>
