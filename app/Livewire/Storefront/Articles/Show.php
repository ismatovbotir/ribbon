<?php

namespace App\Livewire\Storefront\Articles;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

class Show extends Component
{
    /** Article::TYPES value this route serves; News\Show overrides these. */
    protected const TYPE = 'article';

    protected const ROUTE_PREFIX = 'storefront.articles';

    protected const LANG_PREFIX = 'articles';

    protected const SCHEMA_TYPE = 'Article';

    public Article $article;

    /**
     * Resolves the article from its per-locale JSON slug — mirrors
     * Storefront\Products\Show::mount() exactly (locale-fallback lookup,
     * then a canonical redirect if the matched locale's slug differs from
     * the segment actually requested). See that method's docblock for the
     * full reasoning.
     *
     * Parameter deliberately named `$articleSlug`, not `$article` — same
     * Livewire implicit-route-model-binding collision reasoning as
     * `$categorySlug`/`$productSlug` elsewhere in this app.
     *
     * Buyer-facing: only a published article (published_at set, not in the
     * future) ever resolves here — a draft or scheduled article's slug
     * 404s outright, matching Products\Show's identical "no
     * moderation-status UI a buyer can reach" rule.
     */
    public function mount(string $articleSlug): void
    {
        $locales = config('ribbon.locales');
        $currentLocale = app()->getLocale();

        $query = fn () => Article::query()->whereNotNull('published_at')->where('published_at', '<=', now());

        $found = $query()->where("slug->{$currentLocale}", $articleSlug)->first();

        if (! $found) {
            foreach ($locales as $locale) {
                if ($locale === $currentLocale) {
                    continue;
                }

                $found = $query()->where("slug->{$locale}", $articleSlug)->first();

                if ($found) {
                    break;
                }
            }
        }

        abort_unless($found, 404);

        // Right slug, wrong section: 301 to the section the item belongs to
        // (articles <-> news), keeping only `?lang=`.
        if ($found->type !== static::TYPE) {
            $otherPrefix = $found->type === 'news' ? 'storefront.news' : 'storefront.articles';
            $lang = request()->query('lang');

            throw new HttpResponseException(new RedirectResponse(
                route($otherPrefix.'.show', ['articleSlug' => $found->slug[$currentLocale] ?? $articleSlug])
                    .(is_string($lang) && $lang !== '' ? '?'.http_build_query(['lang' => $lang]) : ''),
                301,
            ));
        }

        $canonicalSlug = $found->slug[$currentLocale] ?? null;

        if ($canonicalSlug && $canonicalSlug !== $articleSlug) {
            $this->redirect(route(static::ROUTE_PREFIX.'.show', ['articleSlug' => $canonicalSlug]));

            return;
        }

        $this->article = $found;
    }

    public function render()
    {
        $navCategories = Category::navList();
        $locale = app()->getLocale();
        $defaultLocale = config('ribbon.locales')[0];

        $title = $this->article->title[$locale] ?? ($this->article->title[$defaultLocale] ?? '');
        $body = $this->article->body[$locale] ?? ($this->article->body[$defaultLocale] ?? '');
        $excerpt = $this->article->excerpt[$locale] ?? ($this->article->excerpt[$defaultLocale] ?? '');

        $metaDescription = $excerpt !== '' ? $excerpt : Str::limit(strip_tags($body), 160);

        $articleSlugForLocale = $this->article->slug[$locale] ?? ($this->article->slug[$defaultLocale] ?? '');
        $baseUrl = route(static::ROUTE_PREFIX.'.show', ['articleSlug' => $articleSlugForLocale]);
        $canonicalUrl = $locale !== $defaultLocale ? $baseUrl.'?lang='.$locale : $baseUrl;

        $hreflangAlternates = [];

        foreach (config('ribbon.locales') as $loc) {
            $slugForLoc = $this->article->slug[$loc] ?? null;

            if (! $slugForLoc) {
                continue;
            }

            $altUrl = route(static::ROUTE_PREFIX.'.show', ['articleSlug' => $slugForLoc]);

            if ($loc !== $defaultLocale) {
                $altUrl .= '?lang='.$loc;
            }

            $hreflangAlternates[$loc] = $altUrl;
        }

        if (isset($hreflangAlternates[$defaultLocale])) {
            $hreflangAlternates['x-default'] = $hreflangAlternates[$defaultLocale];
        }

        $ogImage = $this->article->cover_image_path
            ? Storage::disk('public')->url($this->article->cover_image_path)
            : null;

        $langSuffix = $locale !== $defaultLocale ? '?lang='.$locale : '';

        $articleNode = array_filter([
            '@context' => 'https://schema.org',
            '@type' => static::SCHEMA_TYPE,
            'headline' => $title,
            'description' => $metaDescription,
            'url' => $canonicalUrl,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonicalUrl],
            'inLanguage' => $locale,
            'datePublished' => $this->article->published_at?->toAtomString(),
            'dateModified' => $this->article->updated_at->toAtomString(),
            'image' => $ogImage,
            'author' => ['@type' => 'Organization', 'name' => __('storefront.nav.brand')],
            'publisher' => ['@type' => 'Organization', 'name' => __('storefront.nav.brand')],
        ], fn ($value) => $value !== null);

        $structuredData = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => __('storefront.catalog.breadcrumb_home'), 'item' => url('/').$langSuffix],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => __('storefront.'.static::LANG_PREFIX.'.title'), 'item' => route(static::ROUTE_PREFIX.'.index').$langSuffix],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $canonicalUrl],
                ],
            ],
            $articleNode,
        ];

        $faq = $this->article->faq[$locale] ?? [];

        // Only what the page visibly shows, as search engines require.
        if ($faq !== []) {
            $structuredData[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn (array $item) => [
                    '@type' => 'Question',
                    'name' => $item['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
                ], $faq),
            ];
        }

        return view('livewire.storefront.articles.show', [
            'title' => $title,
            'body' => $body,
            'shortAnswer' => $this->article->short_answer[$locale] ?? null,
            'faq' => $faq,
            'relatedCategories' => $this->article->categories()->where('is_active', true)->get(),
            'locale' => $locale,
            'defaultLocale' => $defaultLocale,
            // For the breadcrumb / "back to list" links in the shared view.
            'listRoute' => static::ROUTE_PREFIX.'.index',
            'listLabel' => __('storefront.'.static::LANG_PREFIX.'.title'),
        ])->layout('layouts.storefront', [
            'title' => __('storefront.seo.article_title', ['title' => $title]),
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
            'hreflangAlternates' => $hreflangAlternates,
            'ogType' => 'article',
            'ogImage' => $ogImage,
            'structuredData' => $structuredData,
            'navCategories' => $navCategories,
        ]);
    }
}
