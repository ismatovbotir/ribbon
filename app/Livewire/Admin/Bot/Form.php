<?php

namespace App\Livewire\Admin\Bot;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Models\Article;
use App\Models\BotAnswer;
use App\Models\BotUnanswered;
use App\Models\Category;
use App\Services\Bot\BotEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Create / edit one bot answer: reply and keywords per locale, buttons, start
 * flag. Opened with ?from={id} it teaches from an unanswered question: the
 * question becomes the first keyword and is marked resolved on save.
 */
class Form extends Component
{
    use AuthorizesAdmin;

    protected function adminAccess(): ?string
    {
        return 'bot.manage';
    }

    /** The answer being edited, fixed at mount (saved via a fresh copy, never the client's). */
    #[Locked]
    public ?BotAnswer $answer = null;

    /** Unanswered question being taught from (?from=id), fixed at mount. */
    #[Locked]
    public ?int $fromId = null;

    public string $name = '';

    /** @var array<string, string> */
    public array $reply = [];

    /** @var array<string, string> One keyword per line (or comma-separated), per locale. */
    public array $keywords = [];

    /** @var list<array{action: string, target: string, label: array<string, string>}> */
    public array $buttons = [];

    public bool $isStart = false;

    public bool $isActive = true;

    public function mount(?BotAnswer $answer = null): void
    {
        $this->authorizeAdmin('bot.manage');

        $locales = config('ribbon.locales');
        $this->answer = $answer;
        $this->reply = array_fill_keys($locales, '');
        $this->keywords = array_fill_keys($locales, '');

        if ($answer) {
            $this->name = $answer->name;
            foreach ($locales as $locale) {
                $this->reply[$locale] = (string) ($answer->reply[$locale] ?? '');
                $this->keywords[$locale] = implode("\n", $answer->keywordsFor($locale));
            }
            $this->buttons = collect((array) $answer->buttons)->map(fn ($b) => [
                'action' => (string) ($b['action'] ?? 'answer'),
                'target' => (string) ($b['target'] ?? ''),
                'label' => array_replace(array_fill_keys($locales, ''), (array) ($b['label'] ?? [])),
            ])->values()->all();
            $this->isStart = $answer->is_start;
            $this->isActive = $answer->is_active;

            return;
        }

        $this->fromId = request()->integer('from') ?: null;

        if ($this->fromId && ($question = $this->fromQuestion)) {
            $this->name = mb_substr($question->text, 0, 120);
            $this->keywords[$question->locale] = $question->text;
        }
    }

    #[Computed]
    public function fromQuestion(): ?BotUnanswered
    {
        return $this->fromId ? BotUnanswered::open()->find($this->fromId) : null;
    }

    /** Other answers a button can lead to. */
    #[Computed]
    public function otherAnswers(): Collection
    {
        return BotAnswer::query()->when($this->answer, fn ($q) => $q->whereKeyNot($this->answer->id))->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
    }

    #[Computed]
    public function articles(): Collection
    {
        return Article::query()->whereNotNull('published_at')->orderByDesc('published_at')->get(['id', 'title', 'type']);
    }

    #[Computed]
    public function incompleteLocales(): array
    {
        return collect(config('ribbon.locales'))->filter(fn ($l) => blank($this->reply[$l] ?? null))->values()->all();
    }

    public function addButton(): void
    {
        $this->authorizeAdmin('bot.manage');

        if (count($this->buttons) < BotAnswer::MAX_BUTTONS) {
            $this->buttons[] = ['action' => 'answer', 'target' => '', 'label' => array_fill_keys(config('ribbon.locales'), '')];
        }
    }

    public function removeButton(int $index): void
    {
        $this->authorizeAdmin('bot.manage');

        unset($this->buttons[$index]);
        $this->buttons = array_values($this->buttons);
    }

    public function moveButton(int $index, int $direction): void
    {
        $this->authorizeAdmin('bot.manage');

        $to = $index + ($direction < 0 ? -1 : 1);

        if (isset($this->buttons[$index], $this->buttons[$to])) {
            [$this->buttons[$index], $this->buttons[$to]] = [$this->buttons[$to], $this->buttons[$index]];
        }
    }

    /** Does this button action need a target (an answer, category, article or link)? The others need none. */
    public static function needsTarget(string $action): bool
    {
        return in_array($action, ['answer', 'category', 'article', 'url'], true);
    }

    /** @return list<string> */
    private function parseKeywords(string $text): array
    {
        return collect(preg_split('/[\n,]+/u', $text) ?: [])
            ->map(fn ($k) => trim(preg_replace('/\s+/u', ' ', $k) ?? ''))
            ->filter(fn ($k) => $k !== '')
            ->unique(fn ($k) => mb_strtolower($k))
            ->values()
            ->all();
    }

