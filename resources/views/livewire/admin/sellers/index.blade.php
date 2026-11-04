@php
    $activeCount = ($search !== '' ? 1 : 0) + ($status !== '' ? 1 : 0);
    $sortLabels = ['name' => 'Company', 'status' => 'Status', 'created_at' => 'Submitted'];
    $target = 'search, sortBy, filterByStatus, status, sortField, sortDirection';
@endphp

<div>
    <x-page-header title="Sellers" subtitle="Review and approve seller applications submitted through the public registration form." />

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <x-table>
        <x-slot:toolbar>
            <x-table.status-tabs aria-label="Filter by status">
                <x-table.tab wire:click="filterByStatus('')" :active="$status === ''" :count="$statusCounts->sum()">All</x-table.tab>
                @foreach ($statuses as $option)
                    <x-table.tab wire:key="tab-{{ $option }}" wire:click="filterByStatus('{{ $option }}')" :active="$status === $option" :count="$statusCounts[$option] ?? 0">{{ ucfirst($option) }}</x-table.tab>
                @endforeach
            </x-table.status-tabs>

            <x-table.toolbar :active-count="$activeCount" :result-count="$sellers->total()">
                <x-slot:search>
                    <x-input type="search" enterkeyhint="search" wire:model.live.debounce.400ms="search" placeholder="Search by name or phone…" aria-label="Search sellers" />
                </x-slot:search>
                <x-slot:filters>
                    <x-select wire:model.live="status" aria-label="Status" class="md:hidden">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $option)
                            <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                        @endforeach
                    </x-select>
                    <x-select wire:model.live="sortField" aria-label="Sort by" class="md:hidden">
                        @foreach ($sortLabels as $field => $label)
                            <option value="{{ $field }}">Sort by: {{ $label }}</option>
                        @endforeach
                    </x-select>
                    <x-select wire:model.live="sortDirection" aria-label="Sort direction" class="md:hidden">
                        <option value="asc">Ascending</option>
                        <option value="desc">Descending</option>
                    </x-select>
                </x-slot:filters>
                <x-slot:chips>
                    @if ($status !== '')
                        <x-table.chip wire:click="filterByStatus('{{ $status }}')">Status: {{ ucfirst($status) }}</x-table.chip>
                    @endif
                    @if ($search !== '')
                        <x-table.chip wire:click="$set('search', '')">Search: {{ $search }}</x-table.chip>
                    @endif
                </x-slot:chips>
            </x-table.toolbar>
        </x-slot:toolbar>

        <x-slot:head>
            <x-table.th :sortable="true" :sort="$sortField === 'name' ? $sortDirection : null" wire:click="sortBy('name')">Company</x-table.th>
            <x-table.th :sortable="true" :sort="$sortField === 'status' ? $sortDirection : null" wire:click="sortBy('status')">Status</x-table.th>
            <x-table.th>Phone</x-table.th>
            <x-table.th :tier="3" :sortable="true" :sort="$sortField === 'created_at' ? $sortDirection : null" wire:click="sortBy('created_at')">Submitted</x-table.th>
            <x-table.th><span class="sr-only">Actions</span></x-table.th>
        </x-slot:head>

        @if ($sellers->isEmpty())
            <tbody class="block md:table-row-group">
                <tr class="block md:table-row"><td colspan="5" class="block md:table-cell">
                    @if ($activeCount > 0)
                        <x-empty-state variant="filtered" title="No results match your filters" description="Try a different search term or status.">
                            <x-slot:actions><x-button variant="secondary" wire:click="clearFilters">Clear filters</x-button></x-slot:actions>
                        </x-empty-state>
                    @else
                        <x-empty-state title="No seller applications yet" description="Applications submitted via the public registration form will appear here." />
                    @endif
                </td></tr>
            </tbody>
        @else
            <tbody role="rowgroup" wire:loading.remove wire:target="{{ $target }}" class="block divide-y divide-border md:table-row-group">
                @foreach ($sellers as $seller)
                    <x-table.row wire:key="seller-{{ $seller->id }}">
                        <x-table.cell role="primary">
                            <a href="{{ route('admin.sellers.show', $seller) }}" wire:navigate title="{{ $seller->name }}" class="line-clamp-2 after:absolute after:inset-0 hover:text-accent-700 hover:underline md:line-clamp-none">{{ $seller->name }}</a>
                        </x-table.cell>
                        <x-table.cell role="status"><x-status-badge :status="$seller->status" /></x-table.cell>
                        <x-table.cell role="meta" label="Phone" :mono="true">{{ $seller->phone }}</x-table.cell>
                        <x-table.cell role="meta" :tier="3" class="font-mono text-xs">{{ $seller->created_at->format('d M Y') }}</x-table.cell>
                        <x-table.cell role="actions">
                            <x-menu :title="$seller->name" label="Row actions">
                                <x-menu.item icon="review" :href="route('admin.sellers.show', $seller)" wire:navigate>Review</x-menu.item>
                            </x-menu>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </tbody>
            <x-table.skeleton :target="$target" :rows="6" :cols="4" />
        @endif

        <x-slot:footer>{{ $sellers->links('pagination.admin') }}</x-slot:footer>
    </x-table>
</div>
