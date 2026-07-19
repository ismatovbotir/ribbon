<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['chat_id', 'username', 'first_name', 'last_name', 'last_message_at'])]
class TelegramContact extends Model
{
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }

    /**
     * "First Last (@username)" with any missing piece dropped, falling
     * back to the chat_id itself if Telegram never gave this contact a
     * name at all — used everywhere the inbox needs a display label.
     */
    public function displayName(): string
    {
        $name = trim("{$this->first_name} {$this->last_name}");

        if ($name === '') {
            $name = $this->username ? "@{$this->username}" : (string) $this->chat_id;

            return $name;
        }

        return $this->username ? "{$name} (@{$this->username})" : $name;
    }
}
