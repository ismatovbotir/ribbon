@php
    $coverImageUrl = $article->cover_image_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($article->cover_image_path)
        : null;
    $toc = \App\Support\TableOfContents::build($body);
    $hasAside = filled($shortAnswer) || $relatedCategories->isNotEmpty();
@endphp

{{-- Phones: one column (header, short answer + products, contents, text). xl+: contents | text | short answer + products;
     the side columns are equal, so the text always sits on the page's central axis. --}}
<article class="mx-auto max-w-[42rem] xl:grid xl:max-w-none xl:grid-cols-[minmax(0,1fr)_minmax(0,42rem)_minmax(0,1fr)] xl:grid-rows-[auto_1fr] xl:gap-x-12">
    <div class="xl:col-start-2 xl:row-start-1">
        <nav aria-label="{{ __('storefront.catalog.breadcrumb_label') }}" class="no-scrollbar -mx-4 flex items-center gap-1.5 overflow-x-auto px-4 text-sm whitespace-nowrap md:mx-0 md:px-0">
            <a href="{{ route('storefront.home') }}" wire:navigate class="hit text-text-secondary hover:text-text-primary hover:underline">{{ __('storefront.catalog.breadcrumb_home') }}</a>
            <span class="text-text-muted" aria-hidden="true">/</span>
            <a href="{{ route($listRoute) }}" wire:navigate class="hit text-text-secondary hover:text-text-primary hover:underline">{{ $listLabel }}</a>
            <span class="text-text-muted" aria-hidden="true">/</span>
            <span class="max-w-[16rem] truncate font-medium text-text-primary" aria-current="page">{{ $title }}</span>
        </nav>

        <header class="mt-3">
            @if ($article->type === 'news')
                <x-badge variant="info">{{ __('storefront.articles.type.news') }}</x-badge>
            @endif
            <h1 class="mt-2 text-2xl font-bold text-balance text-text-primary md:text-3xl">{{ $title }}</h1>
            {{-- Byline (GEO): author, published and updated dates as visible text; matches the Article JSON-LD author. --}}
            <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-text-secondary">
                <span>{{ __('storefront.articles.byline', ['author' => __('storefront.nav.brand')]) }}</span>
                @if ($article->published_at)
                    <span>{{ __('storefront.articles.published_label') }} <time class="text-xs tabular-nums" datetime="{{ $article->published_at->toDateString() }}">{{ \App\Support\LocalizedDate::short($article->published_at) }}</time></span>
                    @if ($article->updated_at && $article->updated_at->toDateString() > $article->published_at->toDateString())
                        <span>{{ __('storefront.articles.updated_label') }} <time class="text-xs tabular-nums" datetime="{{ $article->updated_at->toDateString() }}">{{ \App\Support\LocalizedDate::short($article->updated_at) }}</time></span>
                    @endif
                @endif
            </p>
        </header>

        @if ($coverImageUrl)
            <img src="{{ $coverImageUrl }}" alt="{{ $title }}" width="1280" height="720" class="mt-4 aspect-[16/9] w-full overflow-hidden rounded-lg border border-border object-cover">
        @endif
    </div>

    @if ($hasAside)
        <div class="mt-6 xl:col-start-3 xl:row-span-2 xl:row-start-1 xl:mt-0">
            <div class="space-y-4 xl:sticky xl:top-24">
                @if (filled($shortAnswer))
                    <aside aria-labelledby="short-answer-title" class="border-l-[3px] border-accent-700 bg-surface-subtle p-4">
                        <h2 id="short-answer-title" class="text-sm font-semibold text-text-primary">{{ __('storefront.articles.short_answer') }}</h2>
                        <p class="mt-1 text-base leading-7 text-text-primary xl:text-sm xl:leading-6">{{ $shortAnswer }}</p>
                    </aside>
                @endif

                @if ($relatedCategories->isNotEmpty())
                    <section aria-labelledby="article-products" class="rounded-md border border-border-strong p-4">
                        <h2 id="article-products" class="font-display text-base font-semibold text-text-primary">{{ __('storefront.articles.see_products') }}</h2>
                        <ul class="mt-2 flex flex-wrap gap-2 xl:flex-col">
                            @foreach ($relatedCategories as $category)
                                <li>
                                    <a href="{{ route('storefront.catalog.show', ['categorySlug' => $category->slug[$locale] ?? $category->slug[$defaultLocale]]) }}" wire:navigate class="group flex min-h-11 items-center justify-between gap-2 rounded-sm border border-border-input bg-surface px-3 py-2 text-sm font-medium text-text-primary hover:border-accent-700 hover:text-accent-700">
                                        <span>{{ $category->name[$locale] ?? $category->name[$defaultLocale] }}</span>
                                        <x-storefront.icon name="arrow-right" class="size-4 shrink-0 text-text-muted group-hover:text-accent-700" stroke="2" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </div>
    @endif

    <div class="mt-6 xl:col-start-1 xl:row-span-2 xl:row-start-1 xl:mt-0">
        <x-storefront.toc :items="$toc['items']" />
    </div>

    <div class="xl:col-start-2 xl:row-start-2">
        @if (trim(strip_tags($body)) !== '')
            {{-- Sanitized rich text (App\Support\HtmlSanitizer before save); TableOfContents only adds h2 ids. --}}
            <x-storefront.prose class="mt-6">
                {!! $toc['html'] !!}
            </x-storefront.prose>
        @else
            <p class="mt-6 text-text-secondary">{{ __('storefront.articles.empty_body') }}</p>
        @endif

        @if ($faq !== [])
            <section aria-labelledby="article-faq" class="mt-8">
                <h2 id="article-faq" class="font-display text-xl font-semibold text-text-primary">{{ __('storefront.articles.faq') }}</h2>
                <dl class="mt-3 divide-y divide-border border-y border-border">
                    @foreach ($faq as $item)
                        <div class="py-3">
                            <dt class="font-semibold text-text-primary">{{ $item['q'] }}</dt>
                            <dd class="mt-1 text-text-secondary">{{ $item['a'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        <footer class="mt-8 border-t border-border-strong pt-4">
            <a href="{{ route($listRoute) }}" wire:navigate class="inline-flex h-11 items-center gap-1 text-sm font-medium text-accent-700 hover:underline">
                <x-storefront.icon name="arrow-left" class="size-4" stroke="2" />{{ $listLabel }}
            </a>
        </footer>
    </div>
</article>
