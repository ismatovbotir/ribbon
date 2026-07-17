# 14 — Storefront round 2: bolder price typography & whitespace

Status: ready for `livewire-frontend-engineer` implementation.
Scope: buyer storefront only — `product-card.blade.php`, `home.blade.php`, `catalog/show.blade.php`,
`search.blade.php`, `product-rail.blade.php`, `products/show.blade.php`. Addendum to, not a replacement of,
`docs/design/13-storefront-visual-polish.md` (already shipped) — this doc only specifies the *delta* on top of
what's already implemented. Do not re-apply anything from doc 13 that isn't explicitly touched below.

Reference (per `.claude/design/theme.md`, cited a second time, deliberately — push further, not restructure):
home `https://electro.madrasthemes.com/4x/`, product detail `https://electro.madrasthemes.com/4x/product/moove-l45-2/`.

## 0. Prerequisite — font-weight ceiling fix (blocks every "bolder" change below)

**This must land first.** Everything else in this doc assumes it's done; without it, none of the weight-based
changes below will have any visible effect.

`vite.config.js` currently loads only three weight cuts of the storefront's typeface:

```js
fonts: [
    bunny('Instrument Sans', {
        weights: [400, 500, 600],
    }),
],
```

No `700` face is loaded. Per the CSS font-matching algorithm, a request for `font-weight: 700` (Tailwind's
`font-bold`, used extensively already — every H1 in the shipped doc-13 work, the product card price, etc.) with
no `≥700` face available falls back to the *nearest lighter* face actually present in the family — `600`. Net
effect, today, in the browser: **`font-bold` and `font-semibold` render pixel-identical everywhere on the
storefront.** This is a real, measurable reason the page still reads flatter than the reference even after doc
13 shipped — every "bold" heading and price is secretly capped at semibold weight. Fix:

```js
fonts: [
    bunny('Instrument Sans', {
        weights: [400, 500, 600, 700],
    }),
],
```

Requires a rebuild (`npm run build` / restart `npm run dev`) to regenerate `fonts-manifest.json` and the
`@font-face` CSS — flagging as a build step for whoever implements this, not something to run unprompted.

**Weight ceiling, going forward:** Instrument Sans's published variable range tops out at `700` (Bold) — there
is no `800`/`900` (ExtraBold/Black) cut published for this family. (Verify against bunny.net's actual catalog
at implementation time in case this changes, but treat `700` as the working assumption.) Once the `700` face is
loaded, **`font-bold` is the storefront's hard weight ceiling — never reach for `font-extrabold` or
`font-black` anywhere in the storefront tree.** With no heavier face available, those classes will silently
resolve back down to the same `700` face (same font-matching fallback described above), so they add zero
additional visual weight while implying to the next person reading the markup that a distinction exists that
doesn't. If genuinely heavier display type is wanted later (e.g., a hero numeral treatment), that requires
sourcing a different weight cut or a separate display typeface for that one use — out of scope here.

## 1. Diagnosis delta (on top of doc 13's four causes)

Doc 13 already fixed the radius mismatch, the H1 shrink bug, and gave price its own visual block. What's still
holding the storefront back from the reference's confidence, specifically:

1. **§0 above** — every "bold" element has been secretly rendering at semibold weight since doc 13 shipped.
2. **Every card in every grid still wears a hairline border + `shadow-xs`.** The reference's product cards have
   none — they read as photography floating in whitespace, not as boxed inventory rows. This is the single
   biggest remaining structural gap versus the reference, distinct from (and larger than) the radius fix doc 13
   already made.
3. **The product detail price table treats every row, including the default/vitrin one, at the same
   `text-sm`-inherited size.** Doc 13 added a background tint to flag the vitrin row; it did not give that row's
   *price* any typographic weight of its own, so the page still has no single moment where a number reads as
   "the big number," the way the reference's price line does.

## 2. Price typography

### 2a. Product card — `resources/views/components/storefront/product-card.blade.php`, lines 111–123

**Do not increase the font-size token past `text-2xl` here** — see the worked-out overflow risk below. The
"bolder" lever for cards is §0's weight fix (this is the first time `font-bold` will actually render heavier
than `font-semibold`) plus freeing the price number from sharing its line with the currency label.

Current:
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

New:
```blade
<div class="mt-4 flex items-end justify-between gap-2">
    @if ($vitrinPrice)
        <div>
            <p class="text-2xl leading-none font-bold tracking-tight tabular-nums text-text-primary">
                {{ number_format((float) $vitrinPrice->price) }}
            </p>
            <p class="mt-1 text-xs font-medium tracking-wide text-text-muted uppercase">
                {{ __('storefront.unit.'.$vitrinPrice->unit) }} · UZS
            </p>
        </div>
    @else
        <span></span>
    @endif
</div>
```
Changes: `mt-3` → `mt-4` (more separation from the spec list above, per §3's whitespace push); `UZS` moved off
the number's own line into the caption (merged with the unit label using the exact `uppercase`/`tracking-wide`
treatment the category eyebrow label already uses at line 85, so this isn't a new visual convention — it's
reusing an established one); `tracking-tight` added to the numeral for a denser, more confident digit block.

