<?php

namespace App\Services\Bot;

use App\Models\Article;
use App\Models\BotAnswer;
use App\Models\BotUnanswered;
use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\Product;
use App\Support\CatalogFilterUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The rule-based chat bot (no AI). Channel-neutral: the website consultant,
 * the admin test chat and Telegram all call start(), ask() and press() and
 * render the BotReply. Replies are plain text; channels escape them.
 *
 * - ask(): typed text -> the taught answer with the best keyword match, else
 *   a category named in the text (product finder), else a fallback, and the
 *   question is saved (masked) for staff to teach from.
 * - press(): a button -> another answer, the start menu, a finder step, or a
 *   lead / hand-off form.
 * - The product finder is built from live categories, filterable select
 *   parameters and visible products only, so it never offers a dead end.
 */
final class BotEngine
{
    /** Finder: at most this many questions before showing the results. */
    private const FINDER_STEPS = 3;

    /** Finder: stop asking once this few products are left. */
    private const FINDER_ENOUGH = 3;

    private const MAX_OPTION_BUTTONS = 12;

    private ?Collection $answers = null;

    public function start(string $locale): BotReply
    {
        $answer = BotAnswer::start();

        if ($answer) {
            return $this->answerReply($answer, $locale, 'start');
        }

        return new BotReply('answer', __('storefront.bot.start_default', [], $locale), [
            $this->button('finder', '', __('storefront.bot.finder', [], $locale)),
            $this->button('handoff', '', __('storefront.bot.handoff', [], $locale)),
        ], matchedBy: 'start');
    }

    /**
     * @param  bool  $learn  false for the admin test chat: no hit counts, nothing saved
     */
    public function ask(string $text, string $locale, string $channel = 'web', bool $learn = true): BotReply
    {
        $text = mb_substr(trim($text), 0, 500);

        if (BotText::normalize($text) === '') {
            return $this->start($locale);
        }

        if ($match = $this->match($text, $locale)) {
            if ($learn) {
                $match['answer']->increment('hits');
            }

            return $this->answerReply($match['answer'], $locale, 'keyword: '.$match['keyword']);
        }

        $categories = $this->categoriesNamedIn($text);

        // A short message naming a category ("нужны риббоны") starts the finder.
        // A longer question that merely mentions one ("есть гарантия на принтер?")
        // is a real question: it is saved for staff and the category is offered.
        if ($categories->isNotEmpty() && $this->isShort($text)) {
            return $categories->count() === 1
                ? $this->finder('c'.$categories->first()->id, $locale, 'category: '.$this->name($categories->first()->name, $locale))
                : $this->finderCategories($locale, $categories->pluck('id')->all(), 'categories');
        }

        if ($learn) {
            $this->remember($text, $locale, $channel);
        }

        return $this->fallback($locale, $categories);
    }

    /** At most 3 words of 3+ letters, so "нужны риббоны" counts but a full question does not. */
    private function isShort(string $text): bool
    {
        return count(array_filter(BotText::tokens($text), fn ($t) => mb_strlen($t) >= 3)) <= 3;
    }

    public function press(string $action, string $target, string $locale, bool $learn = true): BotReply
    {
        return match ($action) {
            'answer' => $this->pressAnswer($target, $locale, $learn),
            'finder' => $this->finder($target, $locale),
            'lead' => new BotReply('form', __('storefront.bot.lead_intro', [], $locale), form: 'lead'),
            'handoff' => new BotReply('form', __('storefront.bot.handoff_intro', [], $locale), form: 'handoff'),
            default => $this->start($locale),
        };
    }

    /**
     * Best taught answer for the text: every word of a keyword must appear
     * (word endings ignored); the longest keyword wins, the current locale
     * breaks ties. Keywords of every locale count, because buyers mix them.
     *
     * @return array{answer: BotAnswer, keyword: string}|null
     */
    public function match(string $text, string $locale): ?array
    {
        $typed = BotText::tokens($text);

        if ($typed === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($this->answers() as $answer) {
            foreach (config('ribbon.locales') as $keywordLocale) {
                foreach ($answer->keywordsFor($keywordLocale) as $keyword) {
                    $words = BotText::tokens($keyword);

                    if ($words === [] || ! $this->allWordsTyped($words, $typed)) {
                        continue;
                    }

                    $score = mb_strlen(implode(' ', $words)) + ($keywordLocale === $locale ? 0.5 : 0);

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = ['answer' => $answer, 'keyword' => $keyword];
                    }
                }
            }
        }

        return $best;
    }

    /** @param list<string> $words  @param list<string> $typed */
    private function allWordsTyped(array $words, array $typed): bool
    {
        foreach ($words as $word) {
            $found = false;

            foreach ($typed as $t) {
                if (BotText::wordMatches($t, $word)) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return false;
            }
        }

        return true;
    }

