@props(['links', 'forSellersUrl', 'forSellersActive' => false])

{{-- Secondary, non-sticky content menu (spec 19 3.3 + spec 20 10.3). lg+ only. --}}
<nav aria-label="{{ __('storefront.nav.main_label') }}" class="hidden border-b border-border bg-surface-band lg:block">
    <div class="no-scrollbar mx-auto flex max-w-7xl items-center gap-1 overflow-x-auto px-6">
        @foreach ($links as $l)
            <a
                href="{{ $l['url'] }}"
                wire:navigate
                @if ($l['active']) aria-current="page" @endif
                class="inline-flex h-11 shrink-0 items-center border-b-2 px-3 text-sm font-medium whitespace-nowrap {{ $l['active'] ? 'border-accent-700 font-semibold text-accent-800' : 'border-transparent text-text-secondary hover:border-border-strong hover:text-text-primary' }}"
            >{{ $l['label'] }}</a>
        @endforeach
        <a
            href="{{ $forSellersUrl }}"
            wire:navigate
            @if ($forSellersActive) aria-current="page" @endif
            class="ml-auto inline-flex h-9 shrink-0 items-center gap-1 rounded-sm border border-accent-700 bg-surface px-3 text-sm font-semibold whitespace-nowrap text-accent-700 hover:bg-accent-50"
        >{{ __('storefront.nav.for_sellers') }}<x-storefront.icon name="arrow-right" class="size-4" stroke="2" /></a>
    </div>
</nav>
