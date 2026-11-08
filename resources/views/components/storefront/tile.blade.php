@props(['href', 'name', 'count' => null, 'image' => null, 'icon' => null])

<a href="{{ $href }}" wire:navigate {{ $attributes->class(['group flex min-h-40 flex-col items-center justify-center gap-3 rounded-md border border-border bg-surface p-4 text-center hover:border-accent-700 hover:bg-surface-hover md:p-6']) }}>
    <span class="flex size-14 items-center justify-center overflow-hidden rounded-sm border border-border bg-surface-subtle text-accent-700">
        @if ($image)
            <img src="{{ $image }}" alt="" width="56" height="56" loading="lazy" class="size-full object-cover">
        @else
            <x-storefront.icon :name="$icon ?: 'product'" class="size-7" />
        @endif
    </span>
    <span class="min-w-0">
        <span class="line-clamp-2 block font-display text-base font-semibold text-balance text-text-primary group-hover:text-accent-700">{{ $name }}</span>
        @if ($count !== null)
            <span class="mt-1 block text-sm text-text-muted tabular-nums">{{ trans_choice('storefront.home.tile_count', $count, ['count' => $count]) }}</span>
        @endif
    </span>
</a>
