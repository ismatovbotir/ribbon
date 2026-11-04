{{-- Sticky action bar. Place as the LAST child of the form/page content so
     sticky works in the main scroller. Default slot = buttons (max two). --}}
@props(['dirty' => false, 'static' => false])

<div {{ $attributes->merge(['class' => ($static ? 'shrink-0 ' : 'sticky bottom-0 z-(--z-bar) -mx-4 mt-6 md:-mx-6 md:px-6 ').'flex items-center gap-2 border-t border-border-strong bg-surface px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]']) }}>
    @if (isset($status) || $dirty)
        <span class="hidden min-w-0 flex-1 truncate text-xs text-text-muted md:block" @if ($dirty) wire:dirty @endif>{{ $status ?? 'Unsaved changes' }}</span>
    @endif
    <div class="flex flex-1 gap-2 md:flex-none md:ml-auto [&>*]:flex-1 md:[&>*]:flex-none">{{ $slot }}</div>
</div>
