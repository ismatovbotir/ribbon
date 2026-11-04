@props([
    'product',
    'showCategory' => false,
])

@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
    $name = $product->localizedName($locale);
    $vitrinPrice = $product->prices->firstWhere('is_vitrin', true);
    $coverImage = $product->images->first();
    $categoryName = $product->category?->name[$locale] ?? ($product->category?->name[$defaultLocale] ?? null);
    // Product detail page — .claude/docs/design/12, App\Livewire\Storefront\Products\Show.
    // Falls back to the default-locale slug (never the raw id) if this
    // locale's slug is somehow missing, so the link is still a real,
    // resolvable product URL rather than a dead one.
    $productSlug = $product->slug[$locale] ?? ($product->slug[$defaultLocale] ?? $product->id);
    $productUrl = route('storefront.products.show', ['productSlug' => $productSlug]);
    $displayName = $name !== '' ? $name : (string) $product->name;
    $brandName = ($product->brand && $product->brand->id !== 1) ? $product->brand->name : null;

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
    $imageUrl = $coverImage ? \Illuminate\Support\Facades\Storage::disk('public')->url($coverImage->path) : null;
@endphp

@php $cols = 'lg:grid-cols-[6rem_minmax(0,1fr)_12rem_10rem_2.75rem]'; @endphp
<li class="relative border-b border-border last:border-b-0 hover:bg-surface-hover">
    <div class="grid grid-cols-[var(--spacing-photo)_minmax(0,1fr)] gap-x-3 px-4 py-3 md:grid-cols-[var(--spacing-photo-lg)_minmax(0,1fr)] md:gap-x-4 md:py-4 {{ $cols }} lg:items-center lg:gap-x-6">
        <div class="size-(--spacing-photo) overflow-hidden rounded-sm border border-border bg-surface-subtle md:size-(--spacing-photo-lg) lg:size-24">
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $displayName }}" width="96" height="96" loading="lazy" class="size-full object-contain p-1">
            @else
                <div class="flex size-full items-center justify-center text-text-muted">
                    <x-storefront.icon name="cube" class="size-7" />
                </div>
            @endif
        </div>

        <div class="min-w-0 lg:contents">
            <div class="min-w-0 lg:col-start-2">
                @if ($showCategory && $categoryName)
                    <p class="text-xs text-text-muted">{{ $categoryName }}</p>
                @endif
                <h3 class="line-clamp-2 text-base leading-snug font-semibold text-text-primary">
                    <a href="{{ $productUrl }}" wire:navigate class="after:absolute after:inset-0 hover:text-accent-700 hover:underline">{{ $displayName }}</a>
                </h3>
                @if ($brandName)
                    <p class="text-sm text-text-secondary">{{ $brandName }}</p>
                @endif
                @if ($specEntries->isNotEmpty())
                    <dl class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 font-mono text-xs text-text-primary tabular-nums md:text-[0.8125rem] lg:gap-x-4">
                        @foreach ($specEntries as $entry)
                            <div class="flex">
                                <dt class="mr-1 sr-only text-text-muted lg:not-sr-only">{{ $entry['label'] }}</dt>
                                <dd>{{ $entry['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            @if ($product->seller)
                <x-storefront.seller-line :seller="$product->seller" :brand="$brandName" class="mt-1 lg:col-start-3 lg:mt-0" />
            @endif

            <div class="mt-2 flex items-end justify-between gap-2 lg:contents">
                <div class="min-w-0 lg:col-start-4">
                    @if ($vitrinPrice)
                        <x-storefront.price-line :price="$vitrinPrice->price" :unit="$vitrinPrice->unit" :moq="$product->moqInPcs()" />
                    @endif
                </div>
                <button
                    type="button"
                    wire:click="quickAdd('{{ $product->id }}')"
                    x-data="{ pending: false, added: false }"
                    x-on:click="pending = true"
                    x-on:selection-changed.window="if (pending) { pending = false; added = true; setTimeout(() => added = false, 1500) }"
                    aria-label="{{ __('storefront.product_card.add_aria', ['name' => $displayName]) }}"
                    x-bind:class="added ? 'border-accent-700 bg-accent-700 text-white' : 'border-border-input bg-surface text-text-primary hover:bg-surface-hover'"
                    class="relative z-10 inline-flex size-11 shrink-0 items-center justify-center rounded-sm border border-border-input bg-surface text-text-primary hover:bg-surface-hover dense:size-10 lg:col-start-5"
                >
                    <x-storefront.icon name="add" x-show="!added" class="size-5" stroke="2" />
                    <x-storefront.icon name="check" x-show="added" x-cloak class="size-5" stroke="2" />
                    <span class="sr-only" aria-live="polite" x-text="added ? @js(__('storefront.product_card.added')) : ''"></span>
                </button>
            </div>
        </div>
    </div>
</li>
