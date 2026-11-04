<?php

namespace App\Livewire\Storefront\Pages;

use App\Models\Category;
use App\Models\Page;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Generic CMS page. Serves the fixed system routes (/about, /for-sellers,
 * /privacy, /terms — the route default `pageKey` picks the page) and custom
 * pages at /p/{pageSlug} (per-locale JSON slug, resolved manually like
 * Articles\Show; param named `pageSlug`, not `page`, to dodge Livewire's
 * implicit-binding clash with `public Page $page`). Unpublished or unknown
 * pages 404. The body is sanitized on save (Page::sanitizeBody) and is the
 * only unescaped output in the view.
 */
class Show extends Component
{
    public Page $page;

    public function mount(?string $pageKey = null, ?string $pageSlug = null): void
    {
        if ($pageKey !== null) {
            $found = Page::query()->isPublished()->where('key', $pageKey)->first();

            abort_unless($found, 404);

            $this->page = $found;

            return;
        }

        $currentLocale = app()->getLocale();
        $found = null;

        // Current locale first, then the others (a buyer who switched
        // language reuses the old segment), custom pages only.
        foreach (array_unique([$currentLocale, ...config('ribbon.locales')]) as $locale) {
            $found = Page::query()->isPublished()->whereNull('key')->where("slug->{$locale}", $pageSlug)->first();

            if ($found) {
                break;
            }
        }

        abort_unless($found, 404);

        $canonicalSlug = $found->slug[$currentLocale] ?? null;

        if ($canonicalSlug && $canonicalSlug !== $pageSlug) {
            $this->redirect(route('storefront.pages.show', ['pageSlug' => $canonicalSlug]));

            return;
        }

        $this->page = $found;
    }

    public function render()
    {
        $locale = app()->getLocale();
        $default = config('ribbon.locales')[0];

        $title = $this->page->localized('title', $locale);
        $lead = $this->page->localized('lead', $locale);
        $body = $this->page->localized('body', $locale);

        $metaDescription = $this->page->localized('meta_description', $locale);

        if ($metaDescription === '') {
            $metaDescription = Str::limit($lead !== '' ? $lead : trim(strip_tags($body)), 155, '');
        }

        $langSuffix = $locale !== $default ? '?lang='.$locale : '';
        $canonicalUrl = $this->page->url($locale).$langSuffix;

        $hreflangAlternates = [];

        foreach (config('ribbon.locales') as $loc) {
            $path = $this->page->path($loc);

            if ($path) {
                $hreflangAlternates[$loc] = url($path).($loc !== $default ? '?lang='.$loc : '');
            }
        }

        if (isset($hreflangAlternates[$default])) {
            $hreflangAlternates['x-default'] = $hreflangAlternates[$default];
        }

        $structuredData = [[
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => __('storefront.catalog.breadcrumb_home'), 'item' => url('/').$langSuffix],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $canonicalUrl],
            ],
        ]];

        return view('livewire.storefront.pages.show', [
            'title' => $title,
            'lead' => $lead,
            'body' => $body,
            'showSellersCta' => $this->page->key === 'for-sellers',
            'showUpdated' => $this->page->isLegal(),
        ])->layout('layouts.storefront', [
            'title' => $title,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
            'hreflangAlternates' => $hreflangAlternates,
            'structuredData' => $structuredData,
            'navCategories' => Category::navList(),
        ]);
    }
}
