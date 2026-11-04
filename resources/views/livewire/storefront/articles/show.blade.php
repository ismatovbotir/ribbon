@php
    $coverImageUrl = $article->cover_image_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($article->cover_image_path)
        : null;
@endphp

<article class="mx-auto max-w-[42rem]">
    <nav aria-label="{{ __('storefront.catalog.breadcrumb_label') }}" class="no-scrollbar -mx-4 flex items-center gap-1.5 overflow-x-auto px-4 text-sm whitespace-nowrap md:mx-0 md:px-0">
        <a href="{{ route('storefront.home') }}" wire:navigate class="hit text-text-secondary hover:text-text-primary hover:underline">{{ __('storefront.catalog.breadcrumb_home') }}</a>
        <span class="text-text-muted" aria-hidden="true">/</span>
        <a href="{{ route($listRoute) }}" wire:navigate class="hit text-text-secondary hover:text-text-primary hover:underline">{{ $listLabel }}</a>
        <span class="text-text-muted" aria-hidden="true">/</span>
        <span class="max-w-[16rem] truncate font-medium text-text-primary" aria-current="page">{{ $title }}</span>
    </nav>

    <header class="mt-3">
        <div class="flex items-center gap-2">
            @if ($article->type === 'news')
                <x-badge variant="info">{{ __('storefront.articles.type.news') }}</x-badge>
            @endif
        </div>
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

    @if (trim(strip_tags($body)) !== '')
        {{-- Sanitized rich text (App\Support\HtmlSanitizer before save); flow-root so floated images cannot overflow. --}}
        <x-storefront.prose class="mt-6">
            {!! $body !!}
        </x-storefront.prose>
    @else
        <p class="mt-6 text-text-secondary">{{ __('storefront.articles.empty_body') }}</p>
    @endif

    <footer class="mt-8 border-t border-border-strong pt-4">
        <a href="{{ route($listRoute) }}" wire:navigate class="inline-flex h-11 items-center gap-1 text-sm font-medium text-accent-700 hover:underline">
            <x-storefront.icon name="arrow-left" class="size-4" stroke="2" />{{ $listLabel }}
        </a>
    </footer>
</article>
