{{-- Column header. tier 1 always (md+), 2 md+, 3 xl+. `sort` = 'asc'|'desc'|null (null + sortable=false => plain). --}}
@props(['tier' => 1, 'sortable' => false, 'sort' => null, 'align' => 'left'])

@php
    $display = $tier >= 3 ? 'hidden xl:table-cell' : 'md:table-cell';
    $ariaSort = $sort === 'asc' ? 'ascending' : ($sort === 'desc' ? 'descending' : 'none');
@endphp

<th role="columnheader" scope="col" @if ($sortable) aria-sort="{{ $ariaSort }}" @endif
    class="{{ $display }} md:sticky md:top-0 md:z-(--z-sticky) md:h-9 md:border-b md:border-border-strong md:bg-surface-subtle md:px-4 md:text-xs md:font-medium md:text-text-secondary {{ $align === 'right' ? 'md:text-right' : 'md:text-left' }}">
    @if ($sortable)
        <button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex h-9 w-full items-center gap-1 font-medium hover:text-text-primary '.($align === 'right' ? 'justify-end' : 'text-left')]) }}>
            {{ $slot }}
            <x-icon :name="$sort === 'desc' ? 'sort-desc' : ($sort === 'asc' ? 'sort-asc' : 'sort')" class="size-3.5 {{ $sort ? 'text-text-primary' : 'text-text-muted' }}" />
        </button>
    @else
        {{ $slot }}
    @endif
</th>
