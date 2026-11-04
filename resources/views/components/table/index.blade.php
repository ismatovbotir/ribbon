{{-- Responsive data table: stacked rows under md, real table from md, no horizontal scroll.
     Slots: toolbar (optional), head (<tr> of x-table.th), default (rows / x-table.skeleton), footer (pagination).
     Props: selectable (adds the select column to rows), loading (aria-busy). --}}
@props(['loading' => false])

<div {{ $attributes->merge(['class' => 'border border-border-strong bg-surface']) }}>
    @isset($toolbar){{ $toolbar }}@endisset
    <table role="table" class="block w-full md:table md:border-collapse" aria-busy="{{ $loading ? 'true' : 'false' }}">
        @isset($head)
            <thead role="rowgroup" class="sr-only md:not-sr-only md:table-header-group">
                <tr role="row" class="md:table-row">{{ $head }}</tr>
            </thead>
        @endisset
        {{ $slot }}
    </table>
    @isset($footer){{ $footer }}@endisset
</div>
