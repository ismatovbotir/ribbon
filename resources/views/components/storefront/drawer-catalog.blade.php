@props(['mega'])

{{-- Drawer catalog accordion (spec 19 4 + spec 20 10.3). Category name navigates; the 44px chevron toggles shortcuts. --}}
@php $activeId = collect($mega['categories'])->firstWhere('active', true)['id'] ?? null; @endphp
<div class="px-2" x-data="{ cat: @js($activeId) }">
    @foreach ($mega['categories'] as $c)
        <div>
            <div class="flex items-stretch gap-1">
                <a
                    href="{{ $c['url'] }}"
                    wire:navigate
                    x-on:click="drawerOpen = false"
                    @if ($c['active']) aria-current="page" @endif
                    class="flex min-h-11 min-w-0 flex-1 items-center justify-between gap-2 rounded-sm px-3 py-2 text-sm {{ $c['active'] ? 'bg-accent-50 font-semibold text-accent-800' : 'text-text-primary hover:bg-surface-hover' }}"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        <x-storefront.icon :name="$c['icon']" class="size-6 shrink-0 {{ $c['active'] ? '' : 'text-text-muted' }}" />
                        <span class="min-w-0">{{ $c['name'] }}</span>
                    </span>
                    <span class="font-mono text-xs text-text-muted tabular-nums">{{ $c['count'] }}</span>
                </a>
                @if ($c['groups'] || $c['brands'])
                    <button
                        type="button"
                        x-on:click="cat = cat === {{ $c['id'] }} ? null : {{ $c['id'] }}"
                        x-bind:aria-expanded="cat === {{ $c['id'] }}"
                        aria-expanded="false"
                        aria-controls="dc-{{ $c['id'] }}"
                        aria-label="{{ __('storefront.nav.shortcuts_of', ['name' => $c['name']]) }}"
                        class="flex size-11 shrink-0 items-center justify-center rounded-sm text-text-secondary hover:bg-surface-hover"
                    >
                        <x-storefront.icon name="chevron-down" class="size-4 transition-transform motion-reduce:transition-none" stroke="2" x-bind:class="cat === {{ $c['id'] }} && 'rotate-180'" />
                    </button>
                @endif
            </div>
            @if ($c['groups'] || $c['brands'])
                <div id="dc-{{ $c['id'] }}" x-show="cat === {{ $c['id'] }}" x-collapse @unless ($c['active']) x-cloak @endunless class="pb-2 pl-3 pr-1">
                    @foreach ($c['groups'] as $g)
                        <p class="pt-2 text-xs font-semibold text-text-muted uppercase tracking-[0.04em]">{{ $g['label'] }}</p>
                        <ul class="mt-1 flex flex-wrap gap-2">
                            @foreach ($g['links'] as $l)
                                <li><a href="{{ $l['url'] }}" wire:navigate x-on:click="drawerOpen = false" class="inline-flex h-11 items-center rounded-sm border border-border-input bg-surface px-3 text-sm text-text-primary hover:bg-surface-hover {{ $g['mono'] ? 'font-mono tabular-nums' : '' }}">{{ $l['label'] }}</a></li>
                            @endforeach
                        </ul>
                    @endforeach
                    @if ($c['brands'])
                        <p class="pt-2 text-xs font-semibold text-text-muted uppercase tracking-[0.04em]">{{ __('storefront.nav.mega_brands') }}</p>
                        <ul class="mt-1 flex flex-wrap gap-2">
                            @foreach ($c['brands'] as $b)
                                <li><a href="{{ $b['url'] }}" wire:navigate x-on:click="drawerOpen = false" class="inline-flex h-11 items-center rounded-sm border border-border-input bg-surface px-3 text-sm text-text-primary hover:bg-surface-hover">{{ $b['label'] }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>
    @endforeach
</div>
