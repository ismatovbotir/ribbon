@php
    use Illuminate\Support\Facades\Storage;

    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];

    $categoryName = $product->category?->name[$locale] ?? ($product->category?->name[$defaultLocale] ?? '');
    $categorySlug = $product->category?->slug[$locale] ?? ($product->category?->slug[$defaultLocale] ?? null);

    $vitrinPrice = $orderedPrices->firstWhere('is_vitrin', true);
    $selectedPrice = $orderedPrices->firstWhere('unit', $selectedUnit) ?? $vitrinPrice;
    $images = $product->images;
    $brandName = ($product->brand && $product->brand->id !== 1) ? $product->brand->name : null;
    $minQty = $selectedPrice ? $product->minQtyForPrice($selectedPrice) : 1;
    $moq = $product->moqInPcs();

    $territoryLine = collect([
        $product->seller->city?->name[$locale] ?? $product->seller->city?->name[$defaultLocale] ?? null,
        $product->seller->region?->name[$locale] ?? $product->seller->region?->name[$defaultLocale] ?? null,
        $product->seller->country?->name[$locale] ?? $product->seller->country?->name[$defaultLocale] ?? null,
    ])->filter(fn ($part) => $part !== null && $part !== '')->implode(', ');

    // Datasheet rows: brand first (when real), then every category parameter in admin order.
    $datasheet = collect();
    if ($brandName) {
        $datasheet->push(['label' => __('storefront.product_detail.brand_label'), 'value' => $brandName]);
    }
    $datasheet = $datasheet->concat($specRows);

    // Plain-language summary (GEO): 2-3 self-contained sentences an answer
    // engine can quote. Built only from data already on the page; the seller
    // phone is never part of it.
    $summarySpecs = collect($specRows)
        ->filter(fn ($row) => ($row['value'] ?? '') !== '')
        ->take(4)
        ->map(fn ($row) => $row['label'].': '.$row['value'])
        ->implode('; ');
    $summaryParts = [];
    if ($categoryName !== '') {
        $summaryParts[] = $territoryLine !== ''
            ? __('storefront.product_detail.summary', ['name' => $displayName, 'category' => $categoryName, 'seller' => $product->seller->name, 'location' => $territoryLine])
            : __('storefront.product_detail.summary_no_location', ['name' => $displayName, 'category' => $categoryName, 'seller' => $product->seller->name]);
    }
    if ($summarySpecs !== '') {
        $summaryParts[] = __('storefront.product_detail.summary_specs', ['specs' => $summarySpecs]);
    }
    if ($vitrinPrice) {
        $summaryParts[] = __('storefront.product_detail.summary_price', [
            'price' => number_format((float) $vitrinPrice->price, 0, '', "\u{00A0}"),
            'unit' => __('storefront.unit.'.$vitrinPrice->unit),
            'moq' => $moq,
        ]);
    }
@endphp

