<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryTranslation extends Model
{
    public $guarded = [];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
