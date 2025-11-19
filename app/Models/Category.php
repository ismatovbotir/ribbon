<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\CategoryTranslation;

class Category extends Model
{
    public $guarded = [];
    public function companies()
    {

        return $this->belongsToMany(Company::class);
    }
    public function translations()
    {
        return $this->hasMany(CategoryTranslation::class);
    }
    public function translate($lang = 'uz')
    {
        return $this->translations()->where('lang', $lang)->first();
    }
}
