@php
    $coverImageUrl = $article->cover_image_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($article->cover_image_path)
        : null;
@endphp

<div class="mx-auto flex max-w-3xl flex-col gap-6">
    <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-sm">
        <a href="{{ route('storefront.home') }}" wire:navigate class="text-text-secondary hover:text-text-primary">{{ __('storefront.catalog.breadcrumb_home') }}</a>
        <span class="text-text-muted" aria-hidden="true">/</span>
        <a href="{{ route('storefront.articles.index') }}" wire:navigate class="text-text-secondary hover:text-text-primary">{{ __('storefront.articles.title') }}</a>
        <span class="text-text-muted" aria-hidden="true">/</span>
        <span class="line-clamp-1 font-medium text-text-primary">{{ $title }}</span>
    </nav>

    <header class="flex flex-col gap-3">
        <p class="text-xs font-medium tracking-wide text-text-muted uppercase">{{ \App\Support\LocalizedDate::short($article->published_at) }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-text-primary md:text-4xl">{{ $title }}</h1>
    </header>

    @if ($coverImageUrl)
        <img src="{{ $coverImageUrl }}" alt="{{ $title }}" class="aspect-[16/9] w-full rounded-2xl object-cover">
    @endif

    @if (trim(strip_tags($body)) !== '')
        {{-- flow-root, not just prose's default block, so a floated image
             (admin's align-left/right editor buttons) can't visually
             overflow past this container when it's the last element. --}}
        <div class="prose prose-neutral flow-root max-w-none prose-headings:font-bold prose-headings:tracking-tight prose-a:text-accent-700 prose-img:rounded-2xl">
            {!! $body !!}
        </div>
    @else
        <p class="text-text-secondary">{{ __('storefront.articles.empty_body') }}</p>
    @endif

    <div class="border-t border-border pt-6">
        <a href="{{ route('storefront.articles.index') }}" wire:navigate class="text-sm font-medium text-accent-700 hover:underline">
            ← {{ __('storefront.articles.back_to_articles') }}
        </a>
    </div>
</div>
