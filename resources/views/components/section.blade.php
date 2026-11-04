{{-- Ruled section: edge to edge on phones, bordered rectangle at md+. --}}
@props(['title' => null, 'flush' => false])

<section {{ $attributes->merge(['class' => '-mx-4 border-y border-border-strong bg-surface md:mx-0 md:border-x']) }}>
    @if ($title || isset($header))
        <header class="flex min-h-12 items-center justify-between gap-3 border-b border-border bg-surface-subtle px-4 py-2">
            <h2 class="text-lg font-semibold text-text-primary">{{ $title }}</h2>
            @isset($header){{ $header }}@endisset
        </header>
    @endif
    <div class="{{ $flush ? '' : 'p-4 md:p-5' }}">{{ $slot }}</div>
</section>
