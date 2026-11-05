{{-- Selection bar (not yet wired to a list: needs a selection contract on the Livewire component).
     md+: replaces the toolbar row. Under md: sticky bottom action bar opening an action sheet.
     Slot = the entity's bulk action buttons / menu items. --}}
@props(['count' => 0, 'clear' => 'clearSelection'])

@if ($count > 0)
    <div class="hidden items-center gap-3 border-b border-accent-200 bg-accent-50 px-4 py-2 md:flex">
        <span class="text-sm">{{ __('admin.ui.selected_count', ['count' => $count]) }}</span>
        <div class="flex items-center gap-2">{{ $slot }}</div>
        <x-button variant="ghost" class="ml-auto" wire:click="{{ $clear }}">{{ __('admin.ui.clear') }}</x-button>
    </div>

    <x-action-bar class="md:hidden">
        <span class="flex min-w-0 flex-1 items-center text-sm"><span class="font-mono font-semibold">{{ $count }}</span>&nbsp;selected</span>
        <x-button variant="primary" x-on:click="$dispatch('open-sheet', 'bulk-actions')">{{ __('admin.ui.actions') }}</x-button>
        <x-icon-button wire:click="{{ $clear }}" :aria-label="__('admin.ui.clear_selection')"><x-icon name="close" class="size-5" /></x-icon-button>
    </x-action-bar>
    <x-sheet name="bulk-actions" :title="__('admin.ui.actions')" :flush="true">{{ $slot }}</x-sheet>
@endif
