<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\File;

/**
 * Hand-rolled XML sitemap generator — no sitemap package (this project
 * avoids adding dependencies for hand-rolled admin/CMS features, see
 * CLAUDE.md).
 *
 * Locale is not a URL path prefix (App\Http\Middleware\SetLocale drives it
 * from `?lang=`/session), but every translatable entity (categories,
 * products, articles) stores a *per-locale* `slug`, so each entity still
 * resolves to up to 3 distinct URLs — one per config('ribbon.locales')
 * entry — exactly like Storefront\Catalog\Show / Products\Show /
 * Articles\Show already build their own hreflang alternates
 * (`hreflangAlternates` in each `render()`). This service replicates that
 * same locale/slug logic so the sitemap and the pages themselves can never
 * disagree about what a given locale's URL for an entity is.
 *
 * One deliberate difference from those pages' own hreflang loops: this
 * service *falls back* to the default locale's slug for a locale whose own
 * slug is missing (see localizedUrls()), rather than skipping that locale
 * entirely the way e.g. Catalog\Show::render() does. Slug is required in
 * all 3 locales at the validation layer, so in practice this never fires —
 * it's defensive, so a gap in that invariant can never make the sitemap
 * link to a 404 or silently drop a locale variant.
 */
class SitemapGeneratorService
{
    /**
     * Full sitemap XML as a string — the one method a caller (e.g. the
     * admin Settings "regenerate" button, or generateAndStore() below)
     * needs to get the current sitemap content.
     */
    public function generate(): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $urlset = $dom->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urlset->setAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        $dom->appendChild($urlset);

        foreach ($this->buildGroups() as $group) {
            foreach ($group['urlsByLocale'] as $loc) {
                $urlset->appendChild($this->buildUrlElement($dom, $loc, $group['urlsByLocale'], $group['lastmod']));
            }
        }

