@props(['products'])

{{--
    Horizontal scroll-snap rail — home page "recently added" section only
    (see docs/design/13-storefront-visual-polish.md §3). Not used on
    catalog/search, which need exhaustive paginated grids instead.
--}}
<div class="-mx-4 flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:-mx-6 md:px-6">
    @foreach ($products as $product)
        <div class="w-48 shrink-0 snap-start sm:w-56 lg:w-64" wire:key="rail-product-{{ $product->id }}">
            <x-storefront.product-card :product="$product" :show-category="true" />
        </div>
    @endforeach
</div>
