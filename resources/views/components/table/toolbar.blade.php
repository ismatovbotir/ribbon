{{-- Search + filters. Slots:
       search  : the <x-input type="search" wire:model.live...> (rendered twice: phone row and md+ bar)
       filters : the filter fields (rendered in the phone Filters sheet and in the md+ inline bar)
       chips   : optional applied-filter <x-table.chip>s
       end     : optional md+ right-side controls (export, columns...)
     Props: sheetName, activeCount, resultCount, reset (Livewire action that clears filters). --}}
@props(['sheetName' => 'filters', 'activeCount' => 0, 'resultCount' => null, 'reset' => 'clearFilters'])

<div class="border-b border-border">
    <div class="flex gap-2 p-3 md:hidden">
        <div class="min-w-0 flex-1">{{ $search }}</div>
        @isset($filters)
            <x-button variant="secondary" aria-haspopup="dialog" x-on:click="$dispatch('open-sheet', '{{ $sheetName }}')">
                <x-icon name="funnel" class="size-4" />
                {{ __('admin.ui.filters') }}
                @if ($activeCount > 0)
                    <span class="inline-flex h-5 min-w-5 items-center justify-center bg-accent-700 px-1 font-mono text-xs text-white">{{ $activeCount }}</span>
                @endif
            </x-button>
        @endisset
    </div>

    <div class="hidden items-center gap-3 px-4 py-3 md:flex">
        <div class="w-64">{{ $search }}</div>
        @isset($filters)
            <div class="flex flex-wrap items-center gap-3 [&>*]:w-44">{{ $filters }}</div>
        @endisset
        @isset($end)
            <div class="ml-auto flex items-center gap-2">{{ $end }}</div>
        @endisset
    </div>

    @isset($chips)
        @if ($activeCount > 0)
            <div class="-mt-1 flex snap-x items-center gap-2 overflow-x-auto px-3 pb-3 md:px-4">
                {{ $chips }}
                <button type="button" wire:click="{{ $reset }}" class="h-9 shrink-0 px-2 text-xs font-medium text-accent-700 hover:underline">{{ __('admin.ui.clear_all') }}</button>
            </div>
        @endif
    @endisset
</div>

@isset($filters)
    <x-sheet :name="$sheetName" :title="__('admin.ui.filters')">
        <div class="space-y-4">{{ $filters }}</div>
        <x-slot:footer>
            <x-button variant="secondary" wire:click="{{ $reset }}">{{ __('admin.ui.reset') }}</x-button>
            <x-button variant="primary" x-on:click="$dispatch('close-sheet', '{{ $sheetName }}')">
                @if ($resultCount !== null) {{ __('admin.ui.show_results', ['count' => $resultCount]) }} @else {{ __('admin.ui.apply') }} @endif
            </x-button>
        </x-slot:footer>
    </x-sheet>
@endisset
