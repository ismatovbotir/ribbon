<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A question the bot could not answer (text already masked). Staff teach answers from these. */
#[Fillable(['text', 'fingerprint', 'locale', 'channel', 'times', 'last_asked_at', 'resolved_at', 'bot_answer_id'])]
class BotUnanswered extends Model
{
    use MassPrunable;

    /** New questions stop being collected while this many are open. */
    public const MAX_OPEN = 5000;

    protected $table = 'bot_unanswered';

    /**
     * Daily `model:prune`: handled questions after 90 days, and questions asked
     * only once that nobody repeated for 30 days (typos, junk).
     */
    public function prunable(): Builder
    {
        return static::query()->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->whereNotNull('resolved_at')->where('resolved_at', '<', now()->subDays(90)))
            ->orWhere(fn (Builder $q) => $q->whereNull('resolved_at')->where('times', 1)->where('last_asked_at', '<', now()->subDays(30))));
    }

    protected function casts(): array
    {
        return [
            'times' => 'integer',
            'last_asked_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(BotAnswer::class, 'bot_answer_id');
    }
}
