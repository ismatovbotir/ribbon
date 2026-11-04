<?php

namespace App\Models;

use App\Models\Concerns\FlushesNavMenu;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * CMS page. System pages (non-null `key`) live at fixed paths
 * (/about, /for-sellers, /privacy, /terms; `contact` only supplies the
 * /contact intro); custom pages (null key) resolve through their
 * per-locale JSON `slug` at /p/{slug}. See .claude/docs/design/19.
 */
#[Fillable([
    'key', 'slug', 'title', 'lead', 'body', 'meta_description',
    'is_published', 'show_in_header', 'show_in_footer', 'footer_column',
    'sort_order', 'updated_by',
])]
class Page extends Model
{
    use FlushesNavMenu;

    public const KEYS = ['about', 'for-sellers', 'privacy', 'terms', 'contact'];

    public const FOOTER_COLUMNS = ['company', 'legal'];

    public const MAX_HEADER_PAGES = 3;

    /** Fixed storefront route name per system page key (contact has its own component). */
    private const SYSTEM_ROUTES = [
        'about' => 'storefront.pages.about',
        'for-sellers' => 'storefront.pages.for-sellers',
        'privacy' => 'storefront.pages.privacy',
        'terms' => 'storefront.pages.terms',
        'contact' => 'storefront.contact',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slug' => 'array',
            'title' => 'array',
            'lead' => 'array',
            'body' => 'array',
            'meta_description' => 'array',
            'is_published' => 'boolean',
            'show_in_header' => 'boolean',
            'show_in_footer' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // `key` is immutable once set (fixed URLs and footer links hang off
        // it), and bodies are always sanitized on save so no code path —
        // admin form, seeder, tinker — can persist raw HTML.
        static::saving(function (Page $page) {
            if ($page->exists && $page->isDirty('key') && $page->getOriginal('key') !== null) {
                throw new LogicException('A page key is immutable.');
            }

            if ($page->isDirty('body') && is_array($page->body)) {
                $page->body = array_map(
                    fn ($html) => self::sanitizeBody((string) $html),
                    $page->body,
                );
            }
        });
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Shared by routes, menus and the sitemap.
     */
    public function scopeIsPublished(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_published'), true);
    }

    public function isSystem(): bool
    {
        return $this->key !== null;
    }

    public function isLegal(): bool
    {
        return $this->footer_column === 'legal';
    }

    /**
     * Editable by Super Admin only: legal-column pages (Privacy, Terms).
     * Everyone else needs `pages.manage`; this is the single rule the
     * admin components and tests use.
     */
    public function isEditableBy(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return ! $this->isLegal() && $user->hasAdminPermission('pages.manage');
    }

    /**
     * HtmlSanitizer plus the CMS heading rule: authors get H2/H3 only (the
     * H1 belongs to the page title), so H1 becomes H2 and H4-H6 become H3.
     */
    public static function sanitizeBody(string $html): string
    {
        $clean = HtmlSanitizer::clean($html);

        $clean = preg_replace('#<(/?)h1(\s[^>]*)?>#i', '<$1h2$2>', $clean) ?? $clean;

        return preg_replace('#<(/?)h[4-6](\s[^>]*)?>#i', '<$1h3$2>', $clean) ?? $clean;
    }

    /**
     * Public path for this page in the given locale (without ?lang=).
     * Null when a custom page has no usable slug.
     */
    public function path(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        if ($this->key !== null) {
            return route(self::SYSTEM_ROUTES[$this->key] ?? 'storefront.home', absolute: false);
        }

        $slug = $this->slug[$locale] ?? ($this->slug[config('ribbon.locales')[0]] ?? null);

        return $slug ? route('storefront.pages.show', ['pageSlug' => $slug], false) : null;
    }

    public function url(?string $locale = null): ?string
    {
        $path = $this->path($locale);

        return $path ? url($path) : null;
    }

    public function localized(string $field, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $default = config('ribbon.locales')[0];
        $values = $this->{$field} ?? [];

        return (string) ($values[$locale] ?? ($values[$default] ?? ''));
    }

    /**
     * Per-locale slug, generated like Article::generateUniqueSlug():
     * unique per locale, -2/-3 suffix on collision.
     */
    public static function generateUniqueSlug(string $title, string $locale, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'page';
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
