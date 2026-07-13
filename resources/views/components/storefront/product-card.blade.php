@props([
    'product',
    // Category eyebrow is shown on cross-category grids (home, search) and
    // omitted on single-category catalog pages, where it'd just repeat the
    // page's own H1/breadcrumb — see docs/design/10-storefront-product-card.md.
    'showCategory' => false,
])

@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
    $name = $product->localizedName($locale);
    $vitrinPrice = $product->prices->firstWhere('is_vitrin', true);
    $coverImage = $product->images->first();
    $categoryName = $product->category?->name[$locale] ?? ($product->category?->name[$defaultLocale] ?? null);
    // Product detail page — docs/design/12, App\Livewire\Storefront\Products\Show.
    // Falls back to the default-locale slug (never the raw id) if this
    // locale's slug is somehow missing, so the link is still a real,
    // resolvable product URL rather than a dead one.
    $productSlug = $product->slug[$locale] ?? ($product->slug[$defaultLocale] ?? $product->id);
    $productUrl = route('storefront.products.show', ['productSlug' => $productSlug]);

    // Top filled specs, as extractable text — until the product detail
    // page exists, this card is the *only* place a buyer (or an AI answer
    // engine reading this listing) can see any spec value at all; the
    // filter sidebar only exposes spec *names*/options, not what a given
    // product itself has. Sourced entirely from parameterValues, which
    // both Home and Catalog\Show already eager-load
    // (parameterValues.categoryParameter, parameterValues.options.categoryParameterOption)
    // — no additional query from this component. Mirrors the same
    // text/number/select display rules as Product::localizedName(), capped
    // to 3 entries to keep the card compact; sorted by the category's own
    // admin-defined parameter order.
    $specEntries = $product->parameterValues
        ->filter(fn ($value) => $value->categoryParameter !== null)
        ->sortBy(fn ($value) => $value->categoryParameter->sort_order)
        ->map(function ($value) use ($locale, $defaultLocale) {
            $parameter = $value->categoryParameter;
            $paramName = $parameter->name[$locale] ?? ($parameter->name[$defaultLocale] ?? '');

            $display = match ($parameter->type) {
                'text' => (string) $value->value_text,
                'number' => $value->value_number !== null
                    ? ((string) ((float) $value->value_number + 0)).($parameter->unit ? ' '.$parameter->unit : '')
                    : '',
                'select_single', 'select_multiple' => $value->options
                    ->map(fn ($option) => $option->categoryParameterOption?->value[$locale] ?? ($option->categoryParameterOption?->value[$defaultLocale] ?? ''))
                    ->filter(fn ($label) => $label !== '')
                    ->implode(', '),
                default => '',
            };

            return ($paramName !== '' && $display !== '') ? ['label' => $paramName, 'value' => $display] : null;
        })
        ->filter()
        ->take(3);
@endphp

<div class="group relative flex flex-col overflow-hidden rounded-xl border border-border bg-surface-raised shadow-xs transition-shadow hover:shadow-sm">
    <a href="{{ $productUrl }}" class="contents">
        {{-- Image region --}}
        <div class="aspect-square overflow-hidden rounded-t-xl bg-surface-subtle p-3">
            @if ($coverImage)
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($coverImage->path) }}"
                    alt="{{ $name }}"
                    class="h-full w-full object-contain"
                    loading="lazy"
                >
            @else
                <div class="flex h-full w-full flex-col items-center justify-center gap-1.5 rounded-lg bg-surface-sunken">
                    <svg class="h-9 w-9 text-text-muted" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect x="3.5" y="7" width="17" height="13" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                        <path d="M3.5 7 12 3l8.5 4" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                        <path d="M8 11.5h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                    </svg>
                    <span class="text-xs text-text-muted">{{ __('storefront.catalog.no_image') }}</span>
                </div>
            @endif
        </div>

        {{-- Content --}}
        <div class="flex flex-1 flex-col gap-0.5 p-4">
            @if ($showCategory && $categoryName)
                <p class="text-xs tracking-wide text-text-muted uppercase">{{ $categoryName }}</p>
            @endif

            <h3 class="line-clamp-2 text-lg font-semibold text-text-primary">{{ $name !== '' ? $name : $product->name }}</h3>

            @if ($product->brand && $product->brand->id !== 1)
                <p class="text-xs text-text-secondary">{{ $product->brand->name }}</p>
            @endif

            @if ($product->seller)
                <p class="text-xs text-text-secondary">{{ __('storefront.product_card.sold_by', ['seller' => $product->seller->name]) }}</p>
            @endif

            @if ($specEntries->isNotEmpty())
                <dl class="mt-1 flex flex-col gap-0.5">
                    @foreach ($specEntries as $entry)
                        <div class="flex gap-1 text-xs">
                            <dt class="shrink-0 text-text-muted">{{ $entry['label'] }}:</dt>
                            <dd class="truncate text-text-secondary">{{ $entry['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            <div class="flex-1"></div>

            <div class="mt-3 flex items-end justify-between gap-2">
                @if ($vitrinPrice)
                    <p>
                        <span class="text-xl font-bold tabular-nums text-text-primary">{{ number_format((float) $vitrinPrice->price) }} UZS</span>
                        <span class="text-sm text-text-secondary">/ {{ __('storefront.unit.'.$vitrinPrice->unit) }}</span>
                    </p>
                @else
                    <span></span>
                @endif
            </div>
        </div>

        {{-- Stretched link overlay — the whole card is clickable, but the
             "+ Add" button below lives outside this <a>'s DOM subtree (a
             sibling), so it stays independently clickable rather than
             triggering navigation. See doc 10's markup gotcha note. --}}
        <span class="absolute inset-0" aria-hidden="true"></span>
    </a>

    {{--
        Quick-add ("+ Add") — visually implemented per doc 10, but not
        wired to real add-to-selection logic yet: the selection/offer-
        request flow (session shape, increment-on-duplicate, etc.) is a
        separate task (#19). Disabled rather than silently doing nothing on
        click, so it doesn't read as broken.
    --}}
    <div class="pointer-events-none absolute right-4 bottom-4 z-10">
        <button
            type="button"
            disabled
            title="{{ __('storefront.product_card.add_coming_soon') }}"
            class="pointer-events-auto flex h-9 items-center rounded-sm border border-border bg-surface px-3 text-sm font-medium text-text-secondary opacity-90 disabled:cursor-not-allowed"
        >
            {{ __('storefront.product_card.add_button') }}
        </button>
    </div>
</div>
