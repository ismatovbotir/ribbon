<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'is_active', 'sort_order', 'image_path'])]
class Category extends Model
{
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
            'sort_order' => 'integer',
        ];
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
    public function parameters(): HasMany
    {
        return $this->hasMany(CategoryParameter::class)->orderBy('sort_order');
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
