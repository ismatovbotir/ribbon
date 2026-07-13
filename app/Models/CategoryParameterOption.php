<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Only applicable when the parent CategoryParameter's type is
// select_single or select_multiple — not enforced here, see
// CategoryParameter::options() and the category_parameter_options migration.
#[Fillable(['category_parameter_id', 'value', 'sort_order'])]
class CategoryParameterOption extends Model
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
            'value' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The parameter this option belongs to.
     */
    public function categoryParameter(): BelongsTo
    {
        return $this->belongsTo(CategoryParameter::class);
    }
}
