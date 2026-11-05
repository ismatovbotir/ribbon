@php
    $activeCount = ($search !== '' ? 1 : 0) + ($status !== '' ? 1 : 0);
    $sortLabels = ['name' => __('admin.ui.product'), 'status' => __('admin.ui.status'), 'created_at' => __('admin.ui.submitted')];
@endphp

<div>
    <x-page-header :title="__('admin.ui.products')" :subtitle="__('admin.ui.review_and_moderate_product_listings_submitted_by_sellers_ac')" />

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <x-table :loading="false">
        <x-slot:toolbar>
            <x-table.status-tabs :aria-label="__('admin.ui.filter_by_status')">
                <x-table.tab wire:click="filterByStatus('')" :active="$status === ''" :count="$statusCounts->sum()">{{ __('admin.ui.all') }}</x-table.tab>
                @foreach ($statuses as $option)
                    <x-table.tab wire:key="tab-{{ $option }}" wire:click="filterByStatus('{{ $option }}')" :active="$status === $option" :count="$statusCounts[$option] ?? 0">{{ __('admin.ui.status_'.$option) }}</x-table.tab>
                @endforeach
            </x-table.status-tabs>

            <x-table.toolbar :active-count="$activeCount" :result-count="$products->total()">
                <x-slot:search>
                    <x-input type="search" enterkeyhint="search" wire:model.live.debounce.400ms="search" :placeholder="__('admin.ui.search_by_name')" :aria-label="__('admin.ui.search_products')" />
                </x-slot:search>
                <x-slot:filters>
                    <x-select wire:model.live="status" :aria-label="__('admin.ui.status')" class="md:hidden">
                        <option value="">{{ __('admin.ui.all_statuses') }}</option>
                        @foreach ($statuses as $option)
                            <option value="{{ $option }}">{{ __('admin.ui.status_'.$option) }}</option>
                        @endforeach
                    </x-select>
                    <x-select wire:model.live="sortField" :aria-label="__('admin.ui.sort_by')" class="md:hidden">
                        @foreach ($sortLabels as $field => $label)
                            <option value="{{ $field }}">{{ __('admin.ui.sort_by_label', ['label' => $label]) }}</option>
                        @endforeach
                    </x-select>
                    <x-select wire:model.live="sortDirection" :aria-label="__('admin.ui.sort_direction')" class="md:hidden">
                        <option value="asc">{{ __('admin.ui.ascending') }}</option>
                        <option value="desc">{{ __('admin.ui.descending') }}</option>
                    </x-select>
                </x-slot:filters>
                <x-slot:chips>
                    @if ($status !== '')
                        <x-table.chip wire:click="filterByStatus('{{ $status }}')">{{ __('admin.ui.status_chip', ['value' => __('admin.ui.status_'.$status)]) }}</x-table.chip>
                    @endif
                    @if ($search !== '')
                        <x-table.chip wire:click="$set('search', '')">{{ __('admin.ui.search_chip', ['value' => $search]) }}</x-table.chip>
                    @endif
                </x-slot:chips>
            </x-table.toolbar>
        </x-slot:toolbar>

        <x-slot:head>
            <x-table.th :sortable="true" :sort="$sortField === 'name' ? $sortDirection : null" wire:click="sortBy('name')">{{ __('admin.ui.product') }}</x-table.th>
            <x-table.th :sortable="true" :sort="$sortField === 'status' ? $sortDirection : null" wire:click="sortBy('status')">{{ __('admin.ui.status') }}</x-table.th>
            <x-table.th>{{ __('admin.ui.seller') }}</x-table.th>
            <x-table.th>{{ __('admin.ui.category') }}</x-table.th>
            <x-table.th :tier="3">{{ __('admin.ui.brand') }}</x-table.th>
            <x-table.th :tier="3" :sortable="true" :sort="$sortField === 'created_at' ? $sortDirection : null" wire:click="sortBy('created_at')">{{ __('admin.ui.submitted') }}</x-table.th>
            <x-table.th><span class="sr-only">{{ __('admin.ui.actions') }}</span></x-table.th>
        </x-slot:head>

        @if ($products->isEmpty())
            <tbody class="block md:table-row-group">
                <tr class="block md:table-row"><td colspan="7" class="block md:table-cell">
                    @if ($activeCount > 0)
                        <x-empty-state variant="filtered" :title="__('admin.ui.no_results_match_your_filters')" :description="__('admin.ui.try_a_different_search_term_or_status')">
                            <x-slot:actions><x-button variant="secondary" wire:click="clearFilters">{{ __('admin.ui.clear_filters') }}</x-button></x-slot:actions>
                        </x-empty-state>
                    @else
                        <x-empty-state :title="__('admin.ui.no_products_yet')" :description="__('admin.ui.empty_products')" />
                    @endif
                </td></tr>
            </tbody>
        @else
            <tbody role="rowgroup" wire:loading.remove wire:target="search, sortBy, filterByStatus, status, sortField, sortDirection" class="block divide-y divide-border md:table-row-group">
                @foreach ($products as $product)
                    <x-table.row wire:key="product-{{ $product->id }}">
                        <x-table.cell role="primary">
                            <a href="{{ route('admin.products.show', $product) }}" wire:navigate title="{{ $product->name }}" class="line-clamp-2 after:absolute after:inset-0 hover:text-accent-700 hover:underline md:line-clamp-none">{{ $product->name ?: '(untitled)' }}</a>
                        </x-table.cell>
                        <x-table.cell role="status"><x-status-badge :status="$product->status" /></x-table.cell>
                        <x-table.cell role="meta" :label="__('admin.ui.seller')">
                            @if ($product->seller)
                                <a href="{{ route('admin.sellers.show', $product->seller) }}" wire:navigate class="relative z-10 hover:text-accent-700 hover:underline">{{ $product->seller->name }}</a>
                            @else
                                —
                            @endif
                        </x-table.cell>
                        <x-table.cell role="meta" :label="__('admin.ui.category')">{{ $product->category?->name[app()->getLocale()] ?? '—' }}</x-table.cell>
                        <x-table.cell role="meta" :tier="3">{{ $product->brand?->name ?? '—' }}</x-table.cell>
                        <x-table.cell role="meta" :tier="3" class="font-mono text-xs">{{ $product->created_at->translatedFormat('d M Y') }}</x-table.cell>
                        <x-table.cell role="actions">
                            <x-menu :title="$product->name ?: '(untitled)'" :label="__('admin.ui.row_actions')">
                                <x-menu.item icon="review" :href="route('admin.products.show', $product)" wire:navigate>{{ __('admin.ui.review') }}</x-menu.item>
                            </x-menu>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </tbody>
            <x-table.skeleton target="search, sortBy, filterByStatus, status, sortField, sortDirection" :rows="6" :cols="6" />
        @endif

        <x-slot:footer>{{ $products->links('pagination.admin') }}</x-slot:footer>
    </x-table>
</div>
