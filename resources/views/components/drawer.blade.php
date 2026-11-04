{{-- Form slide-over. Server-driven: render it inside @if($open) and pass the
     Livewire close action name. Full-screen under md, 28rem panel at md+.
     Slots: default (body), `footer` (action bar). --}}
@props(['title', 'close' => 'closeDrawer'])

<div
    x-data="{ show: false }"
    x-init="$nextTick(() => show = true)"
    x-on:keydown.escape.window="$wire.{{ $close }}()"
>
    <div x-show="show" x-transition.opacity class="fixed inset-0 z-(--z-sheet-backdrop) bg-scrim" x-on:click="$wire.{{ $close }}()"></div>
    <div
        x-show="show"
        x-trap.noscroll.inert="show"
        x-transition:enter="transition duration-200 ease-out motion-reduce:transition-none"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        role="dialog"
        aria-modal="true"
        aria-labelledby="drawer-title"
        class="fixed inset-0 z-(--z-sheet) flex flex-col bg-surface-overlay md:left-auto md:w-panel md:border-l md:border-border-strong"
    >
        <div class="flex h-14 shrink-0 items-center justify-between border-b border-border-strong pr-1 pl-4">
            <h2 id="drawer-title" class="truncate text-lg font-semibold text-text-primary">{{ $title }}</h2>
            <x-icon-button x-on:click="$wire.{{ $close }}()" aria-label="Close">
                <x-icon name="close" class="size-5" />
            </x-icon-button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 md:p-5">{{ $slot }}</div>
        @isset($footer)
            <div class="flex shrink-0 items-center gap-2 border-t border-border-strong px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">{{ $footer }}</div>
        @endisset
    </div>
</div>
