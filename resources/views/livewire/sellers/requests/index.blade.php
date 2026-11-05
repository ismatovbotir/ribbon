{{-- Only this seller's items of each request are loaded by the component. --}}
<div>
    <x-page-header :title="__('sellers.nav.requests')" :subtitle="__('sellers.requests.subtitle')" />

    <x-table.status-tabs :aria-label="__('sellers.requests.filter_label')" class="mb-4">
        <x-table.tab wire:click="setFilter('')" :active="$filter === ''" :count="$total">{{ __('sellers.requests.filter_all') }}</x-table.tab>
        <x-table.tab wire:click="setFilter('new')" :active="$filter === 'new'" :count="$newCount">{{ __('sellers.requests.filter_new') }}</x-table.tab>
        @foreach (\App\Models\OfferRequestOutcome::OUTCOMES as $option)
            <x-table.tab wire:key="filter-{{ $option }}" wire:click="setFilter('{{ $option }}')" :active="$filter === $option" :count="$outcomeCounts[$option] ?? 0">{{ __('sellers.requests.outcome.'.$option) }}</x-table.tab>
        @endforeach
    </x-table.status-tabs>

    @if ($requests->isEmpty())
        <div class="border border-border-strong bg-surface">
            @if ($filter !== '')
                <x-empty-state variant="filtered" :title="__('sellers.requests.empty_filtered_title')">
                    <x-slot:actions>
                        <x-button variant="secondary" wire:click="setFilter('')">{{ __('sellers.requests.filter_all') }}</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <x-empty-state :title="__('sellers.requests.empty_title')" :description="__('sellers.requests.empty_body')" />
            @endif
        </div>
    @else
        <ul class="space-y-4">
            @foreach ($requests as $request)
                @php($current = $request->outcomes->first()?->outcome)
                <li wire:key="request-{{ $request->id }}" class="rounded-md border border-border-strong bg-surface-raised">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-surface-subtle px-4 py-3 md:px-5">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs text-text-muted">#{{ $request->id }}</span>
                            <span class="font-mono text-xs text-text-muted">{{ $request->created_at->translatedFormat('d M Y H:i') }}</span>
                        </div>
                        @if ($current)
                            <x-badge variant="{{ ['won' => 'success', 'lost' => 'muted'][$current] ?? 'info' }}" :dot="true">{{ __('sellers.requests.outcome.'.$current) }}</x-badge>
                        @else
                            <x-badge variant="warning" :dot="true">{{ __('sellers.requests.filter_new') }}</x-badge>
                        @endif
                    </div>

                    <div class="grid gap-4 p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)] md:p-5">
                        <dl class="space-y-2 text-sm">
                            <div>
                                <dt class="text-xs text-text-muted">{{ __('sellers.requests.phone') }}</dt>
                                <dd><a href="tel:{{ preg_replace('/[^\d+]/', '', $request->phone) }}" class="inline-flex min-h-11 items-center font-mono text-accent-700 hover:underline">{{ $request->phone }}</a></dd>
                            </div>
                            @if ($request->company_name)
                                <div>
                                    <dt class="text-xs text-text-muted">{{ __('sellers.requests.company') }}</dt>
                                    <dd class="break-words text-text-primary">{{ $request->company_name }}</dd>
                                </div>
                            @endif
                            @if ($request->email)
                                <div>
                                    <dt class="text-xs text-text-muted">{{ __('sellers.requests.email') }}</dt>
                                    <dd class="break-words"><a href="mailto:{{ $request->email }}" class="text-accent-700 hover:underline">{{ $request->email }}</a></dd>
                                </div>
                            @endif
                            @if ($request->buyer_type)
                                <div>
                                    <dt class="text-xs text-text-muted">{{ __('sellers.requests.buyer_type') }}</dt>
                                    <dd class="text-text-primary">{{ __('storefront.offer_request.buyer_type.'.$request->buyer_type) }}</dd>
                                </div>
                            @endif
                        </dl>

                        <div>
                            <ul class="divide-y divide-border border-y border-border text-sm">
                                @foreach ($request->items as $item)
                                    <li class="flex items-start justify-between gap-3 py-2">
                                        <span class="min-w-0 break-words text-text-primary">{{ $item->product?->localizedName(app()->getLocale()) ?: '—' }}</span>
                                        <span class="shrink-0 text-right font-mono text-xs text-text-secondary tabular-nums">
                                            {{ $item->quantity }} {{ __('sellers.products.unit.'.$item->unit) }}<br>
                                            {{ number_format($item->lineTotal(), 0, '.', ' ') }} UZS
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div role="group" aria-label="{{ __('sellers.requests.outcome_label') }}" class="grid grid-cols-2 gap-2 border-t border-border p-4 md:flex md:items-center md:px-5">
                        <span class="col-span-2 text-xs font-medium text-text-secondary md:mr-2">{{ __('sellers.requests.outcome_label') }}</span>
                        @foreach (\App\Models\OfferRequestOutcome::OUTCOMES as $option)
                            <x-button
                                wire:key="request-{{ $request->id }}-{{ $option }}"
                                :variant="$current === $option ? 'primary' : 'secondary'"
                                aria-pressed="{{ $current === $option ? 'true' : 'false' }}"
                                class="min-h-11"
                                wire:click="setOutcome({{ $request->id }}, '{{ $option }}')"
                                wire:loading.attr="disabled"
                                wire:target="setOutcome"
                            >{{ __('sellers.requests.outcome.'.$option) }}</x-button>
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($requests->hasPages())
            <div class="mt-4">{{ $requests->links() }}</div>
        @endif
    @endif
</div>
