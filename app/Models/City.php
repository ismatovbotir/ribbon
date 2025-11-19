<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Region;

class City extends Model
{
    public $guarded = [];
    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
