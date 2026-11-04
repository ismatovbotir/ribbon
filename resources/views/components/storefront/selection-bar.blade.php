@props(['count' => 0])

{{-- Bottom bar, "selection" mode (spec 18 section 11). Hidden at lg+. Seeded from PHP so there is no flash. --}}
<div
    x-show="selectionCount > 0"
    @if ($count < 1) x-cloak @endif
    class="fixed inset-x-0 bottom-0 z-(--z-bar) elev-up border-t border-border-strong bg-surface px-3 pt-1.5 pb-[calc(0.375rem+env(safe-area-inset-bottom))] lg:hidden"
>
    <div class="mx-auto flex max-w-xl items-center gap-3">
        <p class="min-w-0 flex-1 text-sm text-text-primary" aria-live="polite">
            <span class="font-medium tabular-nums" x-text="selectionCount">{{ $count }}</span>
            <span x-text="selectionCount === 1 ? @js(__('storefront.selection_bar.item_one')) : @js(__('storefront.selection_bar.items_other'))">{{ $count === 1 ? __('storefront.selection_bar.item_one') : __('storefront.selection_bar.items_other') }}</span>
        </p>
        <x-button tag="a" variant="cta" href="{{ route('storefront.offer-request.show') }}" wire:navigate class="min-h-11 shrink-0 px-5 font-semibold">{{ __('storefront.selection_bar.send') }}</x-button>
    </div>
</div>
