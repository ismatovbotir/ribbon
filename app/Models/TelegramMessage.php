<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['telegram_contact_id', 'direction', 'body', 'telegram_message_id', 'sent_by'])]
class TelegramMessage extends Model
{
    public function contact(): BelongsTo
    {
        return $this->belongsTo(TelegramContact::class, 'telegram_contact_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
