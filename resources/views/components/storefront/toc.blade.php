@props(['items'])

{{-- "Contents" of a long text page: a bordered box on phones, a sticky side list on xl+. --}}
@if (count($items) > 1)
    <nav aria-labelledby="toc-title" {{ $attributes->class(['rounded-md border border-border bg-surface-band p-4 xl:sticky xl:top-24 xl:border-0 xl:bg-transparent xl:p-0']) }}>
        <p id="toc-title" class="text-xs font-semibold tracking-[0.04em] text-text-muted uppercase">{{ __('storefront.articles.toc') }}</p>
        <ol class="mt-2 space-y-0.5 border-border text-sm xl:border-l">
            @foreach ($items as $item)
                <li>
                    <a href="#{{ $item['id'] }}" class="-ml-px flex min-h-9 items-center border-l-2 border-transparent py-1 pl-3 text-text-secondary hover:border-accent-700 hover:text-accent-700">{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ol>
    </nav>
@endif
