<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rate extends Model
{
    public $guarded = [];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
