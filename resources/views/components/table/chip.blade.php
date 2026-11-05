{{-- Applied-filter chip: the whole chip is the remove button. --}}
<button type="button" {{ $attributes->merge(['class' => 'hit inline-flex h-9 shrink-0 snap-start items-center gap-2 border border-border-input bg-surface px-3 text-xs font-medium text-text-primary hover:bg-surface-hover']) }}>
    <span>{{ $slot }}</span>
    <x-icon name="close" class="size-3" />
    <span class="sr-only">{{ __('admin.ui.remove_filter') }}</span>
</button>
