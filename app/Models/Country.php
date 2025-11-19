<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Region;

class Country extends Model
{
    public $guarded = [];
    public function regions()
    {
        return $this->hasMany(Region::class);
    }
}
