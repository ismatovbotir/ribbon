@props(['id', 'value' => '', 'compact' => false, 'hero' => false])

{{-- Header / mobile-row search (spec 18 3.2-3.3 + spec 20 10.2). Plain GET form to /search, works without JS.
     The submit is the attached right segment of one rounded field. $hero = the 56px home search (rounded-md). --}}
<form action="/search" method="GET" role="search" {{ $attributes->class(['relative']) }}>
    <label for="{{ $id }}" class="sr-only">{{ __('storefront.nav.search_submit') }}</label>
    <x-storefront.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-text-muted" />
    <x-input id="{{ $id }}" type="search" name="q" :value="$value" enterkeyhint="search" autocomplete="off" placeholder="{{ __('storefront.nav.search_placeholder') }}" class="{{ $hero ? 'h-14 rounded-md pl-11 pr-28' : ($compact ? 'pl-10 pr-14' : 'pl-10 pr-24') }}" />
    @if ($compact)
        <button type="submit" aria-label="{{ __('storefront.nav.search_submit') }}" class="absolute top-0 right-0 flex h-full w-12 items-center justify-center rounded-l-none rounded-r-sm bg-accent-700 text-white hover:bg-accent-800">
            <x-storefront.icon name="arrow-right" class="size-5" stroke="2" />
        </button>
    @else
        <button type="submit" class="absolute top-0 right-0 h-full rounded-l-none {{ $hero ? 'rounded-r-md px-6' : 'rounded-r-sm px-4' }} bg-accent-700 text-sm font-semibold text-white hover:bg-accent-800">{{ __('storefront.nav.search_submit') }}</button>
    @endif
</form>
