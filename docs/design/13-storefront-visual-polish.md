# 13 — Storefront visual polish: design review & improvement spec

Status: ready for `livewire-frontend-engineer` implementation.
Scope: buyer storefront only (home, product card, catalog/search grids, product detail). No admin/seller changes.
Companion docs referenced below (`08`, `09`, `10`, `11`, `12`) are cited elsewhere in the codebase but do not
exist on disk yet — this doc does not attempt to backfill them; it stands alone as the current source of truth
for the changes below, scoped to this review.

## 0. Diagnosis — why it currently reads "plain"

Four concrete, fixable causes, in order of leverage:

1. **Systemic radius mismatch.** The storefront's own design tokens (`resources/css/app.css`) explicitly document
   that `radius-xs`/`radius-sm` (3–4px) are the *admin/seller* businesslike sizes, and that storefront should
   "rarely reach for xs/sm" in favor of `radius-lg`/`xl`/`2xl`. In practice the storefront views don't follow
   this: `rounded-sm` appears 11× in `layouts/storefront.blade.php`, 3× in `catalog/show.blade.php`, 2× in
   `products/show.blade.php` — on the search input, catalog-menu trigger, buttons, selects, filter triggers.
   This is the single biggest reason the storefront currently *feels* like an internal tool wearing an orange
   coat rather than a distinct, roomier retail surface — it's literally using the other two panels' corner
   radius almost everywhere. Fixed in §1 below.
2. **A real token bug was shrinking the home page's H1 on desktop.** Fixed directly in this pass (see §2) —
   no view change needed for this specific fix.
3. **Price is present but not dominant** on the product card — it's the same visual weight as the title, with
   no separation from the unit text next to it.
4. **No large-format, high-confidence visual moment anywhere on the storefront** — every section (hero aside)
   uses the same modest text sizes and thin borders; nothing signals "this is the marketing-grade surface"
   the way the *hero carousel* already correctly does.

## 1. Global: radius correction (apply everywhere, all files below)

Wherever a storefront view currently uses `rounded-sm` on interactive chrome — buttons, text inputs, `<select>`,
filter/sort triggers, the mobile filters button, the catalog mega-menu trigger — change it to `rounded-lg`.
Leave `rounded-full` alone (already correctly used for pill filters, the unit selector, carousel dots, badges)
and leave `rounded-xl`/`rounded-2xl` alone (already correct for cards/images/banners). This is a global
find-and-replace within the storefront view tree, not a one-off:

- `resources/views/layouts/storefront.blade.php` — search input (desktop + mobile overlay), catalog-menu
  trigger button, mobile off-canvas nav links, locale link padding boxes stay text-only (no radius change needed
  there).
- `resources/views/livewire/storefront/catalog/show.blade.php` — mobile filters trigger button, sort `<select>`,
  "Apply filters" button (`bg-accent-600` button at the bottom of the mobile sheet already uses `rounded-sm` —
  bump to `rounded-lg`).
- `resources/views/livewire/storefront/products/show.blade.php` — quantity stepper wrapper, any `rounded-sm`
  found there.
- Any new storefront markup written going forward (search page, future pages) should default to `rounded-lg`
  for standard interactive chrome, per the token doc's own guidance in `app.css`.

## 2. Token fix already applied — `resources/css/app.css`

Done in this pass, no further action needed. Root cause: the storefront scope overrides `--text-xs` through
`--text-2xl` but historically left `--text-3xl`/`--text-4xl` at Tailwind's untouched defaults (30px/36px),
which are *smaller* than the storefront's own `--text-2xl` override (32px). Home's only H1
(`resources/views/livewire/storefront/home.blade.php:17`, `text-2xl font-semibold md:text-3xl`) was therefore
*shrinking* from mobile (32px) to desktop (30px) — the opposite of the intended effect. Added:

```css
--text-3xl: 2.5rem;  /* 40px — home H1 desktop tier, section-level hero headings */
--text-3xl--line-height: 3rem;
--text-4xl: 3rem;    /* 48px — reserved: large hero banner overlay headline, if ever needed */
--text-4xl--line-height: 3.5rem;
```

This is scoped to `.storefront` only — admin/seller never use `text-3xl`+ (documented ceiling is `text-2xl` for
KPI numbers), so there is no cross-panel collision. No `.blade.php` file needs to change for this fix; the
existing `text-2xl md:text-3xl` class string on the home H1 now resolves correctly (32px → 40px).

