<?php

namespace App\Models;

use App\Models\Concerns\FlushesNavMenu;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'code', 'name', 'type', 'unit', 'is_required', 'is_filterable', 'sort_order'])]
class CategoryParameter extends Model
{
    public const CODE_RIBBON_WIDTH = 'ribbon_width';

    public const CODE_RIBBON_CORE_WIDTH = 'ribbon_core_width';

    /** Ribbon Type (Wax / Wax-resin / Resin): rows of the compatibility matrix. */
    public const CODE_RIBBON_TYPE = 'ribbon_type';

    /** TT label Material: columns of the compatibility matrix. */
    public const CODE_LABEL_MATERIAL = 'label_material';

    /** TT label Width: must not exceed the ribbon width to be compatible. */
    public const CODE_LABEL_WIDTH = 'label_width';

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
