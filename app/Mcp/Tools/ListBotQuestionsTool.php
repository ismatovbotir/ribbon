<?php

namespace App\Mcp\Tools;

use App\Models\BotAnswer;
use App\Models\BotUnanswered;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('For training the website/Telegram bot: open questions the bot could not answer (most asked first) and the existing answers with their keywords. Use teach-bot to add keywords to an answer.')]
class ListBotQuestionsTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $locales = config('ribbon.locales');

        return Response::json([
            'unanswered' => BotUnanswered::open()->orderByDesc('times')->orderByDesc('last_asked_at')->limit(self::limit($request, 30))->get()
                ->map(fn (BotUnanswered $q) => ['id' => $q->id, 'text' => $q->text, 'language' => $q->locale, 'channel' => $q->channel, 'times_asked' => $q->times])->all(),
            'answers' => BotAnswer::orderBy('name')->get()->map(fn (BotAnswer $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'active' => (bool) $a->is_active,
                'reply_ru' => $a->reply['ru'] ?? null,
                'keywords' => collect($locales)->mapWithKeys(fn ($l) => [$l => $a->keywordsFor($l)])->all(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Max unanswered questions, 1–100 (default 30).'),
        ];
    }
}
