<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only audit of certificate replacements and moderator reviews. */
#[Fillable(['seller_id', 'user_id', 'action', 'created_at'])]
class SellerCertificateAudit extends Model
{
    public const ACTION_REPLACED = 'replaced';

    public const ACTION_REVIEWED = 'reviewed';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