## 3. Home page — `resources/views/livewire/storefront/home.blade.php`, `app/Livewire/Storefront/Home.php`

**H1 (line 17):** bump weight for the "confident hero typography" the inspiration site does well —
`text-2xl font-semibold text-text-primary md:text-3xl` → `text-2xl font-bold tracking-tight text-text-primary md:text-3xl`.

**Intro paragraph (line 20):** currently caps at `md:text-base` (16px) even at desktop — give it one more step
of room: `text-sm text-text-secondary md:text-base` → `text-base text-text-secondary md:text-lg`.

**Section H2s ("Categories", "Recently added" — lines 38, 99):** match the H1's new weight for consistency:
`text-xl font-semibold` → `text-xl font-bold tracking-tight`.

**Category icon grid (lines 51–70) — make it read as the circular-icon nav pattern it's reaching for:**
Current markup puts a square `rounded-md` image inside a bordered square card. Change to an actual circular
badge, matching the inspiration's category nav idea:
- Card: `class="flex flex-col items-center gap-2 rounded-xl border border-border bg-surface-raised p-4 text-center shadow-xs transition-shadow hover:shadow-sm"` →
  `class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface-raised p-5 text-center shadow-xs transition-all hover:-translate-y-0.5 hover:border-accent-200 hover:shadow-sm"`
- Image wrapper: replace the plain `<img class="h-12 w-12 rounded-md object-cover">` with a circular frame:
  `<span class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-surface-subtle ring-1 ring-border transition-colors group-hover:ring-accent-200"><img ... class="h-full w-full object-cover"></span>`
- Apply the same `h-16 w-16 rounded-full` treatment to the no-image fallback `<span>` (line 61–65).
- Label: `text-sm font-medium` → `text-sm font-semibold` for stronger hierarchy against the circle above it.
- Grid gap: `gap-3` → `gap-4`.

**Recently added products (lines 97–106) — convert to a horizontal rail, not a static grid.** This is the one
place on the storefront where the inspiration's rail pattern is a genuine improvement: `Home.php` already caps
this query at 8 items (`->limit(8)`), which is an awkward count for a static grid (uneven last row on most
breakpoints) and a perfect count for a rail. **Do not** apply this to catalog or search — those need exhaustive,
paginated browsing, which a rail actively works against; this is a home-page-only, "what's new" pattern.

New component: `resources/views/components/storefront/product-rail.blade.php`
```blade
@props(['products'])

<div class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:-mx-6 md:px-6">
    @foreach ($products as $product)
        <div class="w-48 shrink-0 snap-start sm:w-56 lg:w-64" wire:key="rail-product-{{ $product->id }}">
            <x-storefront.product-card :product="$product" :show-category="true" />
        </div>
    @endforeach
</div>
```
Notes for the engineer:
- The `-mx-4 ... px-4` (and `md:-mx-6 ... md:px-6`) trick lets the rail's scroll track bleed to the viewport
  edge on mobile while the rest of the page stays inside `layouts/storefront.blade.php`'s `max-w-7xl px-4/px-6`
  container — otherwise the last card is flush against the container edge with no "peek" affordance signaling
  there's more to scroll.
