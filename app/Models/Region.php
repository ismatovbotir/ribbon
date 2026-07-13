<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['country_id', 'name', 'sort_order'])]
class Region extends Model
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
            'sort_order' => 'integer',
        ];
    }

    /**
     * The country this region belongs to.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * The cities belonging to this region.
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /**
     * Sellers whose service territory is directly in this region. Used to
     * block deletion of an in-use region (see Admin\Geography\Regions\Index).
     */
    public function sellers(): HasMany
    {
        return $this->hasMany(Seller::class);
    }
}
