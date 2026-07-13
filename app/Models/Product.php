<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'seller_id',
    'category_id',
    'brand_id',
    'name',
    'name_extra',
    'slug',
    'status',
    'moderated_by',
    'moderated_at',
    'rejection_reason',
])]
class Product extends Model
{
    use HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // JSON keyed by locale (config('ribbon.locales')); required in
            // all 3 locales, generated automatically on creation (see
            // bootProduct()/generateUniqueSlug() below) — never
            // admin/seller-editable.
            'slug' => 'array',
            'moderated_at' => 'datetime',
        ];
    }

    /**
     * Pricing invariant: every product must have a `pcs` price row, and it
     * can never be deleted (see ProductPrice::bootProductPrice()). This is
     * enforced here, right after the product itself is persisted, so the
     * invariant holds no matter which code path created the product
     * (Product::create(), a factory, a seeder, Tinker, ...).
     *
     * The row is seeded with a placeholder price of 0.00 and flagged
     * `is_vitrin` (it's the only price row that exists yet, so it's
     * necessarily the storefront default). Callers/UI that want a real pcs
     * price should update this row rather than creating a new one — a
     * second `pcs` row would violate the products.unique(product_id, unit)
     * constraint.
     */
    #[Boot]
    protected static function bootProduct(): void
    {
        // Slug needs to exist on the row being inserted (unlike the pcs
        // price row below, which is a *different* row created after this
        // one persists), so this hooks `creating`, not `created`.
        //
        // The normal seller Create/Edit flow precomputes both `name` and
        // `slug` via Product::composeNameAndSlug() and passes them straight
        // into create()/update(), so this guard means the auto-generation
        // below never actually runs for that flow — it's a fallback for any
        // other creation path (Tinker, future seeders/admin tools) that
        // doesn't supply name/slug itself.
        static::creating(function (Product $product) {
            if ($product->slug) {
                return;
            }

            $categoryName = $product->category->name ?? [];

            $slugs = [];

            foreach (config('ribbon.locales') as $locale) {
                $label = trim(($categoryName[$locale] ?? '').' '.($product->name ?? ''));
                $slugs[$locale] = static::generateUniqueSlug($label, $locale);
            }

            $product->slug = $slugs;
        });

        static::created(function (Product $product) {
            if (! $product->prices()->where('unit', 'pcs')->exists()) {
                $product->prices()->create([
                    'unit' => 'pcs',
                    'qty_in_pcs' => 1,
                    'price' => 0,
                    'is_vitrin' => true,
                ]);
            }
        });
    }

    /**
     * Derive a unique slug from a single pre-composed label, automatically
     * disambiguating against any existing collision for that locale by
     * appending -2, -3, etc. — mirrors Category::generateUniqueSlug()'s
     * exact pattern. `$ignoreId` is a ULID string (not an int), matching
     * products.id's key type; pass the product's own id when recomputing
     * the slug for an existing row so it doesn't collide with itself.
     *
     * The caller is responsible for composing `$label` — see
     * Product::composeNameAndSlug()/composeLabel(), which fold in brand +
     * translated category parameter values + the seller's free-text extra.
     */
    public static function generateUniqueSlug(string $label, string $locale, ?string $ignoreId = null): string
    {
        $base = Str::slug($label);
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where("slug->{$locale}", $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Compose the single-locale display label used for both `name`
     * (default-locale only) and each locale's `slug` entry:
     * [brand name, omitted entirely if brand_id is 1 "No Brand"] +
     * [each filled category parameter's display value, in sort_order] +
     * [the free-text extra suffix], space-joined and trimmed.
     *
     * `$categoryParameters` is a Category's `parameters` relation (in
     * sort_order, with `options` eager-loaded for select types).
     * `$parameterValues` is keyed by category_parameter_id, same shape the
     * seller Create/Edit forms already use: scalar for text/number/
     * select_single, array of option ids for select_multiple.
     *
     * Exposed as its own public method (rather than folded into
     * composeNameAndSlug()) so a live form preview can call it directly
     * without paying for composeNameAndSlug()'s per-locale slug-uniqueness
     * queries on every keystroke-driven render.
     *
     * @param  iterable<CategoryParameter>  $categoryParameters
     * @param  array<int, mixed>  $parameterValues
     */
    public static function composeLabel(?Brand $brand, iterable $categoryParameters, array $parameterValues, ?string $extra, string $locale): string
    {
        $fallbackLocale = config('ribbon.locales')[0];
        $segments = [];

        if ($brand && $brand->id !== 1) {
            $segments[] = $brand->name;
        }

        foreach ($categoryParameters as $parameter) {
            $raw = $parameterValues[$parameter->id] ?? null;
            $isBlank = $raw === null || $raw === '' || (is_array($raw) && $raw === []);

            if ($isBlank) {
                continue;
            }

            $display = match ($parameter->type) {
                // Locale-invariant, matches how seller free-text is already
                // treated elsewhere in this codebase.
                'text' => (string) $raw,
                // No space between the number and its unit, e.g. "110mm".
                // +0 strips trailing zeros/decimal noise from form input.
                'number' => ((string) ((float) $raw + 0)).($parameter->unit ?? ''),
                'select_single' => static::optionLabel($parameter, $raw, $locale, $fallbackLocale),
                'select_multiple' => collect($raw)
                    ->map(fn ($optionId) => static::optionLabel($parameter, $optionId, $locale, $fallbackLocale))
                    ->filter(fn ($label) => $label !== '')
                    ->implode(', '),
                default => '',
            };

            if ($display !== '') {
                $segments[] = $display;
            }
        }

        if ($extra !== null && trim($extra) !== '') {
            $segments[] = trim($extra);
        }

        return trim(implode(' ', $segments));
    }

    /**
     * Look up a single CategoryParameterOption's translated label by id
     * (loose-compared, since form values often arrive as strings) —
     * used by composeLabel() for select_single/select_multiple parameters,
     * which only has an option *id* on hand (raw in-progress form input).
     * Delegates the actual locale-fallback lookup to optionValueLabel() so
     * that rule lives in exactly one place, shared with localizedName().
     */
    protected static function optionLabel(CategoryParameter $parameter, mixed $optionId, string $locale, string $fallbackLocale): string
    {
        return static::optionValueLabel($parameter->options->firstWhere('id', $optionId), $locale, $fallbackLocale);
    }

    /**
     * Resolve a single CategoryParameterOption's translated label, current
     * locale falling back to the default locale, or '' if there's no option
     * at all (unresolved relation, or the id didn't match anything).
     *
     * Shared by composeLabel() (via optionLabel(), which starts from a raw
     * option id) and localizedName() (which already has the
     * CategoryParameterOption model in hand via a persisted
     * ProductParameterValueOption row) so this locale-fallback rule is
     * defined once rather than duplicated.
     */
    protected static function optionValueLabel(?CategoryParameterOption $option, string $locale, string $fallbackLocale): string
    {
        if (! $option) {
            return '';
        }

        return $option->value[$locale] ?? $option->value[$fallbackLocale] ?? '';
    }

    /**
     * Compose the full `name` (default-locale label) + per-locale `slug`
     * pair for a product, from its brand, category parameters, in-progress
     * parameter values, and free-text extra — see composeLabel() for the
     * exact composition rule. Runs each locale's label through
     * generateUniqueSlug() for disambiguation.
     *
     * `$ignoreId` should be the product's own id when recomputing for an
     * existing product (Edit.php), so an unchanged label doesn't collide
     * with the product's own current slug.
     *
     * @param  iterable<CategoryParameter>  $categoryParameters
     * @param  array<int, mixed>  $parameterValues
     * @return array{name: string, slug: array<string, string>}
     */
    public static function composeNameAndSlug(?Brand $brand, iterable $categoryParameters, array $parameterValues, ?string $extra, ?string $ignoreId = null): array
    {
        $locales = config('ribbon.locales');

        $slug = [];

        foreach ($locales as $locale) {
            $label = static::composeLabel($brand, $categoryParameters, $parameterValues, $extra, $locale);
            $slug[$locale] = static::generateUniqueSlug($label, $locale, $ignoreId);
        }

        return [
            'name' => static::composeLabel($brand, $categoryParameters, $parameterValues, $extra, $locales[0]),
            'slug' => $slug,
        ];
    }

    /**
     * Storefront-only counterpart to `name` (which is always composed at
     * the default locale and stored as a single plain string — see
     * composeNameAndSlug(), left untouched by design since admin/seller
     * tools rely on it as-is). Composes the same display label
     * (composeLabel()'s exact rule: brand omitted when brand_id is 1, then
     * each filled category parameter's display value in the category's
     * parameter sort_order, then name_extra) but in an arbitrary requested
     * locale, and — unlike composeLabel(), which reads raw in-progress form
     * state — sources parameter values from this product's *persisted*
     * `parameterValues` rows, since there's no form to read from on the
     * storefront.
     *
     * Callers must eager-load `parameterValues.categoryParameter`,
     * `parameterValues.options.categoryParameterOption`, and `brand` before
     * calling this (e.g. across a product grid) to avoid N+1 queries — this
     * method does not eager-load anything itself.
     */
    public function localizedName(string $locale): string
    {
        $fallbackLocale = config('ribbon.locales')[0];
        $segments = [];

        if ($this->brand && $this->brand->id !== 1) {
            $segments[] = $this->brand->name;
        }

        $values = $this->parameterValues
            ->filter(fn (ProductParameterValue $value) => $value->categoryParameter !== null)
            ->sortBy(fn (ProductParameterValue $value) => $value->categoryParameter->sort_order);

        foreach ($values as $value) {
            $parameter = $value->categoryParameter;

            $display = match ($parameter->type) {
                'text' => (string) $value->value_text,
                'number' => $value->value_number !== null
                    ? ((string) ((float) $value->value_number + 0)).($parameter->unit ?? '')
                    : '',
                'select_single', 'select_multiple' => $value->options
                    ->map(fn (ProductParameterValueOption $option) => static::optionValueLabel($option->categoryParameterOption, $locale, $fallbackLocale))
                    ->filter(fn ($label) => $label !== '')
                    ->implode(', '),
                default => '',
            };

            if ($display !== '') {
                $segments[] = $display;
            }
        }

        if ($this->name_extra !== null && trim($this->name_extra) !== '') {
            $segments[] = trim($this->name_extra);
        }

        return trim(implode(' ', $segments));
    }

    /**
     * The seller who owns this product listing.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * The category this product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The brand this product is assigned to (defaults to the "No Brand"
     * placeholder when the seller doesn't pick a real one).
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * The staff user who moderated this product.
     */
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * The per-unit price rows for this product.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * This product's filled-in category parameter values.
     */
    public function parameterValues(): HasMany
    {
        return $this->hasMany(ProductParameterValue::class);
    }

    /**
     * This product's images, ordered by sort_order — the lowest sort_order
     * image is the product's "primary"/cover image by convention (no
     * separate is_primary column). Capped at 4 images per product, enforced
     * in ProductImage::bootProductImage().
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Approve this product, recording which admin acted and when. Mirrors
     * Seller::approve() exactly, but against products' `moderated_*`
     * column naming instead of sellers' `approved_*`.
     */
    public function approve(User $admin): void
    {
        $this->status = 'approved';
        $this->moderated_by = $admin->id;
        $this->moderated_at = now();
        $this->rejection_reason = null;
        $this->save();
    }

    /**
     * Reject this product with a reason, recording which admin acted and
     * when. moderated_by/moderated_at are set here too, despite the name —
     * they track "who last acted on this listing and when", not literally
     * "who approved it", so a rejection updates them just the same as an
     * approval would. Mirrors Seller::reject().
     */
    public function reject(User $admin, string $reason): void
    {
        $this->status = 'rejected';
        $this->moderated_by = $admin->id;
        $this->moderated_at = now();
        $this->rejection_reason = $reason;
        $this->save();
    }

    /**
     * Block an already-approved (active) product, recording which admin
     * acted and when. Distinct from reject(): rejection is an
     * initial-review decision (status pending -> rejected); suspension is a
     * punitive action against a listing that was already approved and
     * live. Reuses the `rejection_reason` column to store the block reason
     * rather than adding a parallel `suspension_reason` column for what is
     * conceptually the same "why is this listing not active" note. Mirrors
     * Seller::suspend().
     */
    public function suspend(User $admin, string $reason): void
    {
        $this->status = 'suspended';
        $this->moderated_by = $admin->id;
        $this->moderated_at = now();
        $this->rejection_reason = $reason;
        $this->save();
    }
}
