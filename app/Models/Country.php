<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'sort_order'])]
class Country extends Model
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
     * The regions belonging to this country.
     */
    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    /**
     * Sellers whose service territory is directly in this country. Used to
     * block deletion of an in-use country (see Admin\Geography\Countries\Index)
     * — note a country with any regions is already blocked via that check
     * first, since a seller can only reference a region that belongs to this
     * country, but this relation is checked too for a direct, explicit guard.
     */
    public function sellers(): HasMany
    {
        return $this->hasMany(Seller::class);
    }
}
