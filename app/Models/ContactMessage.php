<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's /contact (or, in Phase 2, "Can't find it?") message.
 * Buyer PII: visible to Super Admin only, never written to logs or
 * analytics events.
 */
#[Fillable(['source', 'phone', 'message', 'locale', 'utm', 'referrer', 'status', 'handled_by', 'handled_at'])]
class ContactMessage extends Model
{
    public const SOURCES = ['contact', 'cant_find'];

    public const STATUSES = ['new', 'handled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'utm' => 'array',
            'handled_at' => 'datetime',
        ];
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function markHandled(User $by): void
    {
        $this->forceFill([
            'status' => 'handled',
            'handled_by' => $by->id,
            'handled_at' => now(),
        ])->save();
    }
}
