<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'name', 'type', 'unit', 'is_required', 'is_filterable', 'sort_order'])]
class CategoryParameter extends Model
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
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The category this parameter belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The pick-list options for this parameter. Only meaningful when
     * `type` is select_single or select_multiple.
     */
    public function options(): HasMany
    {
        return $this->hasMany(CategoryParameterOption::class);
    }
}
