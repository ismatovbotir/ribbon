@props(['selectable' => false, 'selected' => false])

<tr role="row" @if ($selectable) data-selectable @endif
    {{ $attributes->merge(['class' => 'group/row relative grid grid-cols-[minmax(0,1fr)_2.75rem] items-start px-3 data-selectable:grid-cols-[2.75rem_minmax(0,1fr)_2.75rem] data-selectable:px-1 md:table-row md:h-row-comfortable md:border-b md:border-border md:px-0 data-selectable:md:px-0 '.($selected ? 'bg-accent-50' : 'hover:bg-surface-hover')]) }}>
    {{ $slot }}
</tr>
