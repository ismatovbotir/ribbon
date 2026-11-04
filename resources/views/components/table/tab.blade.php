@props(['active' => false, 'count' => null])

<button type="button" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}"
    {{ $attributes->merge(['class' => 'flex h-11 shrink-0 snap-start items-center gap-2 border-b-[3px] px-4 text-sm font-medium whitespace-nowrap dense:h-10 '.($active ? 'border-accent-700 text-accent-700' : 'border-transparent text-text-secondary hover:text-text-primary')]) }}>
    {{ $slot }}
    @if ($count !== null)
        <span class="font-mono text-xs">{{ $count }}</span>
    @endif
</button>