**Why the numeral itself stays at `text-2xl` (32px), not `text-3xl` (40px):** this component renders inside
three different width contexts — the home rail (`w-48`/`w-56`/`w-64` fixed), and the catalog/search grid, whose
narrowest instance is the mobile `grid-cols-2` layout. On a ~375px viewport with the page's `px-4` container and
the grid's `gap-4`, a 2-column card's own outer width is roughly 155–165px; after the card's `p-4` content
padding, that leaves on the order of 125–135px for the price line. UZS prices commonly format to 6–7 characters
including thousands separators (e.g. `150,000`, `1,250,000`). At Instrument Sans's typical proportions, a
7-character tabular-numeral string at `text-2xl` (32px) already runs close to that available width; at
`text-3xl` (40px) it would reliably overflow or wrap on that narrowest breakpoint. `text-2xl` is the safe
ceiling for this shared component — the actual size increase this task asks for belongs where the layout has
real width margin to support it: §2b below.

**No catalog-specific or search-specific price change is needed** — both pages render this exact component
unchanged (`catalog/show.blade.php` line 210, `search.blade.php` line 81), so this one edit is inherited
everywhere automatically.

### 2b. Product detail price table — `resources/views/livewire/storefront/products/show.blade.php`, lines 143–174

This table has real width to spend (full column width, `min-w-[420px]`, right-aligned numeric columns) — this
is where the "large confident price" moment the reference has on its product page belongs, achieved through the
existing table (per the task brief: the per-unit comparison table is Ribbon-specific and correct to keep, not a
candidate for restructuring into a single reference-style price line).

Table wrapper (line 143): `<div class="mt-5 overflow-hidden rounded-xl border border-border bg-surface-raised">`
→ `<div class="mt-8 overflow-hidden rounded-xl border border-border bg-surface-raised">` — more vertical
separation between the category line above and the price moment below, so the table reads as its own
deliberate beat in the page rather than following immediately on the category line's heels.

Header cells (lines 149–152), all four `<th scope="col" class="p-3 ...">` → `class="p-4 ..."` (padding only,
text classes unchanged).

Body row (lines 157–169) — current:
```blade
<tr class="border-b border-border last:border-b-0 {{ $price->is_vitrin ? 'bg-accent-50' : '' }}">
    <th scope="row" class="p-3 text-left font-medium text-text-primary">
        <span class="inline-flex items-center gap-2">
            {{ __('storefront.unit.'.$price->unit) }}
            @if ($price->is_vitrin)
                <span class="rounded-full bg-accent-50 px-2 py-0.5 text-xs font-medium text-accent-700">{{ __('storefront.product_detail.default_unit_tag') }}</span>
            @endif
        </span>
    </th>
    <td class="p-3 whitespace-nowrap text-text-secondary">{{ number_format($price->qty_in_pcs) }} {{ __('storefront.unit.pcs') }}</td>
    <td class="p-3 text-right font-semibold tabular-nums whitespace-nowrap text-text-primary">{{ number_format((float) $price->price) }} UZS</td>
    <td class="p-3 text-right tabular-nums whitespace-nowrap text-text-secondary">{{ number_format((float) $price->price / max($price->qty_in_pcs, 1)) }} UZS/{{ __('storefront.unit.pcs') }}</td>
</tr>
```
New (padding bump on all cells; price cell gets a two-tier size/weight treatment — the vitrin row's price is
visibly the "big number," every other row's price is bumped one notch above the table's base text so the whole
column reads with more confidence, not just the highlighted row):
```blade
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
```
(`font-semibold` → `font-bold` only becomes a real change once §0 lands; before that fix this edit would be
invisible.) The row growing slightly taller on the vitrin row is expected and fine — table cells default to
middle vertical alignment, so the other three cells in that row simply re-center against the taller price cell,
no manual alignment needed.