    private function answers(): Collection
    {
        return $this->answers ??= BotAnswer::active()->orderBy('id')->get();
    }

    private function pressAnswer(string $target, string $locale, bool $learn): BotReply
    {
        $answer = ctype_digit($target) ? BotAnswer::active()->find((int) $target) : null;

        if (! $answer) {
            return $this->start($locale);
        }

        if ($learn) {
            $answer->increment('hits');
        }

        return $this->answerReply($answer, $locale, 'button');
    }

    private function answerReply(BotAnswer $answer, string $locale, string $matchedBy): BotReply
    {
        $buttons = [];

        foreach ((array) $answer->buttons as $button) {
            if ($resolved = $this->resolveButton((array) $button, $locale)) {
                $buttons[] = $resolved;
            }
        }

        $buttons = $this->withExits($buttons, $locale, isStart: $answer->is_start);

        return new BotReply('answer', $answer->replyFor($locale), $buttons, $answer->id, $matchedBy);
    }

    /** A taught button made ready for a channel, or null when its target no longer exists. */
    private function resolveButton(array $button, string $locale): ?array
    {
        $action = (string) ($button['action'] ?? '');
        $target = (string) ($button['target'] ?? '');
        $label = $this->name((array) ($button['label'] ?? []), $locale);

        if ($label === '' || ! in_array($action, BotAnswer::ACTIONS, true)) {
            return null;
        }

        return match ($action) {
            'category' => ($url = $this->categoryUrl($target, [], $locale)) ? $this->link($label, $action, $target, $url) : null,
            'article' => ($url = $this->articleUrl($target, $locale)) ? $this->link($label, $action, $target, $url) : null,
            'url' => ($url = self::safeUrl($target)) ? $this->link($label, $action, $target, $url) : null,
            'answer' => ctype_digit($target) && $this->answers()->contains('id', (int) $target) ? $this->button($action, $target, $label) : null,
            default => $this->button($action, $action === 'finder' ? $target : '', $label),
        };
    }

    /** Every step offers the main menu and a person, unless the answer already has them. */
    private function withExits(array $buttons, string $locale, bool $isStart = false): array
    {
        $actions = array_column($buttons, 'action');

        if (! $isStart && ! in_array('start', $actions, true)) {
            $buttons[] = $this->button('start', '', __('storefront.bot.menu', [], $locale));
        }

        if (! in_array('handoff', $actions, true)) {
            $buttons[] = $this->button('handoff', '', __('storefront.bot.handoff', [], $locale));
        }

        return $buttons;
    }

    /** "Don't know yet", offering any categories the question mentioned, the finder, the menu and a person. */
    private function fallback(string $locale, ?Collection $categories = null): BotReply
    {
        $buttons = collect($categories ?? [])->take(3)
            ->map(fn ($c) => $this->button('finder', 'c'.$c->id, $this->name((array) $c->name, $locale)))
            ->values()->all();

        if ($buttons === []) {
            $buttons[] = $this->button('finder', '', __('storefront.bot.finder', [], $locale));
        }

        $buttons[] = $this->button('start', '', __('storefront.bot.menu', [], $locale));
        $buttons[] = $this->button('handoff', '', __('storefront.bot.handoff', [], $locale));

        return new BotReply('fallback', __('storefront.bot.fallback', [], $locale), $buttons);
    }

