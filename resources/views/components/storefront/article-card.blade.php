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
    $articleUrl = route('storefront.articles.show', ['articleSlug' => $slug]);
@endphp

<a href="{{ $articleUrl }}" wire:navigate class="group flex flex-col overflow-hidden rounded-xl">
    <div class="aspect-[16/9] overflow-hidden rounded-xl bg-surface-subtle">
        @if ($article->cover_image_path)
            <img
                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($article->cover_image_path) }}"
                alt="{{ $title }}"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                loading="lazy"
            >
        @else
            <div class="flex h-full w-full items-center justify-center text-text-muted">
                <svg class="h-8 w-8" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M5 3.5h10v13l-2.5-1.5-2.5 1.5-2.5-1.5-2.5 1.5v-13Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                </svg>
            </div>
        @endif
    </div>

    <div class="mt-4">
        <div class="flex items-center gap-2">
            @if ($article->type === 'news')
                <x-badge variant="info">{{ __('storefront.articles.type.news') }}</x-badge>
            @endif
            <p class="text-xs font-medium tracking-wide text-text-muted uppercase">{{ \App\Support\LocalizedDate::short($article->published_at) }}</p>
        </div>
        <h3 class="mt-1 text-lg font-bold text-text-primary group-hover:text-accent-700">{{ $title }}</h3>
        @if ($excerpt !== '')
            <p class="mt-1.5 line-clamp-2 text-sm text-text-secondary">{{ $excerpt }}</p>
        @endif
    </div>
</a>
