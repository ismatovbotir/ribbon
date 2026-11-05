<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'secret', 'recovery_codes', 'confirmed_at', 'last_used_timestep'])]
#[Hidden(['secret', 'recovery_codes'])]
class UserTwoFactor extends Model
{
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'recovery_codes' => 'encrypted:array',
            'confirmed_at' => 'datetime',
            'last_used_timestep' => 'integer',
        ];
    }
}
