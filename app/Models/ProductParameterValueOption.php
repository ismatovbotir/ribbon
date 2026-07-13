<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// select_single parameters will only ever have one row per
// product_parameter_value_id; select_multiple can have several — see
// ProductParameterValue::options() and the
// product_parameter_value_options migration.
#[Fillable(['product_parameter_value_id', 'category_parameter_option_id'])]
class ProductParameterValueOption extends Model
{
    /**
     * The product parameter value this selected option belongs to.
     */
    public function productParameterValue(): BelongsTo
    {
        return $this->belongsTo(ProductParameterValue::class);
    }

    /**
     * The category parameter option that was selected.
     */
    public function categoryParameterOption(): BelongsTo
    {
        return $this->belongsTo(CategoryParameterOption::class);
    }
}
