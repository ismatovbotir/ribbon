<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'image_path', 'mobile_image_path', 'link_url', 'placement', 'category_id', 'sort_order', 'is_active', 'starts_at', 'ends_at', 'created_by'])]
class Banner extends Model
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
            'title' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * The admin user who created this banner.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The category this banner is targeted to, if any. Null means
     * generic/sitewide (valid for every placement, and also a valid
     * "show on every category page" fallback for `category_top`).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Whether the banner should actually be shown right now. `is_active`
     * alone doesn't capture the scheduling window — this is the single
     * source of truth for combining it with `starts_at`/`ends_at`, so
     * admin UI and storefront code call this rather than re-deriving the
     * same date logic ad hoc. When both dates are null, the banner simply
     * follows `is_active`.
     */
    public function isCurrentlyLive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
