<div class="max-w-4xl">
    <x-page-header :title="__('admin.ui.commercial_offer_n', ['id' => $offerRequest->id])" :subtitle="__('admin.ui.multi_seller_price_quote_request_submitted_by_a_buyer')" :back-url="route('admin.offers.index')" :back-label="__('admin.ui.all_requests')">
        <x-slot:meta>
            <x-status-badge type="offer" :status="$offerRequest->status" />
            <span class="font-mono text-xs text-text-muted">#{{ $offerRequest->id }}</span>
            <span class="font-mono text-xs text-text-muted">{{ $offerRequest->created_at->translatedFormat('d M Y H:i') }}</span>
        </x-slot:meta>
    </x-page-header>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <div class="space-y-6">
        <x-section :title="__('admin.ui.summary')">
            <dl>
                <x-fact :label="__('admin.ui.status')"><x-status-badge type="offer" :status="$offerRequest->status" /></x-fact>
                <x-fact :label="__('admin.ui.submitted')" :mono="true">{{ $offerRequest->created_at->translatedFormat('d M Y H:i') }}</x-fact>
                <x-fact :label="__('admin.ui.items')">{{ __('admin.ui.items_across', ['items' => trans_choice('admin.ui.items_count', $items->count()), 'sellers' => trans_choice('admin.ui.sellers_count', $itemsBySeller->count())]) }}</x-fact>
                <x-fact :label="__('admin.ui.grand_total')" :mono="true">{{ number_format($grandTotal, 2) }}</x-fact>
            </dl>
        </x-section>

        <x-section :title="__('admin.ui.buyer_contact')">
            <dl>
                <x-fact :label="__('admin.ui.phone')" :mono="true"><a href="tel:{{ $offerRequest->phone }}" class="text-accent-700 hover:underline">{{ $offerRequest->phone }}</a></x-fact>
                <x-fact :label="__('admin.ui.company')">{{ $offerRequest->company_name ?: '—' }}</x-fact>
                <x-fact :label="__('admin.ui.email')">{{ $offerRequest->email ?: '—' }}</x-fact>
                <x-fact :label="__('admin.offers.buyer_type')">{{ $offerRequest->buyer_type ? __('storefront.offer_request.buyer_type.'.$offerRequest->buyer_type) : '—' }}</x-fact>
                @php($utmParts = collect($offerRequest->utm ?? [])->only(['utm_source', 'utm_medium', 'utm_campaign'])->filter()->implode(' / '))
                <x-fact :label="__('admin.offers.source')"><span class="break-words">{{ $utmParts !== '' ? $utmParts : __('admin.offers.source_direct') }}</span></x-fact>
                <x-fact :label="__('admin.offers.referrer')"><span class="break-words">{{ $offerRequest->referrer ?: '—' }}</span></x-fact>
            </dl>
        </x-section>

        {{-- Line items grouped by seller; each group has its own subtotal. --}}
        <x-section :title="__('admin.ui.requested_items')" :flush="true">
            @forelse ($itemsBySeller as $sellerName => $sellerItems)
                <div class="border-b border-border last:border-b-0">
                    <div class="flex items-center justify-between gap-3 bg-surface-subtle px-4 py-2.5 md:px-5">
                        <h3 class="min-w-0 truncate text-sm font-semibold text-text-primary">{{ $sellerName }}</h3>
                        <span class="shrink-0 text-xs text-text-secondary">{{ trans_choice('admin.ui.items_count', $sellerItems->count()) }}</span>
                    </div>
                    <ul class="divide-y divide-border">
                        @foreach ($sellerItems as $item)
                            <li class="grid grid-cols-2 gap-x-4 gap-y-1 px-4 py-3 text-sm md:grid-cols-[minmax(0,1fr)_5rem_6rem_7rem_8rem] md:items-center md:px-5">
                                <span class="col-span-2 font-medium text-text-primary md:col-span-1">{{ $item->product?->name ?: __('admin.ui.deleted_product') }}</span>
                                <span class="text-xs text-text-secondary md:text-sm"><span class="md:hidden">{{ __('admin.ui.unit_2') }} </span>{{ $item->unit }}</span>
                                <span class="font-mono text-xs text-text-secondary tabular-nums md:text-right md:text-sm"><span class="font-sans md:hidden">{{ __('admin.ui.qty') }} </span>{{ $item->quantity }}</span>
                                <span class="font-mono text-xs text-text-secondary tabular-nums md:text-right md:text-sm"><span class="font-sans md:hidden">{{ __('admin.ui.price') }} </span>{{ number_format((float) $item->price_at_request, 2) }}</span>
                                <span class="font-mono text-xs font-medium text-text-primary tabular-nums md:text-right md:text-sm"><span class="font-sans font-normal md:hidden">{{ __('admin.ui.total') }} </span>{{ number_format($item->lineTotal(), 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="flex items-center justify-end gap-4 border-t border-border px-4 py-2 text-sm md:px-5">
                        <span class="text-xs text-text-muted">{{ __('admin.ui.seller_subtotal') }}</span>
                        <span class="font-mono font-semibold tabular-nums">{{ number_format($sellerItems->sum(fn ($item) => $item->lineTotal()), 2) }}</span>
                    </p>
                </div>
            @empty
                <p class="p-4 text-sm text-text-secondary">{{ __('admin.ui.no_line_items_on_this_request') }}</p>
            @endforelse

            @if ($itemsBySeller->isNotEmpty())
                <p class="flex items-center justify-end gap-4 border-t border-border-strong bg-surface-subtle px-4 py-3 md:px-5">
                    <span class="text-xs text-text-muted">{{ __('admin.ui.grand_total') }}</span>
                    <span class="font-mono text-base font-semibold tabular-nums">{{ number_format($grandTotal, 2) }}</span>
                </p>
            @endif
        </x-section>
    </div>

    {{-- State machine: pending -> contacted -> fulfilled; cancel from pending or contacted only. --}}
    @if (in_array($offerRequest->status, ['pending', 'contacted']))
        <x-action-bar>
            <x-button variant="primary" wire:click="markFulfilled" wire:loading.attr="disabled" wire:target="markFulfilled">{{ __('admin.ui.mark_fulfilled') }}</x-button>
            <x-menu :title="__('admin.ui.more')" :label="__('admin.ui.more')">
                <x-slot:trigger><x-button variant="secondary" aria-haspopup="menu">{{ __('admin.ui.more') }}</x-button></x-slot:trigger>
                @if ($offerRequest->status === 'pending')
                    <x-menu.item icon="check" wire:click="markContacted">{{ __('admin.ui.mark_contacted') }}</x-menu.item>
                @endif
                <x-menu.item icon="reject" wire:click="cancel" :destructive="true">{{ __('admin.ui.cancel_request') }}</x-menu.item>
            </x-menu>
        </x-action-bar>
    @endif
</div>
