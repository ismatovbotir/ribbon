{{-- Loading rows with the same grid as a real row. Put in the table with wire:loading + wire:target. --}}
@props(['rows' => 6, 'cols' => 4, 'target' => null])

<tbody {{ $attributes->merge(['class' => 'hidden divide-y divide-border']) }} @if ($target) wire:target="{{ $target }}" @endif wire:loading.class.remove="hidden" wire:loading.class="block! md:table-row-group!" aria-hidden="true">
    @for ($i = 0; $i < $rows; $i++)
        <tr class="block space-y-2 px-4 py-3 md:table-row md:h-row-comfortable md:space-y-0 md:border-b md:border-border md:p-0">
            <td class="block h-3 w-3/4 animate-pulse bg-surface-sunken motion-reduce:animate-none md:hidden"></td>
            <td class="block h-3 w-1/2 animate-pulse bg-surface-sunken motion-reduce:animate-none md:hidden"></td>
            <td class="block h-3 w-1/3 animate-pulse bg-surface-sunken motion-reduce:animate-none md:hidden"></td>
            @for ($c = 0; $c < $cols; $c++)
                <td class="hidden px-4 md:table-cell"><span class="block h-3 animate-pulse bg-surface-sunken motion-reduce:animate-none"></span></td>
            @endfor
        </tr>
    @endfor
</tbody>
