<?php

namespace App\Livewire\Storefront\Articles;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class Show extends Component
{
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

        $canonicalSlug = $found->slug[$currentLocale] ?? null;

        if ($canonicalSlug && $canonicalSlug !== $articleSlug) {
            $this->redirect(route('storefront.articles.show', ['articleSlug' => $canonicalSlug]));

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

        $metaDescription = $excerpt !== '' ? $excerpt : \Illuminate\Support\Str::limit(strip_tags($body), 160);

        $articleSlugForLocale = $this->article->slug[$locale] ?? ($this->article->slug[$defaultLocale] ?? '');
        $baseUrl = route('storefront.articles.show', ['articleSlug' => $articleSlugForLocale]);
        $canonicalUrl = $locale !== $defaultLocale ? $baseUrl.'?lang='.$locale : $baseUrl;

        $hreflangAlternates = [];

        foreach (config('ribbon.locales') as $loc) {
            $slugForLoc = $this->article->slug[$loc] ?? null;

            if (! $slugForLoc) {
                continue;
            }

            $altUrl = route('storefront.articles.show', ['articleSlug' => $slugForLoc]);

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

        $articleNode = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
            'description' => $metaDescription,
            'url' => $canonicalUrl,
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
                    ['@type' => 'ListItem', 'position' => 1, 'name' => __('storefront.catalog.breadcrumb_home'), 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => __('storefront.articles.title'), 'item' => url('/articles')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $canonicalUrl],
                ],
            ],
            $articleNode,
        ];

        return view('livewire.storefront.articles.show', [
            'title' => $title,
            'body' => $body,
            'locale' => $locale,
            'defaultLocale' => $defaultLocale,
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
