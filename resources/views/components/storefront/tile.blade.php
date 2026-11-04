@props(['href', 'name', 'count' => null, 'image' => null])

<a href="{{ $href }}" wire:navigate {{ $attributes->class(['group flex min-h-28 flex-col justify-between gap-4 rounded-md border border-border bg-surface p-4 hover:border-accent-700 hover:bg-surface-hover']) }}>
    <span class="flex size-12 items-center justify-center overflow-hidden rounded-sm border border-border bg-surface-subtle text-text-muted">
        @if ($image)
            <img src="{{ $image }}" alt="" width="48" height="48" loading="lazy" class="size-full object-cover">
        @else
            <x-storefront.icon name="cube" class="size-6" />
        @endif
    </span>
    <span class="flex items-end justify-between gap-2">
        <span class="min-w-0">
            <span class="line-clamp-2 block font-display text-base font-semibold text-text-primary">{{ $name }}</span>
            @if ($count !== null)
                <span class="text-xs text-text-muted tabular-nums">{{ $count }}</span>
            @endif
        </span>
        <x-storefront.icon name="arrow-right" class="size-4 shrink-0 text-text-muted group-hover:text-accent-700" stroke="2" />
    </span>
</a>
