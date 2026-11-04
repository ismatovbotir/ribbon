<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use App\Models\Concerns\FlushesNavMenu;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'is_active', 'requires_subscription', 'sort_order', 'image_path', 'icon', 'guide_article_id'])]
class Category extends Model
{
    use FlushesNavMenu;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // JSON keyed by locale (config('ribbon.locales')); required in
            // all 3 locales, validated at the form-request layer.
            'name' => 'array',
            'slug' => 'array',
            'is_active' => 'boolean',
            'requires_subscription' => 'boolean',
            'sort_order' => 'integer',
            'guide_article_id' => 'integer',
        ];
    }

    /**
     * The active categories for the storefront header nav / catalog
     * mega-menu, in admin-defined order, each carrying an
     * `approved_products_count` — the number of publicly visible
     * (moderation-approved) products, shown next to the category name in
     * the mega-menu. Single source of truth for every storefront page's
     * `$navCategories`, so the count logic can't drift between pages.
     *
     * @return Collection<int, static>
     */
    public static function navList(): Collection
    {
        return static::query()
            ->where('is_active', true)
            ->withCount([
                'products as approved_products_count' => fn ($query) => $query->visibleOnStorefront(),
            ])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * The admin-picked "How to choose" article for the mega menu (spec 22).
     * Only shown while that article is published.
     */
    public function guideArticle(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'guide_article_id');
    }

    /**
     * The products listed under this category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * The category-specific parameters defined for this category (no
     * cross-category reuse — each category defines its own, in full).
     */
    /**
     * Changes the free/paid flag and records who did it. Callers must already
     * have checked the actor is a Super Admin: this flag decides which sellers
     * must pay and immediately changes storefront visibility.
     */
    public function setRequiresSubscription(bool $requiresSubscription, User $by): void
    {
        if ($this->requires_subscription === $requiresSubscription) {
            return;
        }

        DB::transaction(function () use ($requiresSubscription, $by) {
            CategorySubscriptionAudit::create([
                'category_id' => $this->id,
                'user_id' => $by->id,
                'old_requires_subscription' => $this->requires_subscription,
                'new_requires_subscription' => $requiresSubscription,
                'created_at' => now(),
            ]);

            $this->update(['requires_subscription' => $requiresSubscription]);
        });
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(CategoryParameter::class)->orderBy('sort_order');
    }

    /**
     * Articles optionally tagged with this category (many-to-many — an
     * article can also relate to other categories) — used to surface
     * "related articles" on the category's storefront page. See
     * Article::categories()'s docblock for why this can be empty.
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }

    /**
     * Derive a per-locale slug from a name, automatically disambiguating
     * against any existing collision for that locale by appending -2, -3,
     * etc. Slugs are fully system-generated (no admin-editable slug input
     * anywhere) — this is the single source of truth for that generation,
     * used both for the live preview shown while creating a category and
     * for the value actually persisted on save.
     *
     * Slugs are generated once at creation time and are never
     * regenerated on rename (they back URLs), so this is only ever called
     * without an $ignoreId — it has no legitimate "reslug an existing
     * category" caller today, but accepts one for forward-compatibility
     * with any future bulk-import/backfill tooling.
     */
    public static function generateUniqueSlug(string $name, string $locale, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
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
}
