<?php

namespace App\Livewire\Storefront\News;

use App\Livewire\Storefront\Articles\Index as ArticlesIndex;

/**
 * /news — the `type = news` section as its own indexable list (own H1,
 * canonical, sitemap entry). Identical behaviour to Articles\Index with the
 * type/route/lang constants swapped; an empty list is noindex and left out
 * of the sitemap.
 */
class Index extends ArticlesIndex
{
    protected const TYPE = 'news';

    protected const ROUTE_PREFIX = 'storefront.news';

    protected const LANG_PREFIX = 'news';

    protected const SEO_PREFIX = 'news';

    protected const VIEW = 'livewire.storefront.news.index';
}
