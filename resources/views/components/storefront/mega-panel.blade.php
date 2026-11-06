@props(['mega', 'catalogActive' => false])

{{-- Catalog mega menu, two-pane (spec 22). lg+ only. A plain wrapper without `relative`: the sticky <header> is the
     positioning parent of the panel. Disclosure, not an ARIA menu. Every category and pane link is in the DOM at
     render (crawlable); panes are only shown/hidden. --}}
@php
    $cats = $mega['categories'];
    $startId = collect($cats)->firstWhere('active', true)['id']
        ?? collect($cats)->first(fn ($c) => $c['count'] > 0)['id']
        ?? ($cats[0]['id'] ?? null);
    $label = 'text-xs font-semibold uppercase tracking-[0.04em] text-text-muted';
@endphp
<div
    x-data="megaMenu(@js($startId))"
    class="hidden lg:block"
    x-on:pointerenter="hoverOpen($event)"
    x-on:pointerleave="hoverClose($event)"
    x-on:focusout="focusOut($event)"
    x-on:keydown.escape.window="open && close()"
    x-on:livewire:navigate.window="close()"
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggle()"
        x-on:keydown.arrow-down.prevent="focusFirst()"
        aria-expanded="false"
        x-bind:aria-expanded="open"
        aria-controls="mega-catalog"
        @if ($catalogActive) aria-current="true" @endif
        x-bind:class="open ? 'border-accent-700! bg-accent-50! text-accent-800!' : ''"
        class="hit inline-flex h-control items-center gap-2 rounded-sm border px-3 text-sm font-semibold whitespace-nowrap {{ $catalogActive ? 'border-accent-700' : 'border-border-input' }} bg-surface text-text-primary hover:bg-surface-hover"
    >
        {{ __('storefront.nav.catalog') }}
        <x-storefront.icon name="chevron-down" class="size-4 transition-transform motion-reduce:transition-none" x-bind:class="open && 'rotate-180'" />
    </button>

    {{-- scrim: only catches clicks once pinned; never blocks hover-leave --}}
    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.100ms
        x-bind:class="pinned ? 'pointer-events-auto' : 'pointer-events-none'"
        x-on:click="close()"
        aria-hidden="true"
        class="fixed inset-x-0 top-(--spacing-storefront-header) bottom-0 bg-scrim motion-reduce:transition-none"
    ></div>

    <nav
        id="mega-catalog"
        x-ref="panel"
        x-show="open"
        x-cloak
        x-transition.opacity.duration.100ms
        aria-label="{{ __('storefront.nav.catalog_menu_label') }}"
        class="pointer-events-none absolute inset-x-0 top-full hidden px-6 pt-2 motion-reduce:transition-none lg:block"
    >
        {{-- Narrower than the page, with a border on all sides, so it's clear where the menu starts and ends. --}}
        <div class="elev-2 pointer-events-auto mx-auto flex max-h-[min(35rem,calc(100dvh-var(--spacing-storefront-header)-1rem))] max-w-6xl flex-col overflow-hidden rounded-md border border-border-strong bg-surface">
            <div class="grid min-h-0 flex-1 grid-cols-[18.75rem_minmax(0,1fr)]">
                {{-- Left: category list (the only part that may scroll). Roving tabindex: Tab leaves the list into the pane. --}}
                <ul
                    x-ref="list"
                    class="min-h-0 overflow-y-auto overscroll-contain border-r border-border py-2"
                    x-on:pointermove="track($event)"
                    x-on:keydown.arrow-down.prevent="moveFocus(1)"
                    x-on:keydown.arrow-up.prevent="moveFocus(-1)"
                    x-on:keydown.home.prevent="moveFocus('first')"
                    x-on:keydown.end.prevent="moveFocus('last')"
                    x-on:keydown.arrow-right.prevent="enterPane()"
                >
                    @foreach ($cats as $c)
                        @php($empty = $c['count'] === 0)
                        <li>
                            <a
                                href="{{ $c['url'] }}"
                                wire:navigate.hover
                                data-cat="{{ $c['id'] }}"
                                aria-controls="mega-pane-{{ $c['id'] }}"
                                x-on:click="close()"
                                x-on:pointerenter="hoverCat({{ $c['id'] }}, $event)"
                                x-on:focus="active = {{ $c['id'] }}"
                                x-bind:tabindex="active === {{ $c['id'] }} ? 0 : -1"
                                @if ($c['active']) aria-current="page" @endif
                                x-bind:class="active === {{ $c['id'] }} ? 'bg-accent-50 text-accent-800 before:bg-accent-700' : '{{ $empty ? 'text-text-muted' : 'text-text-primary' }} before:bg-transparent hover:bg-surface-hover'"
                                class="relative flex h-11 items-center gap-3 pr-3 pl-5 text-sm font-medium before:absolute before:inset-y-0 before:left-0 before:w-[3px] {{ $empty ? 'text-text-muted' : 'text-text-primary' }}"
                            >
                                <x-storefront.icon :name="$c['icon']" class="size-6 shrink-0 {{ $empty ? 'opacity-60' : '' }}" />
                                <span class="min-w-0 flex-1 truncate">{{ $c['name'] }}</span>
                                <span class="font-mono text-xs text-text-muted tabular-nums">{{ $c['count'] }}</span>
                                <x-storefront.icon name="chevron-right" class="size-4 shrink-0" x-bind:class="active === {{ $c['id'] }} ? 'opacity-100' : 'opacity-0'" />
                            </a>
                        </li>
                    @endforeach
                </ul>

                {{-- Right: one pane per category; only the active one is shown --}}
                <div x-ref="panes" class="min-h-0 overflow-y-auto" x-on:keydown.arrow-left.prevent="leavePane()">
                    @foreach ($cats as $c)
                        <section
                            id="mega-pane-{{ $c['id'] }}"
                            x-show="active === {{ $c['id'] }}"
                            @if ($c['id'] !== $startId) x-cloak @endif
                            aria-label="{{ $c['name'] }}"
                            class="p-6"
                        >
                            @if ($c['count'] === 0)
                                <p class="font-display text-lg font-semibold text-text-primary">{{ $c['name'] }}</p>
                                <p class="mt-2 text-sm text-text-secondary">{{ __('storefront.nav.mega_empty') }}</p>
                                <a href="{{ route('sellers.register') }}" wire:navigate x-on:click="close()" class="mt-3 inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-accent-700 underline underline-offset-2 hover:text-accent-800">
                                    {{ __('storefront.nav.mega_sell_cta') }}
                                    <x-storefront.icon name="arrow-right" class="size-4" />
                                </a>
                            @else
                                <div class="flex items-baseline justify-between gap-4">
                                    <p class="min-w-0 font-display text-lg font-semibold text-text-primary">{{ $c['name'] }}</p>
                                    <a href="{{ $c['url'] }}" wire:navigate.hover x-on:click="close()" class="inline-flex min-h-11 shrink-0 items-center gap-1.5 text-sm font-semibold text-accent-700 hover:text-accent-800 hover:underline">
                                        {{ __('storefront.nav.mega_view_all') }}
                                        <span class="font-mono tabular-nums">{{ $c['count'] }}</span>
                                        <x-storefront.icon name="arrow-right" class="size-4" />
                                    </a>
                                </div>

                                @if ($c['verified_sellers'] > 0)
                                    <p class="mt-1 flex items-center gap-1.5 text-sm text-text-secondary">
                                        <x-storefront.icon name="security" class="size-4 shrink-0 text-accent-700" />
                                        {{ trans_choice('storefront.nav.mega_verified_sellers', $c['verified_sellers'], ['count' => $c['verified_sellers']]) }}
                                    </p>
                                @endif

                                <div class="mt-4 space-y-4">
                                    @foreach ($c['groups'] as $g)
                                        <div>
                                            <p class="{{ $label }}">{{ $g['label'] }}</p>
                                            <ul class="mt-1.5 flex flex-wrap gap-1.5">
                                                @foreach ($g['links'] as $l)
                                                    <li><a href="{{ $l['url'] }}" wire:navigate.hover x-on:click="close()" class="inline-flex h-8 items-center rounded-sm border border-border bg-surface px-2.5 text-sm text-text-secondary hover:border-accent-700 hover:bg-accent-50 hover:text-accent-800 {{ $g['mono'] ? 'font-mono tabular-nums' : '' }}">{{ $l['label'] }}</a></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach

                                    @if ($c['brands'])
                                        <div>
                                            <p class="{{ $label }}">{{ __('storefront.nav.mega_brands') }}</p>
                                            <ul class="mt-1 flex flex-wrap items-center text-sm">
                                                @foreach ($c['brands'] as $b)
                                                    <li class="flex items-center">
                                                        @unless ($loop->first)<span aria-hidden="true" class="px-2 text-text-muted">·</span>@endunless
                                                        <a href="{{ $b['url'] }}" wire:navigate.hover x-on:click="close()" class="inline-flex min-h-8 items-center text-text-secondary hover:text-accent-700 hover:underline">{{ $b['label'] }}</a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    @if ($c['guide'])
                                        <a href="{{ $c['guide']['url'] }}" wire:navigate x-on:click="close()" class="group flex max-w-md items-start gap-3 rounded-sm border border-border bg-surface-subtle p-3 hover:border-accent-700">
                                            <x-storefront.icon name="book" class="size-5 shrink-0 text-accent-700" />
                                            <span class="min-w-0">
                                                <span class="block {{ $label }}">{{ __('storefront.nav.mega_guide_label') }}</span>
                                                <span class="mt-0.5 block line-clamp-2 text-sm font-semibold text-text-primary group-hover:text-accent-700">{{ $c['guide']['title'] }}</span>
                                                <span class="mt-1 inline-flex items-center gap-1 text-sm font-medium text-accent-700">{{ __('storefront.nav.mega_guide_read') }} <x-storefront.icon name="arrow-right" class="size-4" /></span>
                                            </span>
                                        </a>
                                    @endif
                                </div>
                            @endif
                        </section>
                    @endforeach
                </div>
            </div>

            <div class="flex shrink-0 items-center justify-between gap-6 border-t border-border bg-surface-band px-6 py-2">
                @if ($mega['article'])
                    <a href="{{ $mega['article']['url'] }}" wire:navigate x-on:click="close()" class="group flex min-h-11 min-w-0 items-center gap-2 text-sm">
                        <span class="shrink-0 text-text-muted">{{ __('storefront.nav.mega_featured') }}:</span>
                        <span class="truncate font-medium text-text-primary group-hover:text-accent-700 group-hover:underline">{{ $mega['article']['title'] }}</span>
                    </a>
                @else
                    <span></span>
                @endif
                <p class="flex shrink-0 items-center gap-x-2 text-sm text-text-secondary">
                    {{ __('storefront.nav.mega_cant_find') }}
                    <a href="{{ route('storefront.contact') }}" wire:navigate x-on:click="close()" class="inline-flex min-h-11 items-center font-medium text-accent-700 underline underline-offset-2 hover:text-accent-800">{{ __('storefront.nav.mega_cant_find_link') }}</a>
                </p>
            </div>
        </div>
    </nav>
</div>
