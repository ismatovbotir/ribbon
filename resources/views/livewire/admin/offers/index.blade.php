@php
    $activeCount = ($search !== '' ? 1 : 0) + ($status !== '' ? 1 : 0);
    $target = 'search, filterByStatus, status';
@endphp

<div>
    <x-page-header title="Commercial offers" subtitle="Multi-seller price-quote requests submitted by buyers through the public storefront." />

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <x-table>
        <x-slot:toolbar>
            <x-table.status-tabs aria-label="Filter by status">
                <x-table.tab wire:click="filterByStatus('')" :active="$status === ''" :count="$statusCounts->sum()">All</x-table.tab>
                @foreach ($statuses as $option)
                    <x-table.tab wire:key="tab-{{ $option }}" wire:click="filterByStatus('{{ $option }}')" :active="$status === $option" :count="$statusCounts[$option] ?? 0">{{ $option === 'pending' ? 'New' : ucfirst($option) }}</x-table.tab>
                @endforeach
            </x-table.status-tabs>

            <x-table.toolbar :active-count="$activeCount" :result-count="$requests->total()">
                <x-slot:search>
                    <x-input type="search" enterkeyhint="search" wire:model.live.debounce.400ms="search" placeholder="Search by phone or company…" aria-label="Search offers" />
                </x-slot:search>
                <x-slot:filters>
                    <x-select wire:model.live="status" aria-label="Status" class="md:hidden">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $option)
                            <option value="{{ $option }}">{{ $option === 'pending' ? 'New' : ucfirst($option) }}</option>
                        @endforeach
                    </x-select>
                </x-slot:filters>
                <x-slot:chips>
                    @if ($status !== '')
                        <x-table.chip wire:click="filterByStatus('{{ $status }}')">Status: {{ $status === 'pending' ? 'New' : ucfirst($status) }}</x-table.chip>
                    @endif
                    @if ($search !== '')
                        <x-table.chip wire:click="$set('search', '')">Search: {{ $search }}</x-table.chip>
                    @endif
                </x-slot:chips>
            </x-table.toolbar>
        </x-slot:toolbar>

        <x-slot:head>
            <x-table.th>Phone</x-table.th>
            <x-table.th>Status</x-table.th>
            <x-table.th align="right">Items</x-table.th>
            <x-table.th>Company</x-table.th>
            <x-table.th :tier="3">Received</x-table.th>
            <x-table.th><span class="sr-only">Actions</span></x-table.th>
        </x-slot:head>

        @if ($requests->isEmpty())
            <tbody class="block md:table-row-group">
                <tr class="block md:table-row"><td colspan="6" class="block md:table-cell">
                    @if ($activeCount > 0)
                        <x-empty-state variant="filtered" title="No results match your filters" description="Try a different search term or status.">
                            <x-slot:actions><x-button variant="secondary" wire:click="clearFilters">Clear filters</x-button></x-slot:actions>
                        </x-empty-state>
                    @else
                        <x-empty-state title="No commercial offer requests yet" description="Requests submitted via the public storefront will appear here." />
                    @endif
                </td></tr>
            </tbody>
        @else
            <tbody role="rowgroup" wire:loading.remove wire:target="{{ $target }}" class="block divide-y divide-border md:table-row-group">
                @foreach ($requests as $request)
                    <x-table.row wire:key="request-{{ $request->id }}">
                        <x-table.cell role="primary" class="font-mono">
                            <a href="{{ route('admin.offers.show', $request) }}" wire:navigate class="after:absolute after:inset-0 hover:text-accent-700 hover:underline">{{ $request->phone }}</a>
                        </x-table.cell>
                        <x-table.cell role="status"><x-status-badge type="offer" :status="$request->status" /></x-table.cell>
                        <x-table.cell role="meta" label="Items" :mono="true" align="right">{{ $request->items_count }}</x-table.cell>
                        <x-table.cell role="meta" label="Company">{{ $request->company_name ?: '—' }}</x-table.cell>
                        <x-table.cell role="meta" :tier="3" class="font-mono text-xs">{{ $request->created_at->format('d M Y') }}</x-table.cell>
                        <x-table.cell role="actions">
                            <x-menu :title="$request->phone" label="Row actions">
                                <x-menu.item icon="view" :href="route('admin.offers.show', $request)" wire:navigate>View</x-menu.item>
                            </x-menu>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </tbody>
            <x-table.skeleton :target="$target" :rows="6" :cols="5" />
        @endif

        <x-slot:footer>{{ $requests->links('pagination.admin') }}</x-slot:footer>
    </x-table>
</div>