{{--
    Meta description/canonical/hreflang/OG/JSON-LD come from
    Storefront\Products\Show::render() via ->layout(...), rendered centrally
    by layouts/storefront.blade.php. Nothing in this view touches them.
    DOM order (crawlers + phone): breadcrumb, header/H1, gallery, action
    panel, datasheet, seller block, bottom bar (<lg). lg re-places the panel
    to column 2 with grid placement only. The seller phone is NOT in the
    initial HTML: it only renders from $revealedPhone after revealPhone().
--}}
<div x-data="{ galleryActive: 0 }">
    {{-- Breadcrumb (scroll strip on phones) --}}
    <nav aria-label="{{ __('storefront.catalog.breadcrumb_label') }}" class="no-scrollbar -mx-4 mb-3 flex items-center gap-1.5 overflow-x-auto px-4 text-sm whitespace-nowrap md:mx-0 md:px-0">
        <a href="{{ route('storefront.home') }}" wire:navigate class="hit text-text-secondary hover:text-text-primary hover:underline">{{ __('storefront.catalog.breadcrumb_home') }}</a>
        <span class="text-text-muted" aria-hidden="true">/</span>
        @if ($categorySlug)
            <a href="{{ route('storefront.catalog.show', ['categorySlug' => $categorySlug]) }}" wire:navigate class="hit text-text-secondary hover:text-text-primary hover:underline">{{ $categoryName }}</a>
        @else
            <span class="text-text-secondary">{{ $categoryName }}</span>
        @endif
        <span class="text-text-muted" aria-hidden="true">/</span>
        <span class="font-medium text-text-primary" aria-current="page">{{ $displayName }}</span>
    </nav>

    <header class="mt-1">
        @if ($categoryName !== '')
            <p class="text-sm font-medium">
                @if ($categorySlug)
                    <a href="{{ route('storefront.catalog.show', ['categorySlug' => $categorySlug]) }}" wire:navigate class="text-accent-700 hover:underline">{{ $categoryName }}</a>
                @else
                    <span class="text-text-secondary">{{ $categoryName }}</span>
                @endif
            </p>
        @endif
        <h1 class="mt-1 text-xl font-semibold text-balance text-text-primary lg:text-2xl">{{ $displayName }}</h1>
        <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-text-secondary">
            @if ($brandName)
                <span>{{ $brandName }}</span>
                <span aria-hidden="true">&middot;</span>
            @endif
            {{-- Partner badge stays off until approved brand-authorization data exists (CLAUDE.md: self-claims are never shown). --}}
            <x-storefront.seller-line :seller="$product->seller" :labelled="true" />
        </div>
        @if ($summaryParts !== [])
            <p class="mt-3 max-w-3xl text-base text-text-secondary">{{ implode(' ', $summaryParts) }}</p>
        @endif
    </header>

    <div class="mt-4 lg:mt-6 lg:grid lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] lg:gap-x-10">
        {{-- Gallery: every image is in the DOM (no JS-only reveal) --}}
        <div class="lg:col-start-1 lg:row-start-1">
            <div class="relative overflow-hidden rounded-lg border border-border bg-surface-subtle">
                <div x-ref="strip" class="no-scrollbar flex snap-x snap-mandatory overflow-x-auto" x-on:scroll.throttle.100ms="galleryActive = Math.round($refs.strip.scrollLeft / $refs.strip.clientWidth)">
                    @forelse ($images as $image)
                        <div class="aspect-square w-full shrink-0 snap-center">
                            <img
                                src="{{ Storage::disk('public')->url($image->path) }}"
                                alt="{{ $displayName }}{{ $images->count() > 1 ? ' ('.$loop->iteration.')' : '' }}"
                                width="800"
                                height="800"
                                class="size-full object-contain p-4"
                                loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                            >
                        </div>
                    @empty
                        <div class="flex aspect-square w-full flex-col items-center justify-center gap-2">
                            <x-storefront.icon name="cube" class="size-12 text-text-muted" />
                            <span class="text-sm text-text-muted">{{ __('storefront.product_detail.gallery_no_image') }}</span>
                        </div>
                    @endforelse
                </div>

                @if ($images->count() > 1)
                    <button type="button" x-on:click="$refs.strip.scrollBy({ left: -$refs.strip.clientWidth, behavior: 'smooth' })" aria-label="{{ __('storefront.product_detail.gallery_prev') }}" class="absolute top-1/2 left-2 hidden size-11 -translate-y-1/2 items-center justify-center rounded-sm border border-border-input bg-surface text-text-primary hover:bg-surface-hover lg:flex">
                        <x-storefront.icon name="chevron-left" class="size-4" stroke="2" />
                    </button>
                    <button type="button" x-on:click="$refs.strip.scrollBy({ left: $refs.strip.clientWidth, behavior: 'smooth' })" aria-label="{{ __('storefront.product_detail.gallery_next') }}" class="absolute top-1/2 right-2 hidden size-11 -translate-y-1/2 items-center justify-center rounded-sm border border-border-input bg-surface text-text-primary hover:bg-surface-hover lg:flex">
                        <x-storefront.icon name="chevron-right" class="size-4" stroke="2" />
                    </button>
                @endif
            </div>

            @if ($images->count() > 1)
                <p class="mt-2 text-xs text-text-muted tabular-nums"><span x-text="galleryActive + 1">1</span> / {{ $images->count() }}</p>
                <div class="mt-2 hidden gap-2 md:flex">
                    @foreach ($images as $image)
                        <button
                            type="button"
                            x-on:click="$refs.strip.scrollTo({ left: $refs.strip.clientWidth * {{ $loop->index }}, behavior: 'smooth' })"
                            x-bind:class="galleryActive === {{ $loop->index }} ? 'border-accent-700 outline outline-1 outline-accent-700' : 'border-border'"
                            aria-label="{{ __('storefront.product_detail.gallery_thumbnail', ['n' => $loop->iteration]) }}"
                            class="size-16 shrink-0 overflow-hidden rounded-sm border bg-surface p-1"
                        >
                            <img src="{{ Storage::disk('public')->url($image->path) }}" alt="" width="64" height="64" class="size-full object-contain" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Action panel (sticky at lg) --}}
        <aside class="mt-6 lg:sticky lg:top-[calc(var(--spacing-storefront-header)+1rem)] lg:col-start-2 lg:row-span-3 lg:row-start-1 lg:mt-0 lg:self-start">
            <div class="overflow-hidden rounded-md border border-border-strong bg-surface">
                {{-- Quiet price strip (decision 9): regular weight, secondary colour, never louder than the title or the CTA --}}
                @if ($selectedPrice)
                    <div class="border-b border-border bg-surface-band px-4 py-3 md:px-5">
                        <p class="text-xs font-semibold text-text-secondary">{{ __('storefront.product_detail.price_per_unit_strip') }}</p>
                        <p class="font-mono text-base font-normal text-text-secondary tabular-nums">{{ number_format((float) $selectedPrice->price, 0, '', "\u{00A0}") }} UZS <span class="text-text-muted">/ {{ __('storefront.unit.'.$selectedPrice->unit) }}</span></p>
                        <p class="mt-0.5 font-mono text-sm text-text-secondary tabular-nums">{{ __('storefront.moq') }} {{ $moq }} {{ __('storefront.unit.pcs') }}</p>
                    </div>
                @endif

                <div class="p-4 md:p-5">
                {{-- Price comparison: every enabled unit, real <table> (data-table recipe, spec 20 section 9) --}}
                <div class="overflow-hidden rounded-md border border-border-strong bg-surface">
                    <table class="w-full border-collapse text-sm">
                        <caption class="sr-only">{{ __('storefront.product_detail.price_table_caption') }}</caption>
                        <thead>
                            <tr class="bg-surface-band">
                                <th scope="col" class="h-10 border-b border-border-strong px-3 text-left text-xs font-semibold text-text-secondary md:px-4">{{ __('storefront.product_detail.price_col_unit') }}</th>
                                <th scope="col" class="h-10 border-b border-border-strong px-3 text-right text-xs font-semibold text-text-secondary md:px-4">{{ __('storefront.product_detail.price_col_qty') }}</th>
                                <th scope="col" class="h-10 border-b border-border-strong px-3 text-right text-xs font-semibold text-text-secondary md:px-4">{{ __('storefront.product_detail.price_col_price') }}</th>
                                <th scope="col" class="h-10 border-b border-border-strong px-3 text-right text-xs font-semibold text-text-secondary md:px-4">{{ __('storefront.product_detail.price_col_per_unit') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orderedPrices as $price)
                                <tr class="border-b border-border last:border-b-0 {{ $price->is_vitrin ? 'bg-accent-50' : '' }}">
                                    <th scope="row" class="h-12 px-3 text-left font-sans font-medium text-text-primary md:px-4 {{ $price->is_vitrin ? 'border-l-2 border-l-accent-700' : '' }}">
                                        {{ __('storefront.unit.'.$price->unit) }}
                                        @if ($price->is_vitrin)
                                            <span class="ml-2 inline-flex h-5 items-center rounded-xs border border-accent-200 bg-surface px-1.5 text-xs font-medium text-accent-800">{{ __('storefront.product_detail.default_unit_tag') }}</span>
                                        @endif
                                    </th>
                                    <td class="h-12 px-3 text-right font-mono text-text-secondary tabular-nums md:px-4">{{ number_format($price->qty_in_pcs) }}</td>
                                    <td class="h-12 px-3 text-right font-mono text-text-secondary tabular-nums md:px-4">{{ number_format((float) $price->price, 0, '', "\u{00A0}") }}</td>
                                    <td class="h-12 px-3 text-right font-mono text-text-secondary tabular-nums md:px-4">{{ number_format((float) $price->price / max($price->qty_in_pcs, 1), 0, '', "\u{00A0}") }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Unit switch --}}
                <p id="unit-switch-label" class="mt-4 mb-1.5 text-sm font-medium text-text-primary">{{ __('storefront.product_detail.unit_selector_label') }}</p>
                <div role="radiogroup" aria-labelledby="unit-switch-label" class="inline-grid auto-cols-fr grid-flow-col divide-x divide-border-input overflow-hidden rounded-sm border border-border-input">
                    @foreach ($orderedPrices as $price)
                        <button
                            type="button"
                            wire:click="selectUnit('{{ $price->unit }}')"
                            role="radio"
                            aria-checked="{{ $selectedUnit === $price->unit ? 'true' : 'false' }}"
                            class="flex h-11 min-w-20 items-center justify-center gap-1.5 px-4 text-sm font-medium {{ $selectedUnit === $price->unit ? 'bg-accent-700 text-white' : 'bg-surface text-text-secondary hover:bg-surface-hover' }}"
                        >
                            @if ($selectedUnit === $price->unit)
                                <x-storefront.icon name="check" class="size-4" stroke="2" />
                            @endif
                            {{ __('storefront.unit.'.$price->unit) }}
                        </button>
                    @endforeach
                </div>

                {{-- Quantity --}}
                <p class="mt-4 mb-1.5 text-sm font-medium text-text-primary"><label for="product-qty">{{ __('storefront.product_detail.qty_label') }}</label></p>
                <x-storefront.qty-stepper :value="$qty" :min="$minQty">
                    <input id="product-qty" type="text" inputmode="numeric" wire:model.live="qty" class="h-11 w-16 rounded-none border-0 bg-surface text-center font-mono text-field text-text-primary tabular-nums">
                </x-storefront.qty-stepper>
                <p class="mt-1.5 font-mono text-xs text-text-muted tabular-nums">{{ __('storefront.product_detail.min_order', ['qty' => $minQty, 'unit' => __('storefront.unit.'.($selectedPrice->unit ?? 'pcs')), 'pcs' => $moq]) }}</p>

                {{-- Actions (lg+; under lg the fixed bottom bar carries them) --}}
                <div class="mt-5 hidden lg:block">
                    <x-button variant="cta" size="lg" wire:click="requestOffer" wire:loading.attr="disabled" wire:target="requestOffer" class="w-full">{{ __('storefront.product_detail.request_offer') }}</x-button>

                    @if ($phoneRevealed && $revealedPhone)
                        <x-button tag="a" variant="secondary" href="tel:{{ $revealedPhone }}" class="mt-2 w-full font-mono" aria-live="polite">{{ $revealedPhone }}</x-button>
                    @else
                        <x-button variant="secondary" wire:click="revealPhone" wire:loading.attr="disabled" wire:target="revealPhone" class="mt-2 w-full">{{ __('storefront.product_detail.show_phone') }}</x-button>
                    @endif
                    @error('phone')
                        <p class="mt-1.5 text-xs font-medium text-danger-700" role="alert">{{ $message }}</p>
                    @enderror
                    @error('selection')
                        <p class="mt-1.5 text-xs font-medium text-danger-700" role="alert">{{ $message }}</p>
                    @enderror

                    <x-button
                        variant="ghost"
                        wire:click="addToRequest"
                        wire:target="addToRequest"
                        x-data="{ added: false }"
                        x-on:added-to-request.window="added = true; setTimeout(() => added = false, 1500)"
                        class="mt-1 w-full"
                    >
                        <span x-show="!added">{{ __('storefront.product_detail.keep_browsing') }}</span>
                        <span x-show="added" x-cloak>{{ __('storefront.product_detail.added') }}</span>
                    </x-button>
                </div>

                {{-- Under lg: keep-browsing as the third row of the panel --}}
                <div class="mt-4 lg:hidden">
                    <x-button
                        variant="secondary"
                        wire:click="addToRequest"
                        wire:target="addToRequest"
                        x-data="{ added: false }"
                        x-on:added-to-request.window="added = true; setTimeout(() => added = false, 1500)"
                        class="w-full"
                    >
                        <span x-show="!added">{{ __('storefront.product_detail.keep_browsing') }}</span>
                        <span x-show="added" x-cloak>{{ __('storefront.product_detail.added') }}</span>
                    </x-button>
                    @error('phone')
                        <p class="mt-1.5 text-xs font-medium text-danger-700" role="alert">{{ $message }}</p>
                    @enderror
                    @error('selection')
                        <p class="mt-1.5 text-xs font-medium text-danger-700" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Explicit statement of the actual mechanism (GEO): a request for an offer, no checkout. --}}
                <p class="mt-3 text-sm text-text-secondary">{{ __('storefront.product_detail.request_note', ['seller' => $product->seller->name]) }}</p>
                </div>
            </div>
        </aside>

        {{-- Datasheet: always server-rendered, never collapsed --}}
        <section class="mt-8 lg:col-start-1 lg:row-start-2" aria-labelledby="product-specifications-heading">
            <x-storefront.section-heading id="product-specifications-heading" :title="__('storefront.product_detail.specifications_heading')" />

            @if ($datasheet->isEmpty())
                <p class="mt-3 text-sm text-text-secondary">{{ __('storefront.product_detail.no_specifications') }}</p>
            @else
                <div class="mt-4 overflow-hidden rounded-md border border-border-strong bg-surface">
                    <table class="w-full border-collapse text-sm">
                        <caption class="sr-only">{{ __('storefront.product_detail.specifications_caption', ['name' => $displayName]) }}</caption>
                        <tbody>
                            @foreach ($datasheet as $row)
                                <tr class="border-b border-border last:border-b-0">
                                    <th scope="row" class="w-2/5 border-r border-border bg-surface-band px-3 py-3 text-left align-top text-sm font-normal text-text-secondary md:w-1/3 md:px-4">{{ $row['label'] }}</th>
                                    <td class="px-3 py-3 font-mono text-sm break-words text-text-primary tabular-nums md:px-4">
                                        {{ $row['value'] }}
                                        @if (! empty($row['copy']))
                                            {{-- Part-number copy (spec 20 8.5): only rows flagged `copy`; hidden when the Clipboard API is unavailable. --}}
                                            <button
                                                type="button"
                                                x-data="{ copied: false }"
                                                x-show="navigator.clipboard"
                                                x-cloak
                                                x-on:click="navigator.clipboard.writeText(@js($row['value'])); copied = true; setTimeout(() => copied = false, 1500)"
                                                aria-label="{{ __('storefront.product_detail.copy_part_number') }}"
                                                class="hit ml-1 inline-flex size-8 items-center justify-center rounded-sm align-middle text-text-muted hover:bg-surface-hover hover:text-text-primary"
                                            >
                                                <x-storefront.icon name="copy" x-show="!copied" class="size-4" stroke="2" />
                                                <x-storefront.icon name="check" x-show="copied" x-cloak class="size-4" stroke="2" />
                                                <span class="sr-only" aria-live="polite" x-text="copied ? @js(__('storefront.product_detail.copied')) : ''"></span>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Seller trust block. Only data the platform holds is shown; empty values are omitted. --}}
        <section class="mt-8 lg:col-start-1 lg:row-start-3" aria-labelledby="product-seller-heading">
            <x-storefront.section-heading id="product-seller-heading" :title="__('storefront.product_detail.seller_label')" />
            <div class="mt-4 overflow-hidden rounded-md border border-border-strong bg-surface">
                <div class="flex items-center gap-3 border-b border-border bg-surface-band p-4">
                    @if ($product->seller->logo_path)
                        <img src="{{ Storage::disk('public')->url($product->seller->logo_path) }}" alt="" width="48" height="48" loading="lazy" class="size-12 shrink-0 rounded-sm border border-border bg-surface object-contain">
                    @else
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-sm border border-border bg-surface text-text-muted">
                            <x-storefront.icon name="cube" class="size-6" />
                        </span>
                    @endif
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-semibold text-text-primary">{{ $product->seller->name }}</h3>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <x-storefront.verified :labelled="true" />
                        </div>
                    </div>
                </div>
                <p class="px-4 pt-3 text-xs text-text-muted">{{ __('storefront.seller.verified_explainer') }}</p>
                @php($seller = $product->seller)
                <dl class="mt-3 text-sm">
                    @if ($territoryLine !== '')
                        <div class="grid grid-cols-[2fr_3fr] gap-3 border-t border-border px-4 py-3">
                            <dt class="text-text-secondary">{{ __('storefront.seller.location') }}</dt>
                            <dd class="text-text-primary">{{ $territoryLine }}</dd>
                        </div>
                    @endif
                    @if ($seller->is_vat_payer !== null)
                        <div class="grid grid-cols-[2fr_3fr] gap-3 border-t border-border px-4 py-3">
                            <dt class="text-text-secondary">{{ __('storefront.seller.vat_payer') }}</dt>
                            <dd class="text-text-primary">{{ $seller->is_vat_payer ? __('storefront.seller.vat_payer_yes') : __('storefront.seller.vat_payer_no') }}</dd>
                        </div>
                    @endif
                    @if (! empty($seller->payment_methods))
                        <div class="grid grid-cols-[2fr_3fr] gap-3 border-t border-border px-4 py-3">
                            <dt class="text-text-secondary">{{ __('storefront.seller.payment_methods') }}</dt>
                            <dd class="text-text-primary">{{ collect($seller->payment_methods)->map(fn ($method) => __('storefront.seller.payment_method.'.$method))->implode(', ') }}</dd>
                        </div>
                    @endif
                    @if ($seller->warranty_info)
                        <div class="grid grid-cols-[2fr_3fr] gap-3 border-t border-border px-4 py-3">
                            <dt class="text-text-secondary">{{ __('storefront.seller.warranty') }}</dt>
                            <dd class="break-words whitespace-pre-line text-text-primary">{{ $seller->warranty_info }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </section>
    </div>

    {{-- Bottom bar, product mode (below lg). The only fixed element on this page; yellow CTA = "Request offer". --}}
    <div class="elev-up fixed inset-x-0 bottom-0 z-(--z-bar) border-t border-border-strong bg-surface px-3 pt-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))] lg:hidden">
        <div class="mx-auto grid max-w-xl gap-2 {{ $phoneRevealed && $revealedPhone ? 'grid-cols-[auto_minmax(0,1fr)]' : 'grid-cols-2' }}">
            @if ($phoneRevealed && $revealedPhone)
                <x-button tag="a" variant="secondary" href="tel:{{ $revealedPhone }}" class="min-h-12 px-3 font-mono" aria-live="polite">{{ $revealedPhone }}</x-button>
            @else
                <x-button variant="secondary" wire:click="revealPhone" wire:loading.attr="disabled" wire:target="revealPhone" class="min-h-12 px-3">{{ __('storefront.product_detail.show_phone') }}</x-button>
            @endif
            <x-button variant="cta" wire:click="requestOffer" wire:loading.attr="disabled" wire:target="requestOffer" class="min-h-12 px-3 font-semibold">{{ __('storefront.product_detail.request_offer') }}</x-button>
        </div>
    </div>
</div>
