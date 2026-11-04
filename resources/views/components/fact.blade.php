{{-- Definition-list row. Wrap rows in <dl class="divide-y-0">. Use :mono for ids, phones, amounts. --}}
@props(['label', 'mono' => false])

<div class="grid grid-cols-1 border-b border-border py-2.5 last:border-b-0 md:grid-cols-[12rem_minmax(0,1fr)] md:gap-4">
    <dt class="text-xs font-medium text-text-secondary md:text-sm">{{ $label }}</dt>
    <dd {{ $attributes->merge(['class' => 'min-w-0 text-sm break-words text-text-primary '.($mono ? 'font-mono' : '')]) }}>{{ $slot }}</dd>
</div>