- No JS/Alpine state needed — this is native scroll-snap, works identically to the existing hero carousel's
  touch handling but simpler (no autoplay, no dots — a rail isn't a slideshow).
- Reuses `<x-storefront.product-card>` completely unchanged; only the wrapping width/snap classes are new.
- **"View all" link — deliberately omitted for now.** The task's inspiration reference pairs rails with a
  "View all" link, but there is currently no cross-category "all/newest products" catalog route to point it at
  (catalog pages are per-category; search's empty-query state intentionally shows nothing, per
  `App\Livewire\Storefront\Search::matchingProductsQuery()`). Do not invent a link to a route that doesn't
  exist. Flagging as a natural follow-up if/when a cross-category "new arrivals" listing route is added — at
  that point add a `<a href="..." class="text-sm font-medium text-accent-700 hover:underline">{{ __('...') }}</a>`
  to the right side of the section's `<h2>` row.

Update `home.blade.php` lines 100–104 to use the new component instead of the static grid:
```blade
<div class="mt-4">
    <x-storefront.product-rail :products="$recentProducts" />
</div>
```

## 4. Product card — `resources/views/components/storefront/product-card.blade.php`

This is the single most-repeated component on the storefront (home rail, catalog grid, search grid) — changes
here have the highest leverage of anything in this doc.

**Price hierarchy (lines 111–120) — make price the dominant secondary element, per the task's brief.** Currently
price (`text-xl font-bold`, 20px) and unit (`text-sm`, inline sibling) compete at similar visual weight with the
title (`text-lg`, 18px) just above. Restructure to a stacked block with the unit demoted to a caption:
```blade
<div class="mt-3 flex items-end justify-between gap-2">
    @if ($vitrinPrice)
        <div>
            <p class="text-2xl leading-none font-bold tabular-nums text-text-primary">
                {{ number_format((float) $vitrinPrice->price) }}
                <span class="text-base font-semibold text-text-secondary">UZS</span>
            </p>
            <p class="mt-1 text-xs text-text-muted">{{ __('storefront.unit.'.$vitrinPrice->unit) }}</p>
        </div>
    @else
        <span></span>
    @endif
</div>
```
This uses the now-fixed `text-2xl` (32px) tier so the price genuinely reads as the card's second-loudest
element after the product image, ahead of the title.

**Consolidate the brand/seller lines (lines 90–96).** Today brand and "Sold by X" render as two separate
`text-xs` lines stacked above the title, plus the optional category eyebrow — up to three lines of small gray
text before you even reach the title. Merge brand + seller into one line so the card's vertical rhythm favors
the title and price instead:
```blade
@if (($product->brand && $product->brand->id !== 1) || $product->seller)
    <p class="text-xs text-text-secondary">
        @if ($product->brand && $product->brand->id !== 1){{ $product->brand->name }}@endif
        @if ($product->brand && $product->brand->id !== 1 && $product->seller) · @endif
        @if ($product->seller){{ __('storefront.product_card.sold_by', ['seller' => $product->seller->name]) }}@endif
    </p>
@endif
```

**Card frame + hover treatment (line 59):** the current hover state (`shadow-xs` → `shadow-sm`) is too subtle
to register. Also add an image scale-in on hover for the "alive" feel the inspiration's cards have (purely
decorative CSS, not a consumer-cart pattern):
`class="group relative flex flex-col overflow-hidden rounded-xl border border-border bg-surface-raised shadow-xs transition-shadow hover:shadow-sm"` →
`class="group relative flex flex-col overflow-hidden rounded-xl border border-border bg-surface-raised shadow-xs transition-all duration-200 hover:border-accent-200 hover:shadow-md"`

Image wrapper (line 62) already has `overflow-hidden` — add the scale transform to the `<img>` itself (line
64–69): `class="h-full w-full object-contain"` → `class="h-full w-full object-contain transition-transform duration-300 group-hover:scale-105"`.

**Quick-add ("+ Add") button (lines 130–146) — recommend removing from the card for now, not just restyling.**
This is a UI/UX call, flagged explicitly because it's a genuine trade-off: the button is visually implemented
as a normal, live-looking button (solid border, opaque background, hover-capable styling) but is `disabled`
and wired to nothing (per the component's own comment, pending task #19's selection/offer-request wiring). A
permanently-inert button that *looks* interactive on every single product card, across every grid on the site,
reads as broken far more than a card with no secondary action — and directly reinforces the "unfinished/plain"
impression this whole review is trying to fix. Recommendation: remove the block entirely until the real
add-to-selection logic ships, then reintroduce it already styled as a genuinely live control (the
`title="…add_coming_soon"` affordance is a reasonable stopgap otherwise, but should visually read as a ghost/
placeholder, not a normal button, if it stays — e.g., dashed border + `opacity-50`, not solid border +
`opacity-90` as today). This is ultimately a product-priority call outside pure visual design, so implement
whichever the task owner prefers, but do not ship the current "looks live, does nothing" state unchanged.

## 5. Catalog page — `resources/views/livewire/storefront/catalog/show.blade.php`

