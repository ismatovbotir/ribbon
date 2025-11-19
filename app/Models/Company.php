<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\Category;
use App\Models\User;


class Company extends Model
{
    use HasUuids;

    public $guarded = [];

    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }
    public function rates()
    {
        return $this->hasMany(Rate::class);
    }
    public function currentRate()
    {
        return $this->rates()->latest()->first();
    }
}
