<?php

namespace App\Livewire\Storefront\Articles;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    private const PER_PAGE = 12;

    /** Article::TYPES value this list shows; News\Index overrides these. */
    protected const TYPE = 'article';

    protected const ROUTE_PREFIX = 'storefront.articles';

    protected const LANG_PREFIX = 'articles';

    protected const SEO_PREFIX = 'articles';

    protected const VIEW = 'livewire.storefront.articles.index';

    /**
     * The retired `?type=` filter 301s to the section it used to select:
     * `news` -> /news, anything else -> /articles. Only `lang` survives.
     */
    public function mount(): void
    {
        if (! request()->query->has('type')) {
            return;
        }

        $target = request()->query('type') === 'news' ? 'storefront.news.index' : 'storefront.articles.index';
        $lang = request()->query('lang');

        throw new HttpResponseException(new RedirectResponse(
            route($target).(is_string($lang) && $lang !== '' ? '?'.http_build_query(['lang' => $lang]) : ''),
            301,
        ));
    }

    public function render()
    {
        $navCategories = Category::navList();
        $locale = app()->getLocale();
        $defaultLocale = config('ribbon.locales')[0];

        $articles = Article::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('type', static::TYPE)
            ->orderByDesc('published_at')
            ->paginate(self::PER_PAGE);

        $page = $articles->currentPage();
        // An empty list (e.g. no news yet) is thin content: noindex until it
        // has at least one item. Page 2+ keeps its own self-referential canonical.
        $robots = $articles->isEmpty() ? 'noindex,follow' : 'index,follow';
        $listPath = route(static::ROUTE_PREFIX.'.index', absolute: false);

        $langSuffix = $locale !== $defaultLocale ? '?lang='.$locale : '';

        $canonicalQuery = [];

        if ($page > 1) {
            $canonicalQuery['page'] = $page;
        }

        if ($locale !== $defaultLocale) {
            $canonicalQuery['lang'] = $locale;
        }

        $canonicalUrl = url($listPath).($canonicalQuery !== [] ? '?'.http_build_query($canonicalQuery) : '');
        $listUrl = url($listPath).$langSuffix;

        $pageSuffix = ($page > 1) ? __('storefront.seo.page_suffix', ['n' => $page]) : '';

        $hreflangAlternates = [];

        foreach (config('ribbon.locales') as $loc) {
            $hreflangAlternates[$loc] = $loc === $defaultLocale ? url($listPath) : url($listPath).'?lang='.$loc;
        }

        $hreflangAlternates['x-default'] = $hreflangAlternates[$defaultLocale];

        $structuredData = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => __('storefront.catalog.breadcrumb_home'), 'item' => url('/').$langSuffix],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => __('storefront.'.static::LANG_PREFIX.'.title'), 'item' => $listUrl],
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'itemListElement' => collect($articles->items())
                    ->values()
                    ->map(function (Article $article, int $index) use ($locale, $defaultLocale, $page) {
                        return [
                            '@type' => 'ListItem',
                            'position' => (($page - 1) * self::PER_PAGE) + $index + 1,
                            'url' => route(static::ROUTE_PREFIX.'.show', ['articleSlug' => $article->slug[$locale] ?? ($article->slug[$defaultLocale] ?? $article->id)]),
                            'name' => $article->title[$locale] ?? ($article->title[$defaultLocale] ?? ''),
                        ];
                    })
                    ->all(),
            ],
        ];

        return view(static::VIEW, [
            'articles' => $articles,
            // Retired filter: kept (always '') only so the pre-split
            // articles view keeps rendering until the frontend replaces its
            // type tabs with the two-link Articles/News switch.
            'type' => '',
            'contentType' => static::TYPE,
            'locale' => $locale,
            'defaultLocale' => $defaultLocale,
        ])->layout('layouts.storefront', [
            'title' => __('storefront.seo.'.static::SEO_PREFIX.'_title').$pageSuffix,
            'metaDescription' => __('storefront.seo.'.static::SEO_PREFIX.'_description').$pageSuffix,
            'robots' => $robots,
            'canonicalUrl' => $canonicalUrl,
            'hreflangAlternates' => $hreflangAlternates,
            'structuredData' => $structuredData,
            'navCategories' => $navCategories,
        ]);
    }
}
