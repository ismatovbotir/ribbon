<?php

namespace App\Livewire\Storefront\News;

use App\Livewire\Storefront\Articles\Show as ArticlesShow;

/**
 * /news/{articleSlug} — news-type items only (an article slug 301s to
 * /articles/{slug}). Reuses the articles detail view; JSON-LD is
 * NewsArticle.
 */
class Show extends ArticlesShow
{
    protected const TYPE = 'news';

    protected const ROUTE_PREFIX = 'storefront.news';

    protected const LANG_PREFIX = 'news';

    protected const SCHEMA_TYPE = 'NewsArticle';
}
