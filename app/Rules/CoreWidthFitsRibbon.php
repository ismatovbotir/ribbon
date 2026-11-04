<?php

namespace App\Rules;

use App\Models\CategoryParameter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A ribbon's core can be wider than the ribbon (60 mm ribbon on a 110 mm core)
 * but never narrower.
 */
class CoreWidthFitsRibbon implements ValidationRule
{
    public function __construct(private mixed $ribbonWidth) {}

    /**
     * @param  iterable<CategoryParameter>  $parameters
     * @param  array<int|string, mixed>  $values  keyed by category_parameter_id
     */
    public static function for(iterable $parameters, array $values): self
    {
        $width = collect($parameters)->firstWhere('code', CategoryParameter::CODE_RIBBON_WIDTH);

        return new self($width ? ($values[$width->id] ?? null) : null);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_numeric($value) && is_numeric($this->ribbonWidth) && (float) $value < (float) $this->ribbonWidth) {
            $fail(__('sellers.products.core_width_too_narrow'));
        }
    }
}
