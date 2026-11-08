<div class="flex flex-col gap-6 md:gap-8">
    <header class="text-center">
        <nav aria-label="{{ __('storefront.catalog.breadcrumb_label') }}" class="no-scrollbar -mx-4 flex items-center justify-center gap-1.5 overflow-x-auto px-4 text-sm whitespace-nowrap md:mx-0 md:px-0">
            <a href="{{ route('storefront.home') }}" wire:navigate class="hit text-text-secondary hover:text-text-primary hover:underline">{{ __('storefront.catalog.breadcrumb_home') }}</a>
            <span class="text-text-muted" aria-hidden="true">/</span>
            <span class="font-medium text-text-primary" aria-current="page">{{ __('storefront.articles.title') }}</span>
        </nav>

        <h1 id="articles-heading" class="mt-2 text-xl font-semibold text-text-primary md:text-2xl">{{ __('storefront.articles.title') }}</h1>
        <p class="mx-auto mt-1 max-w-2xl text-base text-balance text-text-secondary">{{ __('storefront.articles.subtitle') }}</p>
    </header>

    <x-storefront.content-switch current="articles" />

    @if ($articles->isEmpty())
        <div class="rounded-md border border-border-strong">
            <x-empty-state variant="empty" :title="__('storefront.articles.empty_title')" :description="__('storefront.articles.empty_body')" />
        </div>
    @else
        <h2 class="sr-only">{{ __('storefront.articles.list_heading') }}</h2>
        <div id="list" class="grid-balanced [--gap:1.5rem] sm:[--cols:2] lg:[--cols:3]">
            @foreach ($articles as $article)
                <x-storefront.article-card :article="$article" wire:key="article-{{ $article->id }}" />
            @endforeach
        </div>

        {{ $articles->links('pagination.storefront') }}
    @endif
</div>
