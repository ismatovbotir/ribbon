{{-- Generic CMS page (spec 19 7.1 + spec 20). Body is sanitized HTML (Page::sanitizeBody on save) - the only unescaped output.
     Same three-column frame as articles on xl+: contents | text | help card; one column on phones. --}}
@php
    $toc = \App\Support\TableOfContents::build($body);
@endphp
<article class="mx-auto max-w-[42rem] xl:grid xl:max-w-none xl:grid-cols-[minmax(0,1fr)_minmax(0,42rem)_minmax(0,1fr)] xl:grid-rows-[auto_1fr] xl:gap-x-12">
    <div class="xl:col-start-2 xl:row-start-1">
        <nav aria-label="{{ __('storefront.catalog.breadcrumb_label') }}" class="no-scrollbar -mx-4 flex items-center gap-1.5 overflow-x-auto px-4 text-sm whitespace-nowrap md:mx-0 md:px-0">
            <a href="{{ route('storefront.home') }}" wire:navigate class="hit text-text-secondary hover:text-text-primary hover:underline">{{ __('storefront.catalog.breadcrumb_home') }}</a>
            <span class="text-text-muted" aria-hidden="true">/</span>
            <span class="max-w-[16rem] truncate font-medium text-text-primary" aria-current="page">{{ $title }}</span>
        </nav>

        <header class="mt-3 border-b border-border pb-4">
            <h1 class="text-xl font-semibold text-balance text-text-primary md:text-2xl">{{ $title }}</h1>
            @if ($lead !== '')
                <p class="mt-2 text-base text-text-secondary md:text-lg">{{ $lead }}</p>
            @endif
        </header>
    </div>

    <div class="mt-6 xl:col-start-1 xl:row-span-2 xl:row-start-1 xl:mt-0">
        <x-storefront.toc :items="$toc['items']" />
    </div>

    <div class="xl:col-start-2 xl:row-start-2">
        <x-storefront.prose class="mt-6">{!! $toc['html'] !!}</x-storefront.prose>

        @if ($showSellersCta)
            {{-- Cobalt primary, not yellow: yellow is reserved for the buyer-flow CTA (one per view). --}}
            <section class="mt-8 border-t border-border-strong pt-6" aria-label="{{ __('storefront.pages.sellers_cta_title') }}">
                <p class="font-display text-lg font-semibold text-text-primary">{{ __('storefront.pages.sellers_cta_title') }}</p>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                    <x-button tag="a" variant="primary" size="lg" href="{{ route('sellers.register') }}" wire:navigate>{{ __('storefront.nav.become_seller') }}</x-button>
                    <x-button tag="a" variant="secondary" size="lg" href="{{ route('login') }}" wire:navigate>{{ __('storefront.nav.seller_login') }}</x-button>
                </div>
            </section>
        @endif

        @if ($showUpdated)
            <p class="mt-8 text-xs text-text-muted tabular-nums">{{ __('storefront.pages.updated') }} {{ $page->updated_at->format('d.m.Y') }}</p>
        @endif
    </div>

    {{-- Help card: after the text on phones, the right column on xl+. --}}
    <div class="mt-8 xl:col-start-3 xl:row-span-2 xl:row-start-1 xl:mt-0">
        <aside aria-labelledby="page-help-title" class="rounded-md border border-border bg-surface-band p-4 xl:sticky xl:top-24">
            <h2 id="page-help-title" class="font-display text-base font-semibold text-text-primary">{{ __('storefront.contact.form_title') }}</h2>
            <p class="mt-1 text-sm text-text-secondary">{{ __('storefront.contact.lead') }}</p>
            <x-button tag="a" variant="secondary" href="{{ route('storefront.contact') }}" wire:navigate class="mt-3 w-full">{{ __('storefront.nav.contact') }}</x-button>
        </aside>
    </div>
</article>
