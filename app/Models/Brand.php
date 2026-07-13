<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['name', 'logo_path'])]
class Brand extends Model
{
    #[Boot]
    protected static function bootBrand(): void
    {
        // The "No Brand" placeholder is the products.brand_id default
        // (see the create_products_table migration) — it must never be
        // deletable, regardless of whether the DB's FK constraint would
        // also happen to block it via existing product references.
        static::deleting(function (Brand $brand) {
            if ($brand->name === 'No Brand') {
                throw new LogicException('The "No Brand" placeholder cannot be deleted; it is the default brand for every product.');
            }
        });
    }

    /**
     * The products assigned to this brand.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
