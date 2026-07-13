<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Only one of value_text/value_number/options() is populated, depending on
// the linked CategoryParameter's type — see bootProductParameterValue()
// below and the product_parameter_values migration.
#[Fillable(['product_id', 'category_parameter_id', 'value_text', 'value_number'])]
class ProductParameterValue extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:3',
        ];
    }

    /**
     * Normalize value_text/value_number against the linked
     * CategoryParameter's type on every save, mirroring
     * ProductPrice::bootProductPrice()'s pattern of enforcing invariants
     * that can't be expressed as a DB CHECK constraint. Only text/number
     * types are handled here — select_single/select_multiple store their
     * values in the separate product_parameter_value_options table via
     * options(), so both columns are cleared for those types.
     */
    #[Boot]
    protected static function bootProductParameterValue(): void
    {
        static::saving(function (ProductParameterValue $value) {
            $type = $value->categoryParameter?->type;

            if ($type === 'number') {
                $value->value_text = null;
            } elseif ($type === 'text') {
                $value->value_number = null;
            } else {
                // select_single / select_multiple (or an unresolved
                // relation): neither column applies, values live in
                // options() instead.
                $value->value_text = null;
                $value->value_number = null;
            }
        });
    }

    /**
     * The product this parameter value belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The category parameter this value is filling in.
     */
    public function categoryParameter(): BelongsTo
    {
        return $this->belongsTo(CategoryParameter::class);
    }

    /**
     * The selected option row(s) for this value. Only meaningful when
     * `categoryParameter->type` is select_single (exactly one row) or
     * select_multiple (one or more rows).
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductParameterValueOption::class);
    }
}
