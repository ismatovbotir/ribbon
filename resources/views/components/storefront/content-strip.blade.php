@props(['links', 'forSellersUrl', 'forSellersActive' => false])

{{-- Secondary, non-sticky content menu (spec 19 3.3 + spec 20 10.3). lg+ only.
     Icon first; 'iconOnly' links (home, contact) keep their word for screen readers, crawlers and the tooltip. --}}
<nav aria-label="{{ __('storefront.nav.main_label') }}" class="hidden border-b border-border bg-surface-band lg:block">
    <div class="no-scrollbar mx-auto flex max-w-7xl items-center gap-1 overflow-x-auto px-6">
        @foreach ($links as $l)
            @php($iconOnly = $l['iconOnly'] ?? false)
            <a
                href="{{ $l['url'] }}"
                wire:navigate
                @if ($iconOnly) title="{{ $l['label'] }}" @endif
                @if ($l['active']) aria-current="page" @endif
                class="inline-flex h-11 shrink-0 items-center gap-1.5 border-b-2 px-3 text-sm font-medium whitespace-nowrap {{ $l['active'] ? 'border-accent-700 font-semibold text-accent-800' : 'border-transparent text-text-secondary hover:border-border-strong hover:text-text-primary' }}"
            >
                <x-storefront.icon :name="$l['icon']" class="size-5 shrink-0" />
                <span @class(['sr-only' => $iconOnly])>{{ $l['label'] }}</span>
            </a>
        @endforeach
        <a
            href="{{ $forSellersUrl }}"
            wire:navigate
            @if ($forSellersActive) aria-current="page" @endif
            class="ml-auto inline-flex h-9 shrink-0 items-center gap-1.5 rounded-sm border border-accent-700 bg-surface px-3 text-sm font-semibold whitespace-nowrap text-accent-700 hover:bg-accent-50"
        ><x-storefront.icon name="store" class="size-5" />{{ __('storefront.nav.for_sellers') }}</a>
    </div>
</nav>