    public function save(): void
    {
        $this->authorizeAdmin('bot.manage');

        $locales = config('ribbon.locales');
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'buttons' => ['array', 'max:'.BotAnswer::MAX_BUTTONS],
            'buttons.*.action' => ['required', Rule::in(BotAnswer::ACTIONS)],
            'buttons.*.target' => ['nullable', 'string', 'max:255'],
            'isStart' => ['boolean'],
            'isActive' => ['boolean'],
        ];

        foreach ($locales as $locale) {
            $rules["reply.{$locale}"] = ['required', 'string', 'max:1000'];
            $rules["keywords.{$locale}"] = ['nullable', 'string', 'max:3000'];
            $rules["buttons.*.label.{$locale}"] = ['required', 'string', 'max:40'];
        }

        $this->withValidator(function (Validator $validator) use ($locales) {
            $validator->after(function (Validator $validator) use ($locales) {
                foreach ($locales as $locale) {
                    $list = $this->parseKeywords($this->keywords[$locale] ?? '');
                    if (count($list) > BotAnswer::MAX_KEYWORDS || collect($list)->contains(fn ($k) => mb_strlen($k) > 60)) {
                        $validator->errors()->add("keywords.{$locale}", __('validation.max.array', ['attribute' => __('admin.bot.keywords'), 'max' => BotAnswer::MAX_KEYWORDS]));
                    }
                }

                foreach ($this->buttons as $i => $button) {
                    if ($error = $this->targetError((string) ($button['action'] ?? ''), (string) ($button['target'] ?? ''))) {
                        $validator->errors()->add("buttons.{$i}.target", $error);
                    }
                }
            });
        })->validate($rules, [], ['name' => __('admin.bot.name')]);

        $data = [
            'name' => trim($this->name),
            'reply' => array_map('trim', array_intersect_key($this->reply, array_flip($locales))),
            'keywords' => collect($locales)->mapWithKeys(fn ($l) => [$l => $this->parseKeywords($this->keywords[$l] ?? '')])->all(),
            'buttons' => collect($this->buttons)->map(fn ($b) => [
                'action' => $b['action'],
                'target' => self::needsTarget($b['action']) ? trim((string) $b['target']) : '',
                'label' => array_map('trim', array_intersect_key((array) $b['label'], array_flip($locales))),
            ])->values()->all(),
            'is_active' => $this->isActive,
            'updated_by' => Auth::id(),
        ];

        if ($this->answer) {
            $answer = BotAnswer::findOrFail($this->answer->id);
            $answer->update($data);
        } else {
            $answer = BotAnswer::create($data);
        }

        if ($this->isStart) {
            $answer->makeStart();
        } elseif ($answer->is_start) {
            $answer->update(['is_start' => false]);
        }

        if ($question = $this->fromQuestion) {
            $question->update(['resolved_at' => now(), 'bot_answer_id' => $answer->id]);
        }

        session()->flash('status', __('admin.bot.saved'));

        $this->redirectRoute('admin.bot.index', navigate: true);
    }

    private function targetError(string $action, string $target): ?string
    {
        if (! self::needsTarget($action)) {
            return null;
        }

        $valid = match ($action) {
            'answer' => ctype_digit($target) && BotAnswer::whereKey((int) $target)->when($this->answer, fn ($q) => $q->whereKeyNot($this->answer->id))->exists(),
            'category' => ctype_digit($target) && Category::whereKey((int) $target)->exists(),
            'article' => ctype_digit($target) && Article::whereKey((int) $target)->exists(),
            'url' => BotEngine::safeUrl($target) !== null,
            default => false,
        };

        if ($valid && $action === 'url' && ! $this->mayLinkTo($target)) {
            return __('admin.bot.url_external_super');
        }

        if ($valid) {
            return null;
        }

        return $action === 'url' ? __('admin.bot.url_invalid') : __('validation.required', ['attribute' => __('admin.bot.button_target')]);
    }

    /**
     * Links to other websites are shown to every buyer, so only Super Admin
     * may add them. Others may link within Ribbon, and may keep an outside
     * link that is already saved on this answer.
     */
    private function mayLinkTo(string $target): bool
    {
        if (! BotEngine::isExternal($target) || Gate::allows('super-admin')) {
            return true;
        }

        return collect((array) $this->answer?->buttons)
            ->contains(fn ($b) => ($b['action'] ?? null) === 'url' && trim((string) ($b['target'] ?? '')) === trim($target));
    }

    public function render()
    {
        return view('livewire.admin.bot.form')->layout('layouts.admin', [
            'title' => $this->answer ? __('admin.bot.edit') : __('admin.bot.new'),
            'breadcrumb' => [
                ['label' => __('admin.bot.title'), 'url' => route('admin.bot.index')],
                ['label' => $this->answer ? $this->answer->name : __('admin.bot.new')],
            ],
        ]);
    }
}
