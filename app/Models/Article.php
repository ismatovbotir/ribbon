<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['type', 'title', 'slug', 'excerpt', 'body', 'cover_image_path', 'published_at', 'created_by'])]
class Article extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    public const TYPES = ['news', 'article'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // JSON keyed by locale (config('ribbon.locales')); title/body
            // required in all 3 locales, excerpt optional — validated at
            // the form-request layer.
            'title' => 'array',
            'slug' => 'array',
            'excerpt' => 'array',
            'body' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * The admin user who created this article.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Catalog categories this article relates to (many-to-many — an
     * article can span several categories, e.g. a ribbon-vs-printer
     * compatibility guide) — used only to surface it under each tagged
     * category's "related articles" on the storefront (see
     * Category::articles()). Most educational content isn't
     * category-specific, so this can be empty.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Whether this article is currently visible on the storefront — null
     * `published_at` is a draft; a future `published_at` is scheduled but
     * not live yet. Single source of truth so admin UI and storefront
     * queries can't drift on the definition, same role
     * Banner::isCurrentlyLive() plays for banners.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null && ! $this->published_at->isFuture();
    }

    /**
     * Derive a per-locale slug from a title, automatically disambiguating
     * against any existing collision for that locale by appending -2, -3,
     * etc. — mirrors Category::generateUniqueSlug() exactly. Slugs are
     * fully system-generated (no admin-editable slug input), generated
     * once at creation and never regenerated on rename since they back
     * URLs; accepts $ignoreId only for forward-compatibility with any
     * future re-slug tooling.
     */
    public static function generateUniqueSlug(string $title, string $locale, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
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
