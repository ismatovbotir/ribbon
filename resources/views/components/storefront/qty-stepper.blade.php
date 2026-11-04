@props(['id' => 'qty', 'decrement' => 'decrementQty', 'increment' => 'incrementQty', 'min' => 1, 'value' => 1, 'decLabel' => null, 'incLabel' => null])

<div {{ $attributes->class(['inline-flex overflow-hidden rounded-sm border border-border-input divide-x divide-border-input']) }}>
    <button type="button" wire:click="{{ $decrement }}" @disabled($value <= $min) aria-label="{{ $decLabel ?? __('storefront.product_detail.qty_decrease') }}" class="flex size-11 items-center justify-center text-lg text-text-primary hover:bg-surface-hover disabled:cursor-not-allowed disabled:text-text-muted">&minus;</button>
    {{ $slot }}
    <button type="button" wire:click="{{ $increment }}" aria-label="{{ $incLabel ?? __('storefront.product_detail.qty_increase') }}" class="flex size-11 items-center justify-center text-lg text-text-primary hover:bg-surface-hover">&plus;</button>
</div>
