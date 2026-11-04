<div class="max-w-4xl">
    <x-page-header title="Commercial offer #{{ $offerRequest->id }}" subtitle="Multi-seller price-quote request submitted by a buyer." :back-url="route('admin.offers.index')" back-label="All requests">
        <x-slot:meta>
            <x-status-badge type="offer" :status="$offerRequest->status" />
            <span class="font-mono text-xs text-text-muted">#{{ $offerRequest->id }}</span>
            <span class="font-mono text-xs text-text-muted">{{ $offerRequest->created_at->format('d M Y H:i') }}</span>
        </x-slot:meta>
    </x-page-header>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <div class="space-y-6">
        <x-section title="Summary">
            <dl>
                <x-fact label="Status"><x-status-badge type="offer" :status="$offerRequest->status" /></x-fact>
                <x-fact label="Submitted" :mono="true">{{ $offerRequest->created_at->format('d M Y H:i') }}</x-fact>
                <x-fact label="Items">{{ $items->count() }} across {{ $itemsBySeller->count() }} {{ \Illuminate\Support\Str::plural('seller', $itemsBySeller->count()) }}</x-fact>
                <x-fact label="Grand total" :mono="true">{{ number_format($grandTotal, 2) }}</x-fact>
            </dl>
        </x-section>

        <x-section title="Buyer contact">
            <dl>
                <x-fact label="Phone" :mono="true"><a href="tel:{{ $offerRequest->phone }}" class="text-accent-700 hover:underline">{{ $offerRequest->phone }}</a></x-fact>
                <x-fact label="Company">{{ $offerRequest->company_name ?: '—' }}</x-fact>
                <x-fact label="Email">{{ $offerRequest->email ?: '—' }}</x-fact>
            </dl>
        </x-section>

        {{-- Line items grouped by seller; each group has its own subtotal. --}}
        <x-section title="Requested items" :flush="true">
            @forelse ($itemsBySeller as $sellerName => $sellerItems)
                <div class="border-b border-border last:border-b-0">
                    <div class="flex items-center justify-between gap-3 bg-surface-subtle px-4 py-2.5 md:px-5">
                        <h3 class="min-w-0 truncate text-sm font-semibold text-text-primary">{{ $sellerName }}</h3>
                        <span class="shrink-0 text-xs text-text-secondary">{{ $sellerItems->count() }} {{ \Illuminate\Support\Str::plural('item', $sellerItems->count()) }}</span>
                    </div>
                    <ul class="divide-y divide-border">
                        @foreach ($sellerItems as $item)
                            <li class="grid grid-cols-2 gap-x-4 gap-y-1 px-4 py-3 text-sm md:grid-cols-[minmax(0,1fr)_5rem_6rem_7rem_8rem] md:items-center md:px-5">
                                <span class="col-span-2 font-medium text-text-primary md:col-span-1">{{ $item->product?->name ?: '(deleted product)' }}</span>
                                <span class="text-xs text-text-secondary md:text-sm"><span class="md:hidden">Unit: </span>{{ $item->unit }}</span>
                                <span class="font-mono text-xs text-text-secondary tabular-nums md:text-right md:text-sm"><span class="font-sans md:hidden">Qty: </span>{{ $item->quantity }}</span>
                                <span class="font-mono text-xs text-text-secondary tabular-nums md:text-right md:text-sm"><span class="font-sans md:hidden">Price: </span>{{ number_format((float) $item->price_at_request, 2) }}</span>
                                <span class="font-mono text-xs font-medium text-text-primary tabular-nums md:text-right md:text-sm"><span class="font-sans font-normal md:hidden">Total: </span>{{ number_format($item->lineTotal(), 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="flex items-center justify-end gap-4 border-t border-border px-4 py-2 text-sm md:px-5">
                        <span class="text-xs text-text-muted">Seller subtotal</span>
                        <span class="font-mono font-semibold tabular-nums">{{ number_format($sellerItems->sum(fn ($item) => $item->lineTotal()), 2) }}</span>
                    </p>
                </div>
            @empty
                <p class="p-4 text-sm text-text-secondary">No line items on this request.</p>
            @endforelse

            @if ($itemsBySeller->isNotEmpty())
                <p class="flex items-center justify-end gap-4 border-t border-border-strong bg-surface-subtle px-4 py-3 md:px-5">
                    <span class="text-xs text-text-muted">Grand total</span>
                    <span class="font-mono text-base font-semibold tabular-nums">{{ number_format($grandTotal, 2) }}</span>
                </p>
            @endif
        </x-section>
    </div>

    {{-- State machine: pending -> contacted -> fulfilled; cancel from pending or contacted only. --}}
    @if (in_array($offerRequest->status, ['pending', 'contacted']))
        <x-action-bar>
            <x-button variant="primary" wire:click="markFulfilled" wire:loading.attr="disabled" wire:target="markFulfilled">Mark fulfilled</x-button>
            <x-menu title="More" label="More">
                <x-slot:trigger><x-button variant="secondary" aria-haspopup="menu">More</x-button></x-slot:trigger>
                @if ($offerRequest->status === 'pending')
                    <x-menu.item icon="check" wire:click="markContacted">Mark contacted</x-menu.item>
                @endif
                <x-menu.item icon="reject" wire:click="cancel" :destructive="true">Cancel request</x-menu.item>
            </x-menu>
        </x-action-bar>
    @endif
</div>