**Vertical rhythm around the rest of the info column** — small, additive margin-only bumps so the price
table's new weight doesn't feel cramped against its neighbors (control *styling* itself is unchanged, per doc
13's explicit "no changes" call on the CTA/unit selector — only the outer spacing around that cluster moves):
- Unit selector wrapper (line 177): `<div class="mt-5">` → `<div class="mt-6">`
- Quantity stepper wrapper (line 195): `<div class="mt-4 flex items-center gap-3">` → `<div class="mt-5 flex items-center gap-3">`
- Add-to-request CTA wrapper (line 215): `<div class="mt-5" x-ref="addToRequestAnchor">` → `<div class="mt-6" x-ref="addToRequestAnchor">`
- Seller info/trust block (line 242): `<div class="mt-6 rounded-lg border border-accent-100 bg-accent-50/50 p-4">` → `<div class="mt-8 rounded-lg border border-accent-100 bg-accent-50/50 p-4">`

### 2c. Mobile sticky bar price — `resources/views/livewire/storefront/products/show.blade.php`, lines 316–320

`<p class="text-base font-semibold tabular-nums text-text-primary">` → `<p class="text-lg font-bold tabular-nums text-text-primary">`.
Modest bump only (`text-lg` = 18px in the storefront scope) — this is a fixed-height compact bar sharing space
with the CTA button, not the place for a large price moment, but it should no longer be visually weaker than
the price now established as the page's dominant number.

## 3. Whitespace & border reduction

### 3a. Product card frame — `resources/views/components/storefront/product-card.blade.php`

This is the change doc 13 flagged as a genuine trade-off to revisit ("consider whether some of the border
treatments should become borderless-with-whitespace") — going all the way, per the task brief, to match the
reference's "floats on plain background" card language.

Current (line 59): `class="group relative flex flex-col overflow-hidden rounded-xl border border-border bg-surface-raised shadow-xs transition-all duration-200 hover:border-accent-200 hover:shadow-md"`

New: `class="group relative flex flex-col overflow-hidden rounded-xl bg-surface-raised transition-all duration-200 hover:bg-surface-hover hover:shadow-lg"`

Border and resting `shadow-xs` both removed. Hover now carries two signals instead of the old border-color flip
(there's no border to flip anymore): a background tint (`hover:bg-surface-hover`, reusing the existing
row/item-hover token — no new token invented) plus a lift (`hover:shadow-lg`, up from `shadow-md`, since it now
has to do the *entire* job of signaling "raised" on its own).

**Important technical note for the engineer:** `--color-surface` (page background, `bg-surface` on `<body>`)
and `--color-surface-raised` (this card's own background) currently resolve to the *identical* color
(`var(--color-white)` — see `resources/css/app.css` lines 69/71). That's intentional and matches the reference
exactly — the card's content area has no visible boundary at rest, same as the reference's cards — but it means
the **grid gap is now the only thing separating adjacent cards' text stacks**, so §3b's gap increase is not
optional polish, it's load-bearing for this change to read correctly rather than as an unstyled list.

Image wrapper (line 62): `class="aspect-square overflow-hidden rounded-t-xl bg-surface-subtle p-3"` →
`class="aspect-square overflow-hidden rounded-xl bg-surface-subtle p-3"` — was `rounded-t-xl` (top corners only)
so the image's corners lined up with the now-removed outer border's shape; with no outer border to match, give
the image its own fully-rounded frame so it reads as a discrete floating element, matching the reference's
image treatment.

Image `<img>` hover scale (line 67) and no-image fallback (lines 71–78): unchanged from doc 13.

### 3b. Grid/rail gaps — compensate for the removed card border

- Catalog grid (`catalog/show.blade.php` line 208, and its loading skeleton at line 186):
  `grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 lg:grid-cols-4` → `grid grid-cols-2 gap-4 md:grid-cols-3 md:gap-6 lg:grid-cols-4 lg:gap-8`
- Search grid (`search.blade.php` lines 56 and 79): identical change, same reasoning.
- Home product rail (`product-rail.blade.php` line 8): `gap-4` → `gap-5`.

**Mobile/tablet gaps are deliberately left unchanged** (`gap-4`/`md:gap-6`, not bumped) — the narrowest
breakpoint is exactly where §2a's card-width math is tightest; widening the gap there would eat into the
already-tight price-line width for no real benefit (the reference's roomy editorial gaps are a desktop-scale
effect to begin with). The `lg:gap-8` step delivers the "more generous whitespace" the reference has, at the
breakpoint where there's headroom to spend it.

### 3c. Home page section rhythm — `resources/views/livewire/storefront/home.blade.php`

- Top-level section stack (line 6): `class="flex flex-col gap-10 md:gap-12"` → `class="flex flex-col gap-12 md:gap-16"`.
- Intro heading/paragraph gap (line 16): `class="flex flex-col gap-2"` → `class="flex flex-col gap-3"`.

No change to `x-storefront.hero-carousel` — it's already border-free, image-only, and rounded-2xl (checked the
component directly; nothing to remove there). Its content (headline/CTA overlay, if any) is admin-authored
banner imagery, not page-template markup this doc can restyle.

**Category icon grid (home, lines 51–70) — deliberately left bordered, not touched.** This is a nav-tile
pattern (a clickable jump-to-category chip), not a content/product card — a defined boundary is functionally
appropriate here the way it isn't for a product card, and the task's whitespace ask is scoped to "hero/price/CTA
blocks" and product cards specifically. Flagging explicitly so this isn't read as an oversight.

## 4. Tab-bar for product detail content — investigated, not applied

Checked the actual current markup and the component's data (`App\Livewire\Storefront\Products\Show::render()`,
confirmed via the model too — no `description`/`body`/`content` field exists anywhere on `Product`, matching
CLAUDE.md's "seller-entered product text is a single plain string" model) before deciding this. Below the
gallery/info two-column layout, the page has exactly **one** content section: "Specifications"
(`products/show.blade.php` lines 282–297). There is no Description, no Accessories, no Reviews-equivalent —
nothing to tab *between*. Forcing a tab bar around a single panel is the same anti-pattern the codebase already
correctly avoids elsewhere — `hero-carousel.blade.php`'s own comment states it plainly for a single banner:
"dots/arrows on one slide is a well-known anti-pattern" — a tab strip with one tab is that same anti-pattern.
**Not applying this pattern now.**

Banking the visual spec below in case a second content grouping is ever added to the product model (e.g. an
admin-authored rich-text description field, or a real cross-sell "Compatible Accessories" feature) — at that
point, this is the reference-matching tab treatment to drop in, so a future implementer doesn't have to
reverse-engineer it from the inspiration site again:

```blade
<div class="flex gap-8 border-b border-border" role="tablist">
    <button type="button" role="tab" aria-selected="true" class="border-b-2 border-accent-600 pb-3 text-sm font-semibold text-text-primary">
        {{ __('storefront.product_detail.specifications_heading') }}
    </button>
    <button type="button" role="tab" aria-selected="false" class="border-b-2 border-transparent pb-3 text-sm font-medium text-text-secondary hover:text-text-primary">
        {{ __('storefront.product_detail.description_heading') }}
    </button>
</div>
```
Thin 2px underline on the active tab only, no box/card around the panel content beneath it, generous `gap-8`
between labels — matches the reference exactly. Do not build this now; there is nothing to switch between yet.

## 5. Patterns considered and rejected (round 2 specific — see doc 13 §8 for the full running list)

- **`font-extrabold`/`font-black` anywhere** — considered directly, per this task's "black/near-black weight"
  wording. Rejected: per §0, Instrument Sans has no `800`/`900` face loaded (or, per current best knowledge,
  published at all for this family) — those classes would silently resolve to the same `700` face as
  `font-bold`, adding no visual weight while misleading future readers of the markup. `font-bold` (once §0
  lands) is the storefront's real ceiling.
- **Bumping the product card price numeral to `text-3xl`** — considered directly, per this task's "bigger than
  round 1 landed on" wording. Rejected specifically for the shared `product-card.blade.php` component (worked
  overflow math in §2a); applied instead where the layout has room to support it (§2b, product detail price
  table) and via the weight fix + UZS-line-move, which reads bolder at the same physical size.
- **Fully borderless product cards with no background differentiation at all** (i.e., transparent, not even
  `bg-surface-raised`) — considered as the most literal reading of "float on plain background." Rejected: the
  image wrapper already supplies the "plain light background" the reference has (`bg-surface-subtle`, kept
  unchanged); removing the content area's `bg-surface-raised` on top of that would leave the price/title text
  with zero background token driving its hover-tint behavior (§3a's `hover:bg-surface-hover` reuses
  `bg-surface-raised` as its resting state) and no clean way to signal hover without it.
- **A standalone large "hero price" line inserted above the product detail price table** (mirroring the
  reference's single big price line more literally) — considered, rejected as out of scope for this pass: it
  would duplicate the vitrin row's price (already the same number) as a second, redundant display, which is an
  additive structural change the task explicitly scoped out ("push further... not a restructure"). §2b's
  size/weight treatment of the existing vitrin row is the non-duplicative way to get the same "confident price"
  effect out of markup that already exists.
- Everything in doc 13 §8 (cart iconography, discount/urgency pricing and badges, stock-count urgency, star
  ratings, shipping trust badges, wishlist, checkout add-ons) — still rejected, for the same reasons stated
  there; not re-litigated here.

## 6. Suggested implementation order

1. §0 — font-weight fix in `vite.config.js`, rebuild assets. Blocking; nothing else in this doc is visible
   without it.
2. §3a — product card border removal + hover treatment, and §3b — grid/rail gap compensation. These two ship
   together; the gap change is load-bearing for the border change, not independent polish.
3. §2a — product card price block (UZS line move, `tracking-tight`, `mt-4`).
4. §2b/§2c — product detail price table weight/spacing treatment and mobile sticky bar price bump.
5. §3c — home page section-gap and intro-gap bumps.
6. §4 — no build action; keep the banked spec in mind if/when a second product-detail content section is ever
   added.
