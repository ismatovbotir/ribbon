{{-- Bottom sheet (mobile only; everything is md:hidden). Client-driven:
     open with  x-on:click="$dispatch('open-sheet', 'name')"; close with the X, Esc,
     scrim, or by setting `open = false` from slot content (it shares this x-data scope).
     Slots: default (body), `footer` (optional). Pass :flush="true" for action lists (no body padding). --}}
@props(['name', 'title', 'flush' => false])

<div
    x-data="{ open: false }"
    x-on:open-sheet.window="if ($event.detail === '{{ $name }}') open = true"
    x-on:close-sheet.window="if (! $event.detail || $event.detail === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
    x-on:livewire:navigated.window="open = false"
    class="md:hidden"
>
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-(--z-sheet-backdrop) bg-scrim" x-on:click="open = false"></div>
    <div
        x-show="open"
        x-cloak
        x-trap.noscroll.inert="open"
        x-transition:enter="transition duration-200 ease-out motion-reduce:transition-none"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition duration-150 ease-in motion-reduce:transition-none"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        role="dialog"
        aria-modal="true"
        aria-labelledby="sheet-{{ $name }}-title"
        class="fixed inset-x-0 bottom-0 z-(--z-sheet) flex max-h-[85dvh] flex-col border-t border-border-strong bg-surface-overlay"
    >
        <div class="flex h-14 shrink-0 items-center justify-between border-b border-border pr-1 pl-4">
            <h2 id="sheet-{{ $name }}-title" class="truncate text-base font-semibold text-text-primary">{{ $title }}</h2>
            <x-icon-button x-on:click="open = false" aria-label="Close">
                <x-icon name="close" class="size-5" />
            </x-icon-button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain {{ $flush ? '' : 'p-4' }}">{{ $slot }}</div>
        @isset($footer)
            <div class="flex shrink-0 gap-2 border-t border-border p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] [&>*]:flex-1">{{ $footer }}</div>
        @endisset
    </div>
</div>
