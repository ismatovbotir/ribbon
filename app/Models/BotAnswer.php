<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One thing staff taught the rule-based chat bot (no AI): reply text,
 * trigger keywords and buttons, all per locale. Matching lives in
 * App\Services\Bot\BotEngine so the website and Telegram share it.
 */
#[Fillable(['key', 'name', 'reply', 'keywords', 'buttons', 'is_start', 'is_active', 'updated_by'])]
class BotAnswer extends Model
{
    /** Button actions staff can pick. */
    public const ACTIONS = ['answer', 'finder', 'category', 'article', 'url', 'lead', 'handoff', 'start'];

    public const MAX_BUTTONS = 8;

    public const MAX_KEYWORDS = 40;

    protected function casts(): array
    {
        return [
            'reply' => 'array',
            'keywords' => 'array',
            'buttons' => 'array',
            'is_start' => 'boolean',
            'is_active' => 'boolean',
            'hits' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function start(): ?self
    {
        return static::active()->where('is_start', true)->orderBy('id')->first();
    }

    /** Make this the only start answer. */
    public function makeStart(): void
    {
        DB::transaction(function () {
            static::where('id', '!=', $this->id)->where('is_start', true)->update(['is_start' => false]);
            $this->forceFill(['is_start' => true])->save();
        });
    }

    public function replyFor(string $locale): string
    {
        $default = config('ribbon.locales')[0];

        return (string) (($this->reply[$locale] ?? '') ?: ($this->reply[$default] ?? ''));
    }

    /** @return list<string> */
    public function keywordsFor(string $locale): array
    {
        return array_values(array_filter((array) ($this->keywords[$locale] ?? []), fn ($k) => is_string($k) && trim($k) !== ''));
    }
}
