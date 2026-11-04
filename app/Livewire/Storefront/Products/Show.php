<?php

namespace App\Livewire\Storefront\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerContactEvent;
use App\Services\OfferSelectionService;
use App\Services\ProductAnalyticsService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    /**
     * Order price rows are always displayed/selectable in, matching the
     * fixed pcs < pack < box unit hierarchy from CLAUDE.md — sqlite has no
     * portable FIELD()/ORDER BY CASE shortcut worth reaching for here, so
     * the 3-row collection is just re-sorted in PHP after eager-loading.
     */
    private const UNIT_ORDER = ['pcs', 'pack', 'box'];

    public Product $product;

    public string $selectedUnit;

    /**
     * Client-bound: kept `mixed` (not int) so a non-numeric value typed or
     * posted into the input can't TypeError; always read via safeQty().
     */
    public mixed $qty = 1;

    /**
     * Seller phone, populated ONLY by revealPhone() (anti-scraping: never in
     * the initial page data). Locked so the client cannot set it.
     */
    #[Locked]
    public ?string $revealedPhone = null;

    #[Locked]
    public bool $phoneRevealed = false;

    /**
     * Resolves the product from its per-locale JSON slug — mirrors
     * Storefront\Catalog\Show::mount() exactly (locale-fallback lookup,
     * then a canonical redirect if the matched locale's slug differs from
     * the segment actually requested). See that method's docblock for the
     * full reasoning; not repeated here to avoid the two drifting out of
     * sync in prose while diverging in code.
     *
     * Parameter deliberately named `$productSlug`, not `$product` — same
     * Livewire implicit-route-model-binding collision `Catalog\Show`
     * flagged for `$categorySlug`/`$category`, just against `Product` here.
     *
     * Buyer-facing: only `status = approved` products ever resolve here.
     * A pending/rejected/suspended product's slug 404s outright — there is
     * no "this listing is under review" view a buyer can reach, matching
     * doc 12's explicit "no moderation-status UI on this page at all".
     */
    public function mount(string $productSlug): void
    {
        $locales = config('ribbon.locales');
        $currentLocale = app()->getLocale();

        $found = Product::query()
            ->visibleOnStorefront()
            ->where("slug->{$currentLocale}", $productSlug)
            ->first();

        if (! $found) {
            foreach ($locales as $locale) {
                if ($locale === $currentLocale) {
                    continue;
                }

                $found = Product::query()
                    ->visibleOnStorefront()
                    ->where("slug->{$locale}", $productSlug)
                    ->first();

                if ($found) {
                    break;
                }
            }
        }

        abort_unless($found, 404);

        $canonicalSlug = $found->slug[$currentLocale] ?? null;

        if ($canonicalSlug && $canonicalSlug !== $productSlug) {
            $this->redirect(route('storefront.products.show', ['productSlug' => $canonicalSlug]));

            return;
        }

        $this->product = $found->load([
            'category',
            'brand',
            'seller.country',
            'seller.region',
            'seller.city',
            // Full spec sheet: ALL parameter values (not just is_filterable
            // ones — that subset is the catalog sidebar's job, doc 11), in
            // the category's own sort_order via categoryParameter below.
            'parameterValues.categoryParameter',
            'parameterValues.options.categoryParameterOption',
            'images',
            // Every enabled unit row, not just is_vitrin — CLAUDE.md is
            // explicit that a serious B2B buyer compares pcs/pack/box
            // pricing side by side on this page specifically.
            'prices',
        ]);

        $vitrinPrice = $this->product->prices->firstWhere('is_vitrin', true);
        // Falls back to 'pcs' defensively; every product is guaranteed a
        // pcs row (Product::bootProduct()) and exactly one vitrin row
        // (ProductPrice::bootProductPrice()), so $vitrinPrice is never
        // actually null in practice.
        $this->selectedUnit = $vitrinPrice->unit ?? 'pcs';

        // mount() only ever runs once per page load (unlike render(), which
        // re-fires on every Livewire update) — the correct place to record
        // exactly one view per visit. See ProductAnalyticsService.
        ProductAnalyticsService::recordView($this->product);
    }

    /**
     * Switches the unit the "Add to request" button/price line reflects.
     * Silently ignored for a unit this product hasn't enabled (defensive
     * against a stale/tampered client-side click on a unit that was never
     * rendered) rather than raising — the segmented control only ever
     * renders enabled units in the first place, see doc 12.
     */
    public function selectUnit(string $unit): void
    {
        if ($this->product->prices->contains('unit', $unit)) {
            $this->selectedUnit = $unit;
        }
    }

    public function incrementQty(): void
    {
        $this->qty = OfferSelectionService::clampQty($this->safeQty() + 1);
    }

    public function decrementQty(): void
    {
        $this->qty = OfferSelectionService::clampQty($this->safeQty() - 1);
    }

    /**
     * Keeps the qty stepper's bound number input an integer >= 1 regardless
     * of what a buyer types directly into it (the +/- buttons alone can't
     * produce an invalid value, but the input is also directly editable).
     */
    public function updatedQty(mixed $value): void
    {
        $this->qty = $this->safeQty();
    }

    protected function safeQty(): int
    {
        return OfferSelectionService::clampQty(is_numeric($this->qty) ? $this->qty : 1);
    }

    /**
     * Adds the selected unit/qty to the buyer's selection. Visibility and
     * unit are re-validated server-side (client state is untrusted); the
     * add is idempotent (see OfferSelectionService::ensure()).
     */
    public function addToRequest(): void
    {
        if (OfferSelectionService::throttled()) {
            $this->addError('selection', __('storefront.selection.throttled'));

            return;
        }

        if (OfferSelectionService::ensure($this->product->id, $this->selectedUnit, $this->safeQty())) {
            $this->dispatch('added-to-request');
        } elseif (OfferSelectionService::isFull()) {
            $this->addError('selection', __('storefront.selection.line_limit', ['max' => OfferSelectionService::MAX_LINES]));
        }

        $this->dispatch('selection-changed', count: OfferSelectionService::count());
    }

    /**
     * "Request offer": ensure the line is in the selection (idempotent -
     * repeating it never inflates qty), then go to the request form. A
     * product that is no longer visible 404s instead of being requested.
     */
    public function requestOffer(): mixed
    {
        if (OfferSelectionService::throttled()) {
            $this->addError('selection', __('storefront.selection.throttled'));

            return null;
        }

        if (! OfferSelectionService::ensure($this->product->id, $this->selectedUnit, $this->safeQty())) {
            if (OfferSelectionService::isFull()) {
                $this->addError('selection', __('storefront.selection.line_limit', ['max' => OfferSelectionService::MAX_LINES]));

                return null;
            }

            abort(404);
        }

        $this->dispatch('selection-changed', count: OfferSelectionService::count());

        return $this->redirect(route('storefront.offer-request.show'), navigate: true);
    }

    /**
     * "Show phone": returns the owning seller's phone and logs one lead
     * event (no buyer data). Rate limited per IP (10 / 10 min); repeated
     * reveals by the same visitor on the same product within 24h are
     * counted as a single lead. Returns null when throttled or hidden.
     */
    public function revealPhone(): ?string
    {
        $product = Product::query()->visibleOnStorefront()->with('seller')->find($this->product->id);

        abort_unless($product, 404);

        $ip = request()->ip();
        $rateKey = 'reveal-phone:ip:'.$ip;

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $this->addError('phone', __('storefront.product.phone_throttled'));

            return null;
        }

        RateLimiter::hit($rateKey, 600);

        $visitor = SellerContactEvent::visitorHash($ip);

        $alreadyCounted = SellerContactEvent::query()
            ->where('product_id', $product->id)
            ->where('type', 'phone_reveal')
            ->where('visitor_hash', $visitor)
            ->where('occurred_at', '>=', now()->subDay())
            ->exists();

        if (! $alreadyCounted) {
            SellerContactEvent::create([
                'product_id' => $product->id,
                'seller_id' => $product->seller_id,
                'type' => 'phone_reveal',
                'visitor_hash' => $visitor,
                'occurred_at' => now(),
            ]);
        }

        $this->phoneRevealed = true;
        $this->revealedPhone = $product->seller->phone;

        return $this->revealedPhone;
    }

    public function render()
    {
        $navCategories = Category::navList();
        $locale = app()->getLocale();
        $defaultLocale = config('ribbon.locales')[0];

        $orderedPrices = $this->product->prices
            ->sortBy(fn ($price) => array_search($price->unit, self::UNIT_ORDER, true))
            ->values();

        $specRows = $this->product->parameterValues
            ->filter(fn ($value) => $value->categoryParameter !== null)
            ->sortBy(fn ($value) => $value->categoryParameter->sort_order)
            ->map(function ($value) use ($locale, $defaultLocale) {
                $parameter = $value->categoryParameter;

                $displayValue = match ($parameter->type) {
                    'text' => (string) $value->value_text,
                    'number' => $value->value_number !== null
                        ? ((string) ((float) $value->value_number + 0)).($parameter->unit ?? '')
                        : '',
                    'select_single', 'select_multiple' => $value->options
                        ->map(fn ($option) => $option->categoryParameterOption?->value[$locale]
                            ?? $option->categoryParameterOption?->value[$defaultLocale]
                            ?? '')
                        ->filter(fn ($label) => $label !== '')
                        ->implode(', '),
                    default => '',
                };

                return [
                    'label' => $parameter->name[$locale] ?? ($parameter->name[$defaultLocale] ?? ''),
                    'value' => $displayValue,
                ];
            })
            ->filter(fn (array $row) => $row['value'] !== '')
            ->values();

        $displayName = $this->product->localizedName($locale);
        $displayName = $displayName !== '' ? $displayName : ($this->product->name ?? '');

        // localizedName() composes brand + filled category-parameter values
        // (width, material, ...) but never the category name itself (see
        // Product::composeLabel()'s docblock — category is deliberately
        // left out of the buyer-facing display label). The <title> tag is a
        // separate SERP-facing string from the on-page <h1>, so it's worth
        // appending category context here even though the h1 stays exactly
        // $displayName — a bare spec string like "Zebra 110mm 25mm
        // Wax-Resin" is far less immediately parseable in a search result
        // than the same string suffixed with its category. Computed below
        // once $categoryName is resolved.

        // --- SEO/GEO (see this task's own instructions: seo-engineer/
        // geo-engineer would normally be handed this page next; this repo's
        // Storefront\Home and Storefront\Catalog\Show already went through
        // that pass — see their render() methods — so this mirrors their
        // exact established shape/variable names rather than inventing a
        // new one. Flagged in the final report as still needing a real
        // seo-engineer/geo-engineer review pass, since this was written by
        // the frontend engineer as a best-effort placeholder, not that
        // specialized agent.) ---
        $categoryName = $this->product->category?->name[$locale] ?? ($this->product->category?->name[$defaultLocale] ?? '');
        $categorySlug = $this->product->category?->slug[$locale] ?? ($this->product->category?->slug[$defaultLocale] ?? null);

        $pageTitle = ($displayName !== '' && $categoryName !== '')
            ? __('storefront.seo.product_title', ['name' => $displayName, 'category' => $categoryName])
            : ($displayName !== '' ? $displayName : __('storefront.nav.brand'));

        $productSlugForLocale = $this->product->slug[$locale] ?? ($this->product->slug[$defaultLocale] ?? '');
        $baseUrl = route('storefront.products.show', ['productSlug' => $productSlugForLocale]);

        // Same `?lang=` mechanism Home/Catalog\Show already established —
        // locale is session+query-param driven (SetLocale middleware), not
        // a URL path prefix, so a stateless crawler needs it explicit for
        // any non-default locale even though the slug segment itself also
        // already differs per locale.
        $canonicalUrl = $locale !== $defaultLocale ? $baseUrl.'?lang='.$locale : $baseUrl;

        $hreflangAlternates = [];

        foreach (config('ribbon.locales') as $loc) {
            $slugForLoc = $this->product->slug[$loc] ?? null;

            if (! $slugForLoc) {
                // slug is required in all 3 locales at creation time (see
                // Product::bootProduct()) — guarded defensively rather than
                // emitting a broken hreflang link if that ever isn't true.
                continue;
            }

            $altUrl = route('storefront.products.show', ['productSlug' => $slugForLoc]);

            if ($loc !== $defaultLocale) {
                $altUrl .= '?lang='.$loc;
            }

            $hreflangAlternates[$loc] = $altUrl;
        }

        if (isset($hreflangAlternates[$defaultLocale])) {
            $hreflangAlternates['x-default'] = $hreflangAlternates[$defaultLocale];
        }

        // Label + value (not just bare values) — "Width 110mm, Material
        // Wax-Resin" reads unambiguously in a SERP snippet and still matches
        // how a B2B buyer actually phrases an exact-spec search, whereas the
        // previous bare-value join ("110mm, Wax-Resin, ...") left it
        // ambiguous which figure was which spec.
        $specSummary = $specRows->take(4)
            ->map(fn (array $row) => trim($row['label'].' '.$row['value']))
            ->implode(', ');

        $metaDescription = $specSummary !== ''
            ? __('storefront.seo.product_description_with_specs', [
                'name' => $displayName,
                'specs' => $specSummary,
                'seller' => $this->product->seller->name,
            ])
            : __('storefront.seo.product_description', [
                'name' => $displayName,
                'seller' => $this->product->seller->name,
            ]);

        // Product images first (this is the page's own primary visual
        // content); no dedicated default share image exists yet (same
        // pre-existing gap Home/Catalog\Show already flagged), so this
        // stays null (tag omitted) rather than a placeholder when the
        // product has none of its 0-4 allowed images.
        $ogImage = $this->product->images->isNotEmpty()
            ? Storage::disk('public')->url($this->product->images->first()->path)
            : null;

        $vitrinPrice = $orderedPrices->firstWhere('is_vitrin', true);

        $offers = $vitrinPrice ? array_filter([
            '@type' => 'Offer',
            'url' => $canonicalUrl,
            'price' => (string) $vitrinPrice->price,
            'priceCurrency' => 'UZS',
            // No stock-quantity field exists on products today (see
            // ProductPrice model) — every approved listing is assumed
            // InStock as a simplification, matching Catalog\Show's identical
            // flagged simplification.
            'availability' => 'https://schema.org/InStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            // Seller name only - never the seller's phone/contact (revealed
            // on click by design, anti-scraping).
            'seller' => ['@type' => 'Organization', 'name' => $this->product->seller->name],
        ]) : null;

        $langSuffix = $locale !== $defaultLocale ? '?lang='.$locale : '';

        $productNode = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $displayName,
            'description' => $metaDescription,
            'url' => $canonicalUrl,
            'sku' => (string) $this->product->id,
            'category' => $categoryName !== '' ? $categoryName : null,
            'brand' => ($this->product->brand && $this->product->brand->id !== 1)
                ? ['@type' => 'Brand', 'name' => $this->product->brand->name]
                : null,
            'image' => $this->product->images->map(fn ($image) => Storage::disk('public')->url($image->path))->values()->all() ?: null,
            // Surfaces every filled spec (width, core size, material,
            // compatible printer model, ...) directly in structured data —
            // exactly how B2B auto-ID buyers actually search, mirroring
            // Catalog\Show::productListItemJsonLd()'s identical pattern.
            'additionalProperty' => $specRows->isNotEmpty()
                ? $specRows->map(fn (array $row) => ['@type' => 'PropertyValue', 'name' => $row['label'], 'value' => $row['value']])->values()->all()
                : null,
            'offers' => $offers,
        ], fn ($value) => $value !== null);

        $breadcrumbItems = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('storefront.catalog.breadcrumb_home'), 'item' => url('/').$langSuffix],
        ];

        if ($categorySlug) {
            $breadcrumbItems[] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $categoryName,
                'item' => route('storefront.catalog.show', ['categorySlug' => $categorySlug]).$langSuffix,
            ];
        }

        $breadcrumbItems[] = [
            '@type' => 'ListItem',
            'position' => count($breadcrumbItems) + 1,
            'name' => $displayName,
            'item' => $canonicalUrl,
        ];

        $structuredData = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $breadcrumbItems,
            ],
            $productNode,
        ];

        return view('livewire.storefront.products.show', [
            'orderedPrices' => $orderedPrices,
            'specRows' => $specRows,
            'displayName' => $displayName,
        ])->layout('layouts.storefront', [
            'title' => $pageTitle,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
            'hreflangAlternates' => $hreflangAlternates,
            'ogType' => 'product',
            'ogImage' => $ogImage,
            'structuredData' => $structuredData,
            'navCategories' => $navCategories,
            'bottomBar' => 'product',
        ]);
    }
}
