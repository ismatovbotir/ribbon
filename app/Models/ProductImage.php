<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['product_id', 'path', 'sort_order'])]
class ProductImage extends Model
{
    #[Boot]
    protected static function bootProductImage(): void
    {
        // A product may have at most 4 images (the lowest sort_order one is
        // its "primary"/cover image by convention). Enforced here as a real
        // model-layer invariant, not just a UI form-validation nicety.
        static::creating(function (ProductImage $image) {
            if (static::query()->where('product_id', $image->product_id)->count() >= 4) {
                throw new LogicException('A product cannot have more than 4 images.');
            }
        });
    }

    /**
     * The product this image belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