**Category header presence when there's no admin-authored banner.** `$categoryTopBanner` (rendered lines 55–70)
is optional per category — most categories likely won't have one set. When it's absent, the page currently
jumps straight from a plain breadcrumb into a bare H1 with no visual weight at all. Add a fallback tinted band,
rendered only in the banner's absence so the two treatments never double up:
```blade
@if ($categoryTopBanner)
    {{-- existing banner markup, unchanged --}}
@else
    <div class="mt-6 rounded-2xl bg-accent-50 p-6 md:p-8">
        {{-- category intro block (the existing <div class="mt-2 max-w-3xl ..."> content, lines 40–51) moves here --}}
    </div>
@endif
```
Concretely: move the existing intro `<div>` (lines 40–51, the count/specs paragraph block) so it renders
*inside* this new `bg-accent-50 rounded-2xl` wrapper only when there's no banner; when a banner exists, leave
the intro block exactly where it is today (directly under the H1, no tint wrapper) since the banner image
already supplies the visual anchor. The H1 itself (line 25) stays outside either branch, unchanged in position.

**Radius fixes:** per §1 — mobile filters trigger button (~line 98), sort `<select>` (~line 118), mobile
"Apply filters" button (~line 233): all `rounded-sm` → `rounded-lg`.

No other structural changes recommended here — the sidebar-filter + grid layout, applied-filter chips, and
empty/loading states are already sound patterns; leave them as-is.

## 6. Search page — `app/Livewire/Storefront/Search.php` exists; `resources/views/livewire/storefront/search.blade.php` does not exist yet

This view hasn't been built yet (confirmed: no file at that path despite the component referencing it). Spec
for whoever builds it, since the task calls out that it "shares most markup patterns with catalog":

