@props(['current' => 'articles'])

{{-- Articles | News switch (spec 19 section 5): two real links to different pages, not a tablist. --}}
@php
    $items = [
        'articles' => ['label' => __('storefront.articles.type.article'), 'url' => route('storefront.articles.index')],
        'news' => ['label' => __('storefront.articles.type.news'), 'url' => route('storefront.news.index')],
    ];
@endphp
<nav aria-label="{{ __('storefront.articles.type_label') }}" {{ $attributes->class(['no-scrollbar -mx-4 flex overflow-x-auto border-b border-border-strong px-4 md:mx-0 md:px-0']) }}>
    @foreach ($items as $key => $item)
        <a
            href="{{ $item['url'] }}"
            wire:navigate
            @if ($current === $key) aria-current="page" @endif
            class="flex h-11 shrink-0 items-center border-b-2 px-4 text-sm font-medium {{ $current === $key ? 'border-accent-700 text-accent-800' : 'border-transparent text-text-secondary hover:text-text-primary' }}"
        >{{ $item['label'] }}</a>
    @endforeach
</nav>
