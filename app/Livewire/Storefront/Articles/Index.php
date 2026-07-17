<?php

namespace App\Livewire\Storefront\Articles;

use App\Models\Article;
use App\Models\Category;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    private const PER_PAGE = 12;

    #[Url(history: true)]
    public string $type = '';

    /**
     * Toggling the same type again clears the filter — same single-select-
     * with-an-off-state pattern as Catalog\Show's option filters.
     */
    public function filterByType(string $type): void
    {
        $this->type = $this->type === $type ? '' : $type;
        $this->resetPage();
    }

    public function render()
    {
        $navCategories = Category::navList();
        $locale = app()->getLocale();
        $defaultLocale = config('ribbon.locales')[0];

        $articles = Article::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->when(in_array($this->type, Article::TYPES, true), fn ($query) => $query->where('type', $this->type))
            ->orderByDesc('published_at')
            ->paginate(self::PER_PAGE);

        $canonicalUrl = url('/articles');
        $canonicalUrl = $locale !== $defaultLocale ? $canonicalUrl.'?lang='.$locale : $canonicalUrl;

        $hreflangAlternates = [];

        foreach (config('ribbon.locales') as $loc) {
            $hreflangAlternates[$loc] = $loc === $defaultLocale ? url('/articles') : url('/articles').'?lang='.$loc;
        }

        $hreflangAlternates['x-default'] = $hreflangAlternates[$defaultLocale];

        $structuredData = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => __('storefront.catalog.breadcrumb_home'), 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => __('storefront.articles.title'), 'item' => $canonicalUrl],
                ],
            ],
        ];

        return view('livewire.storefront.articles.index', [
            'articles' => $articles,
            'locale' => $locale,
            'defaultLocale' => $defaultLocale,
        ])->layout('layouts.storefront', [
            'title' => __('storefront.seo.articles_title'),
            'metaDescription' => __('storefront.seo.articles_description'),
            'canonicalUrl' => $canonicalUrl,
            'hreflangAlternates' => $hreflangAlternates,
            'structuredData' => $structuredData,
            'navCategories' => $navCategories,
        ]);
    }
}