- **Result grid, skeleton, and empty states:** reuse `catalog/show.blade.php`'s exact classes verbatim —
  `grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 lg:grid-cols-4` for the grid, `<x-storefront.product-card-skeleton />` ×8
  for the loading state, and the same `rounded-xl border border-dashed border-border p-10 text-center` empty-state
  card shape (with copy specific to "no results for '{query}'" vs. catalog's "no products in this category").
- **"No query yet" state** (`$hasQuery === false`): a centered prompt using the same empty-state card shell
  (`rounded-xl border border-dashed border-border p-10 text-center`) with a search-glyph icon (reuse the same
  `<svg>` magnifying-glass path already used in the header search input/button) and copy inviting the buyer to
  type a search term — this is a distinct state from "0 results for a real query" and should read differently
  (neutral/inviting, not "empty result").
- **Category filter row** (`$filterCategories`, only rendered when `count($facetCounts) > 1` per `Search.php`):
  render as a horizontal row of pill toggle chips, not catalog's full sidebar — `Search.php`'s own docblock
  already specifies this scope cut. Reuse the exact pill visual language already established by the product
  detail page's unit selector (`resources/views/livewire/storefront/products/show.blade.php` lines 179–191)
  for consistency: `rounded-full border border-border px-3 py-1.5 text-sm font-medium` with active state
  `bg-accent-600 text-white border-accent-600` and inactive `text-text-secondary hover:text-text-primary`, one
  chip per category with its facet count appended, e.g. `{{ $category->name[...] }} ({{ $facetCounts[$category->id] }})`.
  `wire:click="toggleCategory({{ $category->id }})"` per `Search.php`'s existing method.
- **Page title/H1:** `text-2xl font-bold tracking-tight` for consistency with the H1 weight change elsewhere
  in this doc (`{{ __('storefront.seo.search_title_with_query', [...]) }}`-style copy, or a neutral "Search"
  heading in the no-query state).

## 7. Product detail page — `resources/views/livewire/storefront/products/show.blade.php`

**H1 (line 110):** `text-2xl font-semibold` → `text-2xl font-bold tracking-tight`, matching the weight change
applied elsewhere (size itself is correct already — this is the page-title tier, no token issue here since it
doesn't cross the 2xl/3xl boundary).

**Price table — highlight the vitrin row (lines 156–170).** Today the default/vitrin unit is marked only by a
small badge in its own cell; the row itself has no visual distinction from the others, so a buyer scanning the
table has to read every row's badge column to find the default. Add a background tint to the row:
`<tr class="border-b border-border last:border-b-0">` → `<tr class="border-b border-border last:border-b-0 {{ $price->is_vitrin ? 'bg-accent-50' : '' }}">`.
Keep the existing badge too (redundant signals are fine/good for scannability, not a conflict).

**Specifications list — zebra striping for long spec sheets (lines 288–295).** Auto-ID category parameter sets
can run long (width, core size, material, compatible printer models, etc.) — add alternating row tint so a
buyer can track a row across the label/value columns without losing their place:
```blade
@foreach ($specRows as $row)
    <div class="flex flex-col gap-1 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4 {{ $loop->even ? 'bg-surface-subtle/60' : '' }}">
        <dt class="text-sm text-text-secondary">{{ $row['label'] }}</dt>
        <dd class="text-sm font-medium text-text-primary sm:text-right">{{ $row['value'] }}</dd>
    </div>
@endforeach
```

**Seller info block (lines 242–277) — give it a distinct "trust/contact" visual identity**, since this is the
literal implementation of the "or call the seller directly" alternative to the on-page CTA and currently blends
into the page as a plain gray box indistinguishable from any other muted panel: `bg-surface-subtle p-4 rounded-lg`
→ `bg-accent-50/50 border border-accent-100 p-4 rounded-lg`. Keep everything else in that block unchanged
(logo, name, territory line, phone link) — this is a background/border tint only, not a restructure.

**Add-to-request CTA (lines 215–228) and unit selector (lines 176–192): no changes.** These are already
correctly the most visually dominant interactive elements on the page (full-width-on-mobile `h-12 text-lg
font-semibold` button; clear pill-group unit selector) — called out explicitly so the engineer doesn't "fix"
something that isn't broken.

**Radius fixes:** per §1 — quantity stepper wrapper (line 197) and its focus/border chrome: `rounded-sm` → `rounded-lg`.

## 8. Consumer-retail patterns considered and explicitly rejected

Per the task brief, called out explicitly rather than silently adopted or silently ignored:

- **"Buy Now" / cart iconography** — never. The header's selection indicator already correctly uses a
  clipboard/list glyph (`layouts/storefront.blade.php` lines 229–233), not a cart icon — kept as-is.
- **Slashed/strikethrough pricing, "Save X%" badges, countdown/urgency timers** — never. Ribbon's pricing model
  is fixed unit-based pricing per `CLAUDE.md` (no promotional/discount concept exists in the data model at
  all), so this would require inventing pricing semantics that don't exist, not just a visual treatment.
- **"Only N left in stock" urgency messaging** — never. No inventory/stock-quantity field exists on
  `ProductPrice` (confirmed in `App\Livewire\Storefront\Catalog\Show::productListItemJsonLd()`'s own comment:
  every approved listing is assumed `InStock` as a simplification) — an urgency claim here would be
  fabricated, not real.
- **Star ratings / review counts on cards or the detail page** — never. No reviews/ratings model exists;
  B2B trust here is built through the seller info block (company name, territory, direct phone), not
  consumer-style aggregate ratings.
- **Shipping/warranty trust badges, "free shipping over $X"** — never. No shipping/fulfillment model exists on
  the platform; delivery terms are negotiated directly between buyer and seller off-platform.
- **Wishlist / heart icon** — tempting (it's a common, low-risk, cart-adjacent pattern many storefront kits
  ship by default) but deliberately excluded: buyers never have accounts or a persistent session worth
  building a cross-visit wishlist on top of (`CLAUDE.md`: "Buyers never register"). The existing Commercial
  Offer selection (clipboard icon + count badge, `OfferSelectionService`) already serves the single-session
  "things I'm interested in right now" role a wishlist would otherwise duplicate.
- **Gift wrapping / consumer checkout add-ons** — never applicable; there is no checkout at all.

Patterns adopted from the inspiration, and why they're safe for this model (all covered in detail above):
confident large hero typography (§2 token fix + font-bold bumps), image-forward cards with a dominant price
(§4), horizontal product rail for the home "recently added" section only, not catalog/search (§3), and circular
category-icon navigation, extending the pattern the home page already has (§3).

## 9. Suggested implementation order

1. Radius fixes (§1) — mechanical, low-risk, highest visual leverage per line of diff.
2. Product card price hierarchy + hover treatment + quick-add decision (§4) — repeated on every page.
3. Home page: H1 weight, category circles, product rail (§3).
4. Product detail: vitrin row highlight, spec zebra striping, seller block tint (§7).
5. Catalog category header band (§5).
6. Build `search.blade.php` per §6 (net-new file, not a revision).