        return $dom->saveXML();
    }

    /**
     * Regenerates the sitemap and writes it to public/sitemap.xml — a
     * literal root-level file (not the storage:link-symlinked `public`
     * disk, which is rooted at storage/app/public), so File::put() +
     * public_path() is used directly rather than the Storage facade.
     * Records when this last ran on the singleton Setting row so the admin
     * UI can show "last generated" next to the regenerate button. Returns
     * the number of <url> entries written, for the artisan command's
     * success message.
     */
    public function generateAndStore(): int
    {
        $xml = $this->generate();

        File::put(public_path('sitemap.xml'), $xml);

        Setting::current()->update(['sitemap_generated_at' => now()]);

        return substr_count($xml, '<loc>');
    }

    /**
     * Every entity group that belongs in the sitemap, each carrying its own
     * up-to-3-locale URL map plus a single lastmod for all of them (the
     * underlying content is the same across locales, just presented in a
     * different language, so one lastmod covers every locale variant).
     *
     * @return array<int, array{urlsByLocale: array<string, string>, lastmod: ?CarbonInterface}>
     */
    private function buildGroups(): array
    {
        $groups = [
            $this->homeGroup(),
            $this->articlesIndexGroup(),
        ];

        return array_merge(
            $groups,
            $this->categoryGroups(),
            $this->productGroups(),
            $this->articleGroups(),
        );
    }

    /**
     * Home has no per-locale slug (it's always the bare `/` path) — same
     * `?lang=` mechanism Storefront\Home::render() itself uses to build its
     * own hreflangAlternates.
     *
     * @return array{urlsByLocale: array<string, string>, lastmod: CarbonInterface}
     */
    private function homeGroup(): array
    {
        return [
            'urlsByLocale' => $this->queryLocaleUrls(url('/')),
            'lastmod' => now(),
        ];
    }

    /**
     * Articles index — same "no slug, `?lang=` only" shape as Home, see
     * Storefront\Articles\Index::render().
     *
     * @return array{urlsByLocale: array<string, string>, lastmod: CarbonInterface}
     */
    private function articlesIndexGroup(): array
    {
        return [
            'urlsByLocale' => $this->queryLocaleUrls(url('/articles')),
            'lastmod' => now(),
        ];
    }

    /**
     * Every active category, one group per row.
     *
     * @return array<int, array{urlsByLocale: array<string, string>, lastmod: ?CarbonInterface}>
     */
    private function categoryGroups(): array
    {
        return Category::query()
            ->where('is_active', true)
            ->get()
            ->map(fn (Category $category) => [
                'urlsByLocale' => $this->localizedUrls($category->slug ?? [], 'storefront.catalog.show', 'categorySlug'),
                'lastmod' => $category->updated_at,
            ])
            ->filter(fn (array $group) => $group['urlsByLocale'] !== [])
            ->values()
            ->all();
    }

    /**
     * Every moderation-approved (buyer-visible) product, one group per row.
     *
     * @return array<int, array{urlsByLocale: array<string, string>, lastmod: ?CarbonInterface}>
     */
    private function productGroups(): array
    {
        $groups = [];

        // ->lazy() rather than ->get() — the catalog can grow well beyond
        // what's comfortable to hold in memory all at once, unlike
        // categories (a handful of rows by design, see CLAUDE.md's flat
        // category list).
        foreach (Product::query()->where('status', 'approved')->lazy() as $product) {
            $urlsByLocale = $this->localizedUrls($product->slug ?? [], 'storefront.products.show', 'productSlug');

            if ($urlsByLocale === []) {
                continue;
            }

            $groups[] = [
                'urlsByLocale' => $urlsByLocale,
                'lastmod' => $product->updated_at,
            ];
        }

        return $groups;
    }

    /**
     * Every published article (published_at set and in the past — matches
     * Storefront\Articles\Index's own visibility query exactly), one group
     * per row.
     *
     * @return array<int, array{urlsByLocale: array<string, string>, lastmod: ?CarbonInterface}>
     */
    private function articleGroups(): array
    {
        $groups = [];

        foreach (Article::query()->whereNotNull('published_at')->where('published_at', '<=', now())->lazy() as $article) {
            $urlsByLocale = $this->localizedUrls($article->slug ?? [], 'storefront.articles.show', 'articleSlug');

            if ($urlsByLocale === []) {
                continue;
            }

            // published_at can predate the last edit (updated_at) if the
            // article was tweaked after publishing — lastmod should
            // reflect whichever is actually more recent.
            $lastmod = $article->updated_at && $article->updated_at->greaterThan($article->published_at)
                ? $article->updated_at
                : $article->published_at;

            $groups[] = [
                'urlsByLocale' => $urlsByLocale,
                'lastmod' => $lastmod,
            ];
        }

        return $groups;
    }

    /**
     * Home/Articles-index shape: same bare URL for every locale, default
     * locale unadorned, every other locale suffixed with `?lang=`.
     *
     * @return array<string, string>
     */
    private function queryLocaleUrls(string $baseUrl): array
    {
        $defaultLocale = config('ribbon.locales')[0];
        $urls = [];

        foreach (config('ribbon.locales') as $loc) {
            $urls[$loc] = $loc === $defaultLocale ? $baseUrl : $baseUrl.'?lang='.$loc;
        }

        return $urls;
    }

    /**
     * Category/Product/Article shape: each locale gets its own URL built
     * from that locale's slug (falling back to the default locale's slug
     * when a translation is missing — see this class's docblock for why
     * that's a fallback here rather than a skip), non-default locales
     * additionally suffixed with `?lang=` since locale is session/query
     * driven, not a path prefix.
     *
     * Returns [] (skip this entity entirely) only if even the default
     * locale has no usable slug — there is then no working URL to build at
     * all.
     *
     * @param  array<string, string>  $slugs
     * @return array<string, string>
     */
    private function localizedUrls(array $slugs, string $routeName, string $routeParam): array
    {
        $defaultLocale = config('ribbon.locales')[0];
        $defaultSlug = $slugs[$defaultLocale] ?? null;

        if (! $defaultSlug) {
            return [];
        }

        $urls = [];

        foreach (config('ribbon.locales') as $loc) {
            $slug = $slugs[$loc] ?? $defaultSlug;
            $url = route($routeName, [$routeParam => $slug]);

            $urls[$loc] = $loc === $defaultLocale ? $url : $url.'?lang='.$loc;
        }

        return $urls;
    }

    /**
     * One <url> block: its own <loc>, optional <lastmod>, and an
     * <xhtml:link rel="alternate"> for every locale sibling plus
     * hreflang="x-default" pointing at the default-locale URL — the same
     * x-default convention resources/views/layouts/storefront.blade.php
     * already follows for the in-page <link rel="alternate"> tags.
     *
     * @param  array<string, string>  $alternates
     */
    private function buildUrlElement(DOMDocument $dom, string $loc, array $alternates, ?CarbonInterface $lastmod): DOMElement
    {
        $defaultLocale = config('ribbon.locales')[0];

        $url = $dom->createElement('url');

        $locElement = $dom->createElement('loc');
        $locElement->appendChild($dom->createTextNode($loc));
        $url->appendChild($locElement);

        if ($lastmod) {
            $lastmodElement = $dom->createElement('lastmod');
            $lastmodElement->appendChild($dom->createTextNode($lastmod->toAtomString()));
            $url->appendChild($lastmodElement);
        }

        foreach ($alternates as $altLocale => $altUrl) {
            $url->appendChild($this->buildAlternateLink($dom, $altLocale, $altUrl));
        }

        if (isset($alternates[$defaultLocale])) {
            $url->appendChild($this->buildAlternateLink($dom, 'x-default', $alternates[$defaultLocale]));
        }

        return $url;
    }

    private function buildAlternateLink(DOMDocument $dom, string $hreflang, string $href): DOMElement
    {
        $link = $dom->createElementNS('http://www.w3.org/1999/xhtml', 'xhtml:link');
        $link->setAttribute('rel', 'alternate');
        $link->setAttribute('hreflang', $hreflang);
        $link->setAttribute('href', $href);

        return $link;
    }
}
