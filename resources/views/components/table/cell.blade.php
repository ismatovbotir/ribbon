{{-- Table cell. role: select | primary | status | meta | actions. tier (meta only): 2 | 3.
     meta cells take a `label` shown only in the stacked mobile row. --}}
@props(['role' => 'meta', 'tier' => 2, 'label' => null, 'align' => 'left', 'mono' => false])

@php
    $c = match ($role) {
        'select' => 'col-start-1 row-start-1 row-span-2 hidden group-data-selectable/row:flex size-11 items-center justify-center md:group-data-selectable/row:table-cell md:size-auto md:w-10 md:px-4 md:align-middle',
        'primary' => 'col-start-1 group-data-selectable/row:col-start-2 row-start-1 min-w-0 self-center py-2 pr-1 text-sm font-medium text-text-primary md:table-cell md:w-full md:max-w-0 md:truncate md:px-4 md:py-0',
        'status' => 'col-start-1 group-data-selectable/row:col-start-2 row-start-2 pb-1 md:table-cell md:px-4 md:py-0',
        'actions' => 'relative z-10 col-start-2 group-data-selectable/row:col-start-3 row-start-1 row-span-2 flex items-start justify-end md:table-cell md:w-px md:px-4 md:text-right md:whitespace-nowrap',
        default => $tier >= 3
            ? 'hidden xl:table-cell xl:px-4 xl:text-sm xl:text-text-secondary'
            : 'col-start-1 group-data-selectable/row:col-start-2 flex items-baseline gap-2 pb-1 text-xs text-text-secondary md:table-cell md:px-4 md:text-sm',
    };
    if ($role === 'select') { $c .= ' relative z-10'; }
    if ($role === 'meta' && $align === 'right') { $c .= ' md:text-right'; }
    if ($mono) { $c .= ' font-mono tabular-nums'; }
@endphp

<td role="cell" {{ $attributes->merge(['class' => $c]) }}>
    @if ($role === 'meta' && $label && $tier < 3)
        <span class="shrink-0 text-text-muted md:hidden">{{ $label }}:</span>
    @endif
    {{ $slot }}
</td>
