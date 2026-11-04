@props(['article'])

@php
    $locale = app()->getLocale();
    $defaultLocale = config('ribbon.locales')[0];
    $title = $article->title[$locale] ?? ($article->title[$defaultLocale] ?? '');
    $excerpt = $article->excerpt[$locale] ?? ($article->excerpt[$defaultLocale] ?? '');
    if ($excerpt === '') {
        $body = $article->body[$locale] ?? ($article->body[$defaultLocale] ?? '');
        $excerpt = \Illuminate\Support\Str::limit(strip_tags($body), 120);
    }
    $slug = $article->slug[$locale] ?? ($article->slug[$defaultLocale] ?? $article->id);
    $articleUrl = $article->type === 'news'
        ? route('storefront.news.show', ['articleSlug' => $slug])
        : route('storefront.articles.show', ['articleSlug' => $slug]);
@endphp

<a href="{{ $articleUrl }}" wire:navigate class="group flex flex-col">
    <div class="aspect-[16/9] overflow-hidden rounded-md border border-border bg-surface-subtle">
        @if ($article->cover_image_path)
            <img
                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($article->cover_image_path) }}"
                alt="{{ $title }}"
                width="640"
                height="360"
                class="size-full object-cover"
                loading="lazy"
            >
        @else
            <div class="flex size-full items-center justify-center text-text-muted">
                <x-storefront.icon name="document" class="size-8" />
            </div>
        @endif
    </div>

    <div class="pt-3">
        <div class="flex items-center gap-2">
            @if ($article->type === 'news')
                <x-badge variant="info">{{ __('storefront.articles.type.news') }}</x-badge>
            @endif
            @if ($article->published_at)
                <time class="text-xs text-text-muted tabular-nums" datetime="{{ $article->published_at->toDateString() }}">{{ \App\Support\LocalizedDate::short($article->published_at) }}</time>
            @endif
        </div>
        <h3 class="mt-1 text-lg font-semibold text-text-primary group-hover:text-accent-700 group-hover:underline">{{ $title }}</h3>
        @if ($excerpt !== '')
            <p class="mt-1 line-clamp-2 text-sm text-text-secondary">{{ $excerpt }}</p>
        @endif
    </div>
</a>
