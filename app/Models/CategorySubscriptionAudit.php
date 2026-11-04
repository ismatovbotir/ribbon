<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only audit of every change to categories.requires_subscription. */
#[Fillable(['category_id', 'user_id', 'old_requires_subscription', 'new_requires_subscription', 'created_at'])]
class CategorySubscriptionAudit extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'old_requires_subscription' => 'boolean',
            'new_requires_subscription' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
