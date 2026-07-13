<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['region_id', 'name', 'sort_order'])]
class City extends Model
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
     * The region this city belongs to.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Sellers whose service territory is directly in this city. Used to
     * block deletion of an in-use city (see Admin\Geography\Cities\Index).
     */
    public function sellers(): HasMany
    {
        return $this->hasMany(Seller::class);
    }
}
