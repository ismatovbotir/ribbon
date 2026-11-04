{{-- One nav link. `icon` = a name from the panel icon set (<x-icon>). --}}
@props(['href', 'active' => false, 'icon', 'label', 'count' => null])

<a href="{{ $href }}" wire:navigate
   @if ($active) aria-current="page" @endif
   class="flex h-11 items-center gap-3 border-l-[3px] px-4 text-sm dense:h-9 {{ $active ? 'border-accent-700 bg-accent-50 font-semibold text-accent-700' : 'border-transparent text-text-secondary hover:bg-surface-hover hover:text-text-primary' }}">
    <x-icon :name="$icon" class="size-5 shrink-0" />
    <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
    @if ($count)
        <span class="font-mono text-xs">{{ $count }}</span>
    @endif
</a>