    /** Save an unmatched question (masked) for the Admin "Unanswered" list. */
    private function remember(string $text, string $locale, string $channel): void
    {
        $masked = BotText::mask($text);
        $normalized = BotText::normalize($masked);

        if (mb_strlen($normalized) < 2) {
            return;
        }

        $fingerprint = sha1($locale.'|'.$normalized);

        if ($this->bumpUnanswered($fingerprint)) {
            return;
        }

        // A flood of junk must not bury the real questions: stop collecting new ones at the cap.
        if (BotUnanswered::open()->count() >= BotUnanswered::MAX_OPEN) {
            return;
        }

        try {
            BotUnanswered::create([
                'fingerprint' => $fingerprint, 'text' => $masked, 'locale' => $locale,
                'channel' => $channel, 'times' => 1, 'last_asked_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The same question arrived at the same moment from someone else.
            $this->bumpUnanswered($fingerprint);
        }
    }

    /** Count one more ask of a known question (and reopen it). False when it is new. */
    private function bumpUnanswered(string $fingerprint): bool
    {
        return BotUnanswered::where('fingerprint', $fingerprint)->update([
            'times' => DB::raw('times + 1'),
            'last_asked_at' => now(),
            'resolved_at' => null,
            'updated_at' => now(),
        ]) > 0;
    }

    /**
     * Active categories with products whose name shares a meaningful word
     * (5+ letters, ending ignored) with the text. Only the best-scoring ones.
     */
    private function categoriesNamedIn(string $text): Collection
    {
        $typed = array_values(array_filter(BotText::tokens($text), fn ($t) => mb_strlen($t) >= 5));

        if ($typed === []) {
            return collect();
        }

        $scored = Category::navList()
            ->filter(fn ($c) => ($c->approved_products_count ?? 0) > 0)
            ->map(function ($category) use ($typed) {
                $words = collect((array) $category->name)->flatMap(fn ($n) => BotText::tokens((string) $n))
                    ->filter(fn ($w) => mb_strlen($w) >= 5)->unique();
                $score = collect($typed)->filter(fn ($t) => $words->contains(fn ($w) => BotText::wordMatches($t, $w) || BotText::wordMatches($w, $t)))->count();

                return ['category' => $category, 'score' => $score];
            })
            ->filter(fn ($row) => $row['score'] > 0);

        $top = $scored->max('score');

        return $scored->where('score', $top)->pluck('category')->values();
    }

    /**
     * Product finder. Target: "" (pick a category) or "c{id}" followed by
     * ".{parameterId}-{optionId}" for each answer given. Unknown ids are
     * dropped, so a stale or tampered button simply restarts the step.
     */
    private function finder(string $target, string $locale, string $matchedBy = 'finder'): BotReply
    {
        if (! preg_match('/^c(\d+)((?:\.\d+-\d+){0,'.self::FINDER_STEPS.'})$/', $target, $m)) {
            return $this->finderCategories($locale, null, $matchedBy);
        }

        $category = Category::where('is_active', true)->find((int) $m[1]);

        if (! $category) {
            return $this->finderCategories($locale, null, $matchedBy);
        }

        $parameters = $category->parameters()
            ->where('is_filterable', true)
            ->whereIn('type', ['select_single', 'select_multiple'])
            ->orderBy('sort_order')
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->get()
            ->keyBy('id');

        $chosen = [];

        foreach (array_filter(explode('.', $m[2])) as $pair) {
            [$parameterId, $optionId] = array_map('intval', explode('-', $pair));

            if ($parameters->has($parameterId) && $parameters[$parameterId]->options->contains('id', $optionId)) {
                $chosen[$parameterId] = $optionId;
            }
        }

        $base = $this->finderQuery($category->id, $chosen);
        $total = (clone $base)->count();
        $categoryName = $this->name((array) $category->name, $locale);

        if ($total > self::FINDER_ENOUGH && count($chosen) < self::FINDER_STEPS) {
            foreach ($parameters as $parameter) {
                if (isset($chosen[$parameter->id])) {
                    continue;
                }

                $buttons = $this->optionButtons($category->id, $parameter, $chosen, $base, $total, $locale);

                if (count($buttons) >= 2) {
                    $buttons[] = $this->link(__('storefront.bot.finder_show_all', ['count' => $total], $locale), 'category', (string) $category->id, $this->categoryUrl((string) $category->id, $chosen, $locale));
                    $buttons[] = $this->button('finder', '', __('storefront.bot.finder_again', [], $locale));

                    return new BotReply('finder', __('storefront.bot.finder_pick_param', ['param' => $this->name((array) $parameter->name, $locale), 'category' => $categoryName], $locale), $this->withExits($buttons, $locale), matchedBy: $matchedBy);
                }
            }
        }

        $buttons = [];

        if ($total > 0) {
            $buttons[] = $this->link(trans_choice('storefront.bot.finder_open', $total, ['count' => $total], $locale), 'category', (string) $category->id, $this->categoryUrl((string) $category->id, $chosen, $locale));
        }

        $buttons[] = $this->button('lead', '', __('storefront.bot.lead', [], $locale));
        $buttons[] = $this->button('finder', '', __('storefront.bot.finder_again', [], $locale));

        $text = $total > 0
            ? trans_choice('storefront.bot.finder_result', $total, ['count' => $total, 'category' => $categoryName], $locale)
            : __('storefront.bot.finder_none', ['category' => $categoryName], $locale);

        return new BotReply('finder', $text, $this->withExits($buttons, $locale), matchedBy: $matchedBy);
    }

    /** @param list<int>|null $only */
    private function finderCategories(string $locale, ?array $only, string $matchedBy = 'finder'): BotReply
    {
        $categories = Category::navList()
            ->filter(fn ($c) => ($c->approved_products_count ?? 0) > 0)
            ->when($only !== null, fn ($c) => $c->whereIn('id', $only));

        if ($categories->isEmpty()) {
            return new BotReply('finder', __('storefront.bot.finder_empty', [], $locale), $this->withExits([], $locale), matchedBy: $matchedBy);
        }

        $buttons = $categories->map(fn ($c) => $this->button('finder', 'c'.$c->id, $this->name((array) $c->name, $locale)))->values()->all();

        return new BotReply('finder', __('storefront.bot.finder_pick_category', [], $locale), $this->withExits($buttons, $locale), matchedBy: $matchedBy);
    }

    private function optionButtons(int $categoryId, CategoryParameter $parameter, array $chosen, Builder $base, int $total, string $locale): array
    {
        $buttons = [];

        foreach ($parameter->options as $option) {
            $count = (clone $base)->whereHas('parameterValues', fn ($q) => $q
                ->where('category_parameter_id', $parameter->id)
                ->whereHas('options', fn ($q2) => $q2->where('category_parameter_option_id', $option->id)))
                ->count();

            if ($count === 0) {
                continue;
            }

            $next = $chosen + [$parameter->id => $option->id];
            $buttons[] = $this->button('finder', $this->encode($categoryId, $next), $this->name((array) $option->value, $locale).' · '.$count);

            if (count($buttons) >= self::MAX_OPTION_BUTTONS) {
                break;
            }
        }

        return $buttons;
    }

    /** @param array<int, int> $chosen */
    private function finderQuery(int $categoryId, array $chosen): Builder
    {
        $query = Product::query()->visibleOnStorefront()->where('category_id', $categoryId);

        foreach ($chosen as $parameterId => $optionId) {
            $query->whereHas('parameterValues', fn ($q) => $q
                ->where('category_parameter_id', $parameterId)
                ->whereHas('options', fn ($q2) => $q2->where('category_parameter_option_id', $optionId)));
        }

        return $query;
    }

    /** @param array<int, int> $chosen */
    private function encode(int $categoryId, array $chosen): string
    {
        $target = 'c'.$categoryId;

        foreach ($chosen as $parameterId => $optionId) {
            $target .= '.'.$parameterId.'-'.$optionId;
        }

        return $target;
    }

    /** @param array<int, int> $chosen */
    private function categoryUrl(string $categoryId, array $chosen, string $locale): ?string
    {
        $category = ctype_digit($categoryId) ? Category::where('is_active', true)->find((int) $categoryId) : null;

        if (! $category || $this->name((array) $category->slug, $locale) === '') {
            return null;
        }

        // Same query shape as the catalog's own filters (CatalogFilterUrl), plus the language.
        $query = [];

        foreach ($chosen as $parameterId => $optionId) {
            $query['f'][$parameterId] = [(string) $optionId];
        }

        if ($locale !== config('ribbon.locales')[0]) {
            $query['lang'] = $locale;
        }

        return CatalogFilterUrl::build($category, $query, $locale);
    }

    private function articleUrl(string $articleId, string $locale): ?string
    {
        $article = ctype_digit($articleId) ? Article::find((int) $articleId) : null;

        if (! $article || ! $article->isPublished()) {
            return null;
        }

        $slug = $this->name((array) $article->slug, $locale);
        $route = $article->type === 'news' ? 'storefront.news.show' : 'storefront.articles.show';
        $suffix = $locale !== config('ribbon.locales')[0] ? '?lang='.$locale : '';

        return $slug !== '' ? route($route, ['articleSlug' => $slug]).$suffix : null;
    }

    /** Links may only point inside the site ("/...") or to an https address. */
    public static function safeUrl(string $url): ?string
    {
        $url = trim($url);

        if (preg_match('#^/(?!/)[^\s]*$#', $url)) {
            return url($url);
        }

        if (! preg_match('#^https://[^\s/$.?\#][^\s]*$#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        // Telegram rejects link buttons to hosts without a dot (https://x), which drops the whole reply.
        $host = (string) parse_url($url, PHP_URL_HOST);

        return str_contains(trim($host, '.'), '.') ? $url : null;
    }

    /** An https:// link to a host other than this site's own. */
    public static function isExternal(string $url): bool
    {
        $url = trim($url);

        if (! str_starts_with(strtolower($url), 'https://')) {
            return false;
        }

        $own = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return strtolower((string) parse_url($url, PHP_URL_HOST)) !== $own;
    }

    private function name(array $values, string $locale): string
    {
        $default = config('ribbon.locales')[0];

        return trim((string) (($values[$locale] ?? '') ?: ($values[$default] ?? '')));
    }

    private function button(string $action, string $target, string $label): array
    {
        return ['label' => $label, 'action' => $action, 'target' => $target, 'url' => null];
    }

    private function link(string $label, string $action, string $target, ?string $url): array
    {
        return ['label' => $label, 'action' => $action, 'target' => $target, 'url' => $url];
    }
}
