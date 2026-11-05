@php
    $activeCount = ($search !== '' ? 1 : 0) + ($status !== '' ? 1 : 0);
    $target = 'search, filterByStatus, status';
@endphp

<div>
    <x-page-header :title="__('admin.ui.commercial_offers')" :subtitle="__('admin.ui.multi_seller_price_quote_requests_submitted_by_buyers_throug')" />

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <x-table>
        <x-slot:toolbar>
            <x-table.status-tabs :aria-label="__('admin.ui.filter_by_status')">
                <x-table.tab wire:click="filterByStatus('')" :active="$status === ''" :count="$statusCounts->sum()">{{ __('admin.ui.all') }}</x-table.tab>
                @foreach ($statuses as $option)
                    <x-table.tab wire:key="tab-{{ $option }}" wire:click="filterByStatus('{{ $option }}')" :active="$status === $option" :count="$statusCounts[$option] ?? 0">{{ __('admin.ui.status_'.($option === 'pending' ? 'new' : $option)) }}</x-table.tab>
                @endforeach
            </x-table.status-tabs>

            <x-table.toolbar :active-count="$activeCount" :result-count="$requests->total()">
                <x-slot:search>
                    <x-input type="search" enterkeyhint="search" wire:model.live.debounce.400ms="search" :placeholder="__('admin.ui.search_by_phone_or_company')" :aria-label="__('admin.ui.search_offers')" />
                </x-slot:search>
                <x-slot:filters>
                    <x-select wire:model.live="status" :aria-label="__('admin.ui.status')" class="md:hidden">
                        <option value="">{{ __('admin.ui.all_statuses') }}</option>
                        @foreach ($statuses as $option)
                            <option value="{{ $option }}">{{ __('admin.ui.status_'.($option === 'pending' ? 'new' : $option)) }}</option>
                        @endforeach
                    </x-select>
                </x-slot:filters>
                <x-slot:chips>
                    @if ($status !== '')
                        <x-table.chip wire:click="filterByStatus('{{ $status }}')">{{ __('admin.ui.status_chip', ['value' => __('admin.ui.status_'.($status === 'pending' ? 'new' : $status))]) }}</x-table.chip>
                    @endif
                    @if ($search !== '')
                        <x-table.chip wire:click="$set('search', '')">{{ __('admin.ui.search_chip', ['value' => $search]) }}</x-table.chip>
                    @endif
                </x-slot:chips>
            </x-table.toolbar>
        </x-slot:toolbar>

        <x-slot:head>
            <x-table.th>{{ __('admin.ui.phone') }}</x-table.th>
            <x-table.th>{{ __('admin.ui.status') }}</x-table.th>
            <x-table.th align="right">{{ __('admin.ui.items') }}</x-table.th>
            <x-table.th>{{ __('admin.ui.company') }}</x-table.th>
            <x-table.th :tier="3">{{ __('admin.ui.received') }}</x-table.th>
            <x-table.th><span class="sr-only">{{ __('admin.ui.actions') }}</span></x-table.th>
        </x-slot:head>

        @if ($requests->isEmpty())
            <tbody class="block md:table-row-group">
                <tr class="block md:table-row"><td colspan="6" class="block md:table-cell">
                    @if ($activeCount > 0)
                        <x-empty-state variant="filtered" :title="__('admin.ui.no_results_match_your_filters')" description="Try a different search term or status.">
                            <x-slot:actions><x-button variant="secondary" wire:click="clearFilters">{{ __('admin.ui.clear_filters') }}</x-button></x-slot:actions>
                        </x-empty-state>
                    @else
                        <x-empty-state :title="__('admin.ui.no_commercial_offer_requests_yet')" description="Requests submitted via the public storefront will appear here." />
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
                        <x-table.cell role="meta" :label="__('admin.ui.items')" :mono="true" align="right">{{ $request->items_count }}</x-table.cell>
                        <x-table.cell role="meta" :label="__('admin.ui.company')">{{ $request->company_name ?: '—' }}</x-table.cell>
                        <x-table.cell role="meta" :tier="3" class="font-mono text-xs">{{ $request->created_at->translatedFormat('d M Y') }}</x-table.cell>
                        <x-table.cell role="actions">
                            <x-menu :title="$request->phone" :label="__('admin.ui.row_actions')">
                                <x-menu.item icon="view" :href="route('admin.offers.show', $request)" wire:navigate>{{ __('admin.ui.view') }}</x-menu.item>
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
