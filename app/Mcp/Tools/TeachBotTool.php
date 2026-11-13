<?php

namespace App\Mcp\Tools;

use App\Models\BotAnswer;
use App\Models\BotUnanswered;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Teach the bot: add keywords (1–3 words each) in one language to an existing answer, and optionally mark an unanswered question as solved by it. Or only dismiss a question (answer_id omitted, dismiss=true). Ask the owner before calling.')]
class TeachBotTool extends RibbonTool
{
    protected function run(Request $request, User $admin): Response
    {
        $data = $request->validate([
            'answer_id' => ['nullable', 'integer'],
            'language' => ['required_with:answer_id', 'nullable', 'in:'.implode(',', config('ribbon.locales'))],
            'keywords' => ['required_with:answer_id', 'array', 'max:10'],
            'keywords.*' => ['string', 'min:2', 'max:60'],
            'question_id' => ['nullable', 'integer'],
            'dismiss' => ['nullable', 'boolean'],
        ]);

        $question = isset($data['question_id']) ? BotUnanswered::find($data['question_id']) : null;

        if (isset($data['question_id']) && ! $question) {
            return Response::error('No unanswered question with id '.$data['question_id'].'.');
        }

        if (! isset($data['answer_id'])) {
            if (! $question || empty($data['dismiss'])) {
                return Response::error('Give answer_id + language + keywords, or question_id with dismiss=true.');
            }

            $question->update(['resolved_at' => now()]);
            $this->audit($admin, 'bot-dismiss', ['question_id' => $question->id]);

            return Response::text('Question dismissed.');
        }

        $answer = BotAnswer::find($data['answer_id']);

        if (! $answer) {
            return Response::error('No bot answer with id '.$data['answer_id'].'.');
        }

        $locale = $data['language'];
        $keywords = $answer->keywords ?? [];
        $merged = collect([...($keywords[$locale] ?? []), ...$data['keywords']])
            ->map(fn ($k) => trim(preg_replace('/\s+/u', ' ', $k) ?? ''))
            ->filter(fn ($k) => $k !== '')
            ->unique(fn ($k) => mb_strtolower($k))
            ->values();

        if ($merged->count() > BotAnswer::MAX_KEYWORDS) {
            return Response::error('An answer can have at most '.BotAnswer::MAX_KEYWORDS.' keywords per language.');
        }

        $keywords[$locale] = $merged->all();
        $answer->update(['keywords' => $keywords, 'updated_by' => $admin->id]);

        $question?->update(['resolved_at' => now(), 'bot_answer_id' => $answer->id]);
        $this->audit($admin, 'bot-teach', ['answer_id' => $answer->id, 'locale' => $locale, 'question_id' => $question?->id]);

        return Response::json(['answer' => $answer->name, 'language' => $locale, 'keywords' => $keywords[$locale]]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'answer_id' => $schema->integer()->description('Bot answer to add keywords to (from list-bot-questions).'),
            'language' => $schema->string()->enum(config('ribbon.locales'))->description('Language of the keywords.'),
            'keywords' => $schema->array()->items($schema->string())->description('New keywords, 1–3 words each, e.g. ["гарантия", "срок гарантии"].'),
            'question_id' => $schema->integer()->description('Unanswered question this solves (it is marked resolved).'),
            'dismiss' => $schema->boolean()->description('With question_id and no answer_id: just dismiss the question.'),
        ];
    }
}
