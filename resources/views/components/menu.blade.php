{{-- Overflow / row action menu. Anchored popover at md+, bottom action sheet under md.
     Slot items: <button role="menuitem" class="{{ ... }}"> or use <x-menu.item>.
     Optional `trigger` slot replaces the default kebab icon button. --}}
@props(['title' => null, 'label' => 'Actions'])

<div
    x-data="{ open: false, desktop: window.matchMedia('(min-width: 48rem)').matches, triggerEl: null }"
    x-init="triggerEl = $refs.trigger; window.matchMedia('(min-width: 48rem)').addEventListener('change', e => { desktop = e.matches; open = false })"
    x-on:keydown.escape.window="if (open) { open = false; (triggerEl.querySelector('button, a, [tabindex]') ?? triggerEl).focus() }"
    x-on:livewire:navigated.window="open = false"
    {{-- click.outside lives on the always-present wrapper, not on the
         x-if panel. A panel inserted by the opening click would otherwise
         catch that same click as "outside" and close at once (QA N-02).
         The teleported panel counts as outside this wrapper, so its own
         clicks are skipped: its handler closes it after the click is done. --}}
    x-on:click.outside="if (desktop && ! $event.target.closest('[data-menu-panel]')) open = false"
    class="relative inline-block text-left"
>
    <div
        x-ref="trigger"
        x-on:click="open = ! open"
        x-effect="$el.querySelector('[aria-haspopup]')?.setAttribute('aria-expanded', open ? 'true' : 'false')"
        class="inline-flex"
    >
        @isset($trigger)
            {{ $trigger }}
        @else
            <x-icon-button aria-label="{{ $label }}" aria-haspopup="menu" x-bind:aria-expanded="open">
                <x-icon name="overflow" class="size-5" />
            </x-icon-button>
        @endisset
    </div>

    <template x-if="open && desktop">
        {{-- Teleported to <body> so a table's overflow wrapper can't clip it. --}}
        <template x-teleport="body">
        <div
            data-menu-panel
            x-anchor.bottom-end.offset.4="triggerEl"
            {{-- Close on the next macrotask. Removing the x-if panel during the
                 click would drop a form inside it (e.g. Log out) before it
                 submits (QA N3-01). --}}
            x-on:click="setTimeout(() => open = false)"
            x-on:keydown.arrow-down.prevent="$focus.wrap().next()"
            x-on:keydown.arrow-up.prevent="$focus.wrap().previous()"
            x-on:keydown.home.prevent="$focus.first()"
            x-on:keydown.end.prevent="$focus.last()"
            x-on:keydown.tab.prevent="open = false; (triggerEl.querySelector('button, a, [tabindex]') ?? triggerEl).focus()"
            x-init="$nextTick(() => $focus.first())"
            role="menu"
            class="z-(--z-dropdown) min-w-48 max-w-72 border border-border-strong bg-surface-overlay py-1"
        >
            {{ $slot }}
        </div>
        </template>
    </template>

    <template x-if="open && ! desktop">
        {{-- Teleported to <body>. Inside a positioned <td> the sheet was
             trapped in that cell's stacking context, so later rows' triggers
             showed through it (QA N3-02). --}}
        <template x-teleport="body">
        <div>
            <div class="fixed inset-0 z-(--z-sheet-backdrop) bg-scrim" x-on:click="open = false"></div>
            <div
                x-trap.noscroll.inert="open"
                role="dialog"
                aria-modal="true"
                aria-label="{{ $title ?? $label }}"
                class="fixed inset-x-0 bottom-0 z-(--z-sheet) flex max-h-[85dvh] flex-col border-t border-border-strong bg-surface-overlay"
            >
                <div class="flex h-14 shrink-0 items-center justify-between border-b border-border pr-1 pl-4">
                    <p class="truncate text-base font-semibold text-text-primary">{{ $title ?? $label }}</p>
                    <x-icon-button x-on:click="open = false" aria-label="Close">
                        <x-icon name="close" class="size-5" />
                    </x-icon-button>
                </div>
                <div role="menu" x-on:click="setTimeout(() => open = false)" class="min-h-0 flex-1 overflow-y-auto overscroll-contain pb-[env(safe-area-inset-bottom)]">
                    {{ $slot }}
                </div>
            </div>
        </div>
        </template>
    </template>
</div>
