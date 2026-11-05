{{-- Modal. Server-driven (render inside @if) with a Livewire `close` action.
     kind="form": full-screen under md, centered max-w-lg at md+.
     kind="confirm": docked to the bottom under md, centered max-w-md at md+.
     Slots: default (body), `footer` (buttons; DOM order Cancel then Confirm). --}}
@props(['title', 'kind' => 'confirm', 'close' => 'closeModal'])

@php($confirm = $kind === 'confirm')

<div
    {{-- Livewire removes this modal on close, and a menu-opened modal's item is
         already gone, so focus returns to the last activator (app.js, QA N3-07). --}}
    x-data="{
        show: false,
        opener: window.ribbonLastActivator ?? null,
        destroy() {
            const opener = this.opener;
            setTimeout(() => { if (opener?.isConnected) opener.focus({ preventScroll: true }) });
        },
    }"
    x-init="$nextTick(() => show = true)"
    x-on:keydown.escape.window="$wire.{{ $close }}()"
>
    <div x-show="show" x-transition.opacity class="fixed inset-0 z-(--z-modal-backdrop) bg-scrim" x-on:click="$wire.{{ $close }}()"></div>
    <div class="{{ $confirm ? 'fixed inset-0 z-(--z-modal) flex items-end pointer-events-none md:items-center md:justify-center md:p-4' : 'fixed inset-0 z-(--z-modal) flex pointer-events-none md:items-center md:justify-center md:p-4' }}">
        <div
            x-show="show"
            x-trap.noscroll.inert="show"
            x-transition.opacity.duration.150ms
            role="{{ $confirm ? 'alertdialog' : 'dialog' }}"
            aria-modal="true"
            aria-labelledby="modal-title"
            class="modal-surface pointer-events-auto flex w-full flex-col border border-border-strong bg-surface-overlay md:rounded-lg {{ $confirm ? 'max-h-[85dvh] md:max-w-md' : 'h-full md:h-auto md:max-h-[85dvh] md:max-w-lg' }}"
        >
            @if ($confirm)
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 md:p-5">
                    <h2 id="modal-title" class="text-lg font-semibold text-text-primary">{{ $title }}</h2>
                    <div class="mt-2 text-sm text-text-secondary">{{ $slot }}</div>
                </div>
                @isset($footer)
                    <div class="flex flex-col-reverse gap-2 border-t border-border p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] md:flex-row md:justify-end md:border-0 md:p-5 md:pt-0 [&>*]:w-full md:[&>*]:w-auto">{{ $footer }}</div>
                @endisset
            @else
                <div class="flex h-14 shrink-0 items-center justify-between border-b border-border-strong pr-1 pl-4">
                    <h2 id="modal-title" class="truncate text-lg font-semibold text-text-primary">{{ $title }}</h2>
                    <x-icon-button x-on:click="$wire.{{ $close }}()" :aria-label="__('admin.ui.close')">
                        <x-icon name="close" class="size-5" />
                    </x-icon-button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 md:p-5">{{ $slot }}</div>
                @isset($footer)
                    <div class="flex shrink-0 items-center justify-end gap-2 border-t border-border-strong px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">{{ $footer }}</div>
                @endisset
            @endif
        </div>
    </div>
</div>
