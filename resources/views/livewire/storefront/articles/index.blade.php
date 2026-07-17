<div class="flex flex-col gap-8">
    <section aria-labelledby="articles-heading" class="flex flex-col gap-2">
        <nav aria-label="Breadcrumb" class="flex items-center gap-1.5 text-sm">
            <a href="{{ route('storefront.home') }}" wire:navigate class="text-text-secondary hover:text-text-primary">{{ __('storefront.catalog.breadcrumb_home') }}</a>
            <span class="text-text-muted" aria-hidden="true">/</span>
            <span class="font-medium text-text-primary">{{ __('storefront.articles.title') }}</span>
        </nav>

        <h1 id="articles-heading" class="text-2xl font-bold tracking-tight text-text-primary md:text-3xl">
            {{ __('storefront.articles.title') }}
        </h1>
        <p class="max-w-2xl text-base text-text-secondary">
            {{ __('storefront.articles.subtitle') }}
        </p>
    </section>

    <div class="flex items-center gap-1 self-start rounded-sm border border-border p-0.5 text-sm">
        <button
            type="button"
            wire:click="filterByType('')"
            class="rounded-sm px-3 py-1.5 font-medium transition-colors {{ $type === '' ? 'bg-accent-50 text-accent-700' : 'text-text-muted hover:text-text-primary' }}"
        >
            {{ __('storefront.articles.type.all') }}
        </button>
        @foreach (\App\Models\Article::TYPES as $option)
            <button
                type="button"
                wire:click="filterByType('{{ $option }}')"
                class="rounded-sm px-3 py-1.5 font-medium transition-colors {{ $type === $option ? 'bg-accent-50 text-accent-700' : 'text-text-muted hover:text-text-primary' }}"
            >
                {{ __('storefront.articles.type.'.$option) }}
            </button>
        @endforeach
    </div>

    @if ($articles->isEmpty())
        <div class="rounded-xl border border-dashed border-border p-10 text-center">
            @if ($type !== '')
                <p class="text-base font-medium text-text-primary">{{ __('storefront.articles.filtered_empty_title') }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.articles.filtered_empty_body') }}</p>
                <button type="button" wire:click="filterByType('')" class="mt-3 text-sm font-medium text-accent-700 hover:underline">{{ __('storefront.articles.type.all') }}</button>
            @else
                <p class="text-base font-medium text-text-primary">{{ __('storefront.articles.empty_title') }}</p>
                <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.articles.empty_body') }}</p>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($articles as $article)
                <x-storefront.article-card :article="$article" wire:key="article-{{ $article->id }}" />
            @endforeach
        </div>

        @if ($articles->hasPages())
            <div>
                {{ $articles->links() }}
            </div>
        @endif
    @endif
</div>
