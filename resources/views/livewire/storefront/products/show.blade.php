@php
    use Illuminate\Support\Facades\Storage;

    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];

    $categoryName = $product->category?->name[$locale] ?? ($product->category?->name[$defaultLocale] ?? '');
    $categorySlug = $product->category?->slug[$locale] ?? ($product->category?->slug[$defaultLocale] ?? null);

    $vitrinPrice = $orderedPrices->firstWhere('is_vitrin', true);
    $images = $product->images;

    $territoryParts = collect([
        $product->seller->city?->name[$locale] ?? $product->seller->city?->name[$defaultLocale] ?? null,
        $product->seller->region?->name[$locale] ?? $product->seller->region?->name[$defaultLocale] ?? null,
        $product->seller->country?->name[$locale] ?? $product->seller->country?->name[$defaultLocale] ?? null,
    ])->filter(fn ($part) => $part !== null && $part !== '')->values();
    $territoryLine = $territoryParts->implode(', ');
@endphp

{{--
    Meta description/canonical/hreflang/OG/JSON-LD are NOT pushed here —
    Storefront\Products\Show::render() already passes `metaDescription`,
    `canonicalUrl`, `hreflangAlternates`, `ogImage`, `structuredData` etc.
    straight into ->layout('layouts.storefront', [...]), which renders them
    centrally (see layouts/storefront.blade.php's generic SEO head block).
    This mirrors Storefront\Home / Storefront\Catalog\Show's exact
    established pattern — see this task's final report for the caveat that
    this was written as a frontend-engineer best-effort placeholder, not a
    real seo-engineer/geo-engineer pass.
--}}

<div x-data="{ galleryActive: 0, galleryTotal: {{ max($images->count(), 1) }} }">
    {{-- Breadcrumb --}}
    <nav aria-label="Breadcrumb" class="mb-3 flex flex-wrap items-center gap-1.5 text-sm">
        <a href="{{ route('storefront.home') }}" wire:navigate class="text-text-secondary hover:text-text-primary">{{ __('storefront.catalog.breadcrumb_home') }}</a>
        <span class="text-text-muted" aria-hidden="true">/</span>
        @if ($categorySlug)
            <a href="{{ route('storefront.catalog.show', ['categorySlug' => $categorySlug]) }}" wire:navigate class="text-text-secondary hover:text-text-primary">{{ $categoryName }}</a>
        @else
            <span class="text-text-secondary">{{ $categoryName }}</span>
        @endif
        <span class="text-text-muted" aria-hidden="true">/</span>
        <span class="line-clamp-1 font-medium text-text-primary">{{ $displayName }}</span>
    </nav>

    <div class="lg:grid lg:grid-cols-2 lg:gap-10">
        {{-- Gallery (left column) --}}
        <div>
            <div class="relative aspect-square overflow-hidden rounded-xl bg-surface-subtle">
                @forelse ($images as $image)
                    <img
                        src="{{ Storage::disk('public')->url($image->path) }}"
                        alt="{{ $displayName }}"
                        class="absolute inset-0 h-full w-full object-contain p-3"
                        x-show="galleryActive === {{ $loop->index }}"
                        loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                    >
                @empty
                    {{-- 0-image state: a valid, expected state per doc 12 — never a broken-image icon or empty frame. --}}
                    <div class="flex h-full w-full flex-col items-center justify-center gap-2">
                        <svg class="h-16 w-16 text-text-muted" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect x="3.5" y="7" width="17" height="13" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                            <path d="M3.5 7 12 3l8.5 4" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                            <path d="M8 11.5h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                        </svg>
                        <span class="text-sm text-text-muted">{{ __('storefront.product_detail.gallery_no_image') }}</span>
                    </div>
                @endforelse

                @if ($images->count() > 1)
                    <button
                        type="button"
                        x-on:click="galleryActive = (galleryActive - 1 + galleryTotal) % galleryTotal"
                        aria-label="{{ __('storefront.product_detail.gallery_prev') }}"
                        class="absolute top-1/2 left-2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-surface/90 text-text-secondary hover:text-text-primary"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 5l-5 5 5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                    <button
                        type="button"
                        x-on:click="galleryActive = (galleryActive + 1) % galleryTotal"
                        aria-label="{{ __('storefront.product_detail.gallery_next') }}"
                        class="absolute top-1/2 right-2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border border-border bg-surface/90 text-text-secondary hover:text-text-primary"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M8 5l5 5-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                @endif
            </div>

            @if ($images->count() > 1)
                <div class="mt-3 flex gap-2 overflow-x-auto">
                    @foreach ($images as $image)
                        <button
                            type="button"
                            x-on:click="galleryActive = {{ $loop->index }}"
                            class="h-16 w-16 shrink-0 overflow-hidden rounded-md border p-1"
                            x-bind:class="galleryActive === {{ $loop->index }} ? 'border-accent-600 ring-1 ring-accent-600' : 'border-border'"
                            aria-label="{{ __('storefront.product_detail.gallery_thumbnail', ['n' => $loop->iteration]) }}"
                        >
                            <img src="{{ Storage::disk('public')->url($image->path) }}" alt="" class="h-full w-full object-contain">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Info column (right) --}}
        <div class="mt-6 lg:mt-0">
            <h1 class="text-2xl font-bold tracking-tight text-text-primary">{{ $displayName }}</h1>

            @if ($product->brand && $product->brand->id !== 1)
                <div class="mt-2 flex items-center gap-2">
                    @if ($product->brand->logo_path)
                        <img src="{{ Storage::disk('public')->url($product->brand->logo_path) }}" alt="" class="h-6 w-6 rounded-lg border border-border object-contain">
                    @endif
                    <span class="text-sm text-text-secondary">{{ $product->brand->name }}</span>
                </div>
            @endif

            @if ($categoryName !== '')
                <p class="mt-1 text-sm text-text-secondary">
                    {{ __('storefront.product_detail.in_category_prefix') }}
                    @if ($categorySlug)
                        <a href="{{ route('storefront.catalog.show', ['categorySlug' => $categorySlug]) }}" wire:navigate class="font-medium text-accent-700 hover:underline">{{ $categoryName }}</a>
                    @else
                        {{ $categoryName }}
                    @endif
                </p>
            @endif

            {{--
                Price comparison — every enabled unit, not just the vitrin
                one (CLAUDE.md: serious B2B buyers compare units). Real
                `<table>` markup (not a div grid) so an answer engine can
                extract unit/qty/price/per-unit-price as unambiguous
                name-value facts without inferring structure from layout —
                every cell is always filled (qty_in_pcs is 1 for the `pcs`
                row itself, so "price per pc" there is just the row's own
                price restated, not blank/dashed) so nothing requires visual
                inference to read correctly out of context.
            --}}
            <div class="mt-8 overflow-hidden rounded-xl border border-border bg-surface-raised">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[420px] text-sm">
                        <caption class="sr-only">{{ __('storefront.product_detail.price_table_caption') }}</caption>
                        <thead>
                            <tr class="border-b border-border bg-surface-subtle text-left text-xs font-medium text-text-secondary">
                                <th scope="col" class="p-4">{{ __('storefront.product_detail.price_col_unit') }}</th>
                                <th scope="col" class="p-4">{{ __('storefront.product_detail.price_col_qty') }}</th>
                                <th scope="col" class="p-4 text-right">{{ __('storefront.product_detail.price_col_price') }}</th>
                                <th scope="col" class="p-4 text-right">{{ __('storefront.product_detail.price_col_per_unit') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orderedPrices as $price)
                                <tr class="border-b border-border last:border-b-0 {{ $price->is_vitrin ? 'bg-accent-50' : '' }}">
                                    <th scope="row" class="p-4 text-left font-medium text-text-primary">
                                        <span class="inline-flex items-center gap-2">
                                            {{ __('storefront.unit.'.$price->unit) }}
                                            @if ($price->is_vitrin)
                                                <span class="rounded-full bg-accent-50 px-2 py-0.5 text-xs font-medium text-accent-700">{{ __('storefront.product_detail.default_unit_tag') }}</span>
                                            @endif
                                        </span>
                                    </th>
                                    <td class="p-4 whitespace-nowrap text-text-secondary">{{ number_format($price->qty_in_pcs) }} {{ __('storefront.unit.pcs') }}</td>
                                    <td class="p-4 text-right font-bold tabular-nums whitespace-nowrap text-text-primary {{ $price->is_vitrin ? 'text-xl' : 'text-base' }}">{{ number_format((float) $price->price) }} UZS</td>
                                    <td class="p-4 text-right tabular-nums whitespace-nowrap text-text-secondary">{{ number_format((float) $price->price / max($price->qty_in_pcs, 1)) }} UZS/{{ __('storefront.unit.pcs') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Unit selector --}}
            <div class="mt-6">
                <p class="text-sm font-medium text-text-secondary">{{ __('storefront.product_detail.unit_selector_label') }}</p>
                <div class="mt-2 inline-flex rounded-full border border-border p-1" role="radiogroup" aria-label="{{ __('storefront.product_detail.unit_selector_label') }}">
                    @foreach ($orderedPrices as $price)
                        <button
                            type="button"
                            wire:click="selectUnit('{{ $price->unit }}')"
                            role="radio"
                            aria-checked="{{ $selectedUnit === $price->unit ? 'true' : 'false' }}"
                            class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors {{ $selectedUnit === $price->unit ? 'bg-accent-600 text-white' : 'text-text-secondary hover:text-text-primary' }}"
                        >
                            {{ __('storefront.unit.'.$price->unit) }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Quantity stepper --}}
            <div class="mt-5 flex items-center gap-3">
                <label for="product-qty" class="text-sm font-medium text-text-secondary">{{ __('storefront.product_detail.qty_label') }}</label>
                <div class="flex items-center rounded-lg border border-border">
                    <button type="button" wire:click="decrementQty" aria-label="{{ __('storefront.product_detail.qty_decrease') }}" class="flex h-10 w-10 items-center justify-center text-lg text-text-secondary hover:text-text-primary">&minus;</button>
                    <input id="product-qty" type="number" min="1" step="1" wire:model.live="qty" class="h-10 w-16 border-x border-border bg-surface text-center text-sm tabular-nums text-text-primary focus:outline-none">
                    <button type="button" wire:click="incrementQty" aria-label="{{ __('storefront.product_detail.qty_increase') }}" class="flex h-10 w-10 items-center justify-center text-lg text-text-secondary hover:text-text-primary">&plus;</button>
                </div>
            </div>

            {{--
                Add to request — the single most prominent action on the
                page. See addToRequest()'s docblock for the placeholder-shape
                rationale. `x-ref` deliberately lives on this plain wrapper
                (not the same element as `x-data` below) so it registers on
                the page-root Alpine scope (declared on this view's outermost
                element) rather than this button's own isolated scope — the
                mobile sticky bar further down needs to read this ref from a
                sibling scope, which only works if it's attached to a shared
                ancestor.
            --}}
            <div class="mt-6" x-ref="addToRequestAnchor">
                <button
                    type="button"
                    wire:click="addToRequest"
                    wire:loading.attr="disabled"
                    wire:target="addToRequest"
                    x-data="{ added: false }"
                    x-on:added-to-request.window="added = true; setTimeout(() => added = false, 1500)"
                    class="flex h-12 w-full items-center justify-center rounded-lg bg-accent-600 px-8 text-lg font-semibold text-white transition-colors hover:bg-accent-700 disabled:cursor-not-allowed disabled:opacity-70 sm:w-auto"
                >
                    <span x-show="!added">{{ __('storefront.product_detail.add_to_request') }}</span>
                    <span x-show="added" x-cloak>{{ __('storefront.product_detail.added') }}</span>
                </button>
            </div>

            {{--
                Explicit, unambiguous statement of the actual buying
                mechanism (CLAUDE.md: no on-platform purchase/checkout — a
                phone-only Commercial Offer request, or calling the seller
                directly). Without this sentence in the page's own text, an
                answer engine reading "Add to request" + a price table has
                nothing ruling out it describing this as a normal
                add-to-cart/instant-checkout flow.
            --}}
            <p class="mt-2 text-sm text-text-secondary">{{ __('storefront.product_detail.request_note', ['seller' => $product->seller->name]) }}</p>

            {{-- Seller info block — the literal implementation of CLAUDE.md's "or call the seller directly" alternative path. --}}
            <div class="mt-8 rounded-lg border border-accent-100 bg-accent-50/50 p-4">
                <div class="flex items-center gap-3">
                    @if ($product->seller->logo_path)
                        <img src="{{ Storage::disk('public')->url($product->seller->logo_path) }}" alt="" class="h-10 w-10 rounded-md border border-border object-contain">
                    @else
                        <span class="flex h-10 w-10 items-center justify-center rounded-md bg-surface-sunken text-text-muted">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M3.5 17V6.5l6-3 7 3V17" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                                <path d="M8.5 17v-4h3v4" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                            </svg>
                        </span>
                    @endif
                    <div>
                        {{-- Explicit "Sold by" label, not just a bare name —
                             so this block reads as a complete, citable fact
                             ("sold by X") even when an answer engine pulls
                             just this fragment out of the surrounding page. --}}
                        <p class="text-xs font-medium text-text-muted">{{ __('storefront.product_detail.seller_label') }}</p>
                        <p class="text-base font-semibold text-text-primary">{{ $product->seller->name }}</p>
                    </div>
                </div>

                @if ($territoryLine !== '')
                    <p class="mt-2 text-sm text-text-secondary">{{ __('storefront.product_detail.ships_from', ['location' => $territoryLine]) }}</p>
                @endif

                @if ($product->seller->phone)
                    <p class="mt-3 text-xs font-medium text-text-muted">{{ __('storefront.product_detail.call_seller_label') }}</p>
                    <a href="tel:{{ $product->seller->phone }}" class="mt-1 inline-flex items-center gap-2 text-sm font-medium text-accent-700 hover:underline">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M4.5 4h3l1.2 3.5-1.7 1.3a10 10 0 0 0 4.2 4.2l1.3-1.7L16 12.5v3a1.5 1.5 0 0 1-1.6 1.5A12.5 12.5 0 0 1 3 5.6 1.5 1.5 0 0 1 4.5 4Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" />
                        </svg>
                        {{ $product->seller->phone }}
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Specifications — full-width, server-rendered, ALL parameter values (not just is_filterable ones). --}}
    <section class="mt-10 lg:mt-12" aria-labelledby="product-specifications-heading">
        <h2 id="product-specifications-heading" class="text-xl font-semibold text-text-primary">{{ __('storefront.product_detail.specifications_heading') }}</h2>

        @if ($specRows->isEmpty())
            <p class="mt-3 text-sm text-text-secondary">{{ __('storefront.product_detail.no_specifications') }}</p>
        @else
            <dl class="mt-4 divide-y divide-border rounded-xl border border-border bg-surface-raised">
                @foreach ($specRows as $row)
                    <div class="flex flex-col gap-1 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4 {{ $loop->even ? 'bg-surface-subtle/60' : '' }}">
                        <dt class="text-sm text-text-secondary">{{ $row['label'] }}</dt>
                        <dd class="text-sm font-medium text-text-primary sm:text-right">{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>

    {{--
        Mobile sticky bottom action bar — appears once the buyer scrolls
        past the main "Add to request" button, per doc 12, so the primary
        conversion action stays reachable while reading a long spec table.
        Plain Alpine scroll listener (no intersect plugin is installed in
        this project — see resources/js/app.js/package.json) against the
        main CTA's own wrapper ref, throttled to avoid a scroll-jank
        listener firing on every pixel.
    --}}
    <div
        x-data="{ stickyVisible: false }"
        x-on:scroll.window.throttle.150ms="stickyVisible = $refs.addToRequestAnchor ? $refs.addToRequestAnchor.getBoundingClientRect().bottom < 0 : false"
        x-show="stickyVisible"
        x-cloak
        x-transition
        class="fixed inset-x-0 bottom-0 z-sticky flex items-center justify-between gap-3 border-t border-border bg-surface-raised p-3 shadow-md lg:hidden"
    >
        <p class="text-lg font-bold tabular-nums text-text-primary">
            @if ($vitrinPrice)
                {{ number_format((float) $vitrinPrice->price) }} UZS
            @endif
        </p>
        <button
            type="button"
            wire:click="addToRequest"
            wire:loading.attr="disabled"
            wire:target="addToRequest"
            class="flex h-10 shrink-0 items-center justify-center rounded-lg bg-accent-600 px-5 text-sm font-semibold text-white hover:bg-accent-700 disabled:opacity-70"
        >
            {{ __('storefront.product_detail.add_to_request') }}
        </button>
    </div>
</div>
