<div class="max-w-4xl">
    <x-page-header title="Commercial Offer #{{ $offerRequest->id }}" subtitle="Multi-seller price-quote request submitted by a buyer.">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.offers.index') }}" wire:navigate variant="ghost">
                ← All requests
            </x-button>
            {{-- State machine: pending -> contacted -> fulfilled, with
                 cancel available from pending or contacted only. See
                 Show.php's class docblock for the full rationale. --}}
            @if ($offerRequest->status === 'pending')
                <x-button variant="secondary" wire:click="markContacted" wire:loading.attr="disabled" wire:target="markContacted">
                    Mark contacted
                </x-button>
            @endif
            @if (in_array($offerRequest->status, ['pending', 'contacted']))
                <x-button variant="primary" wire:click="markFulfilled" wire:loading.attr="disabled" wire:target="markFulfilled">
                    Mark fulfilled
                </x-button>
                <x-button variant="danger" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel">
                    Cancel
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    {{-- Status summary --}}
    <div class="mb-6 flex flex-wrap gap-x-8 gap-y-4 rounded-md bg-surface-subtle p-5">
        <div>
            <p class="text-xs font-medium tracking-wide text-text-muted uppercase">Status</p>
            <div class="mt-1.5">
                @php
                    $variant = match ($offerRequest->status) {
                        'fulfilled' => 'success',
                        'pending' => 'warning',
                        'contacted' => 'info',
                        'cancelled' => 'muted',
                        default => 'muted',
                    };
                    $label = $offerRequest->status === 'pending' ? 'New' : ucfirst($offerRequest->status);
                @endphp
                <x-badge :variant="$variant" dot>{{ $label }}</x-badge>
            </div>
        </div>

        <div>
            <p class="text-xs font-medium tracking-wide text-text-muted uppercase">Submitted</p>
            <p class="mt-1.5 text-sm text-text-primary">{{ $offerRequest->created_at->format('M j, Y \a\t H:i') }}</p>
        </div>

        <div>
            <p class="text-xs font-medium tracking-wide text-text-muted uppercase">Items</p>
            <p class="mt-1.5 text-sm text-text-primary">{{ $items->count() }} across {{ $itemsBySeller->count() }} {{ \Illuminate\Support\Str::plural('seller', $itemsBySeller->count()) }}</p>
        </div>

        <div>
            <p class="text-xs font-medium tracking-wide text-text-muted uppercase">Grand total</p>
            <p class="mt-1.5 font-mono text-sm font-semibold text-text-primary tabular-nums">{{ number_format($grandTotal, 2) }}</p>
        </div>
    </div>

    {{-- Buyer contact --}}
    <div class="mb-6 rounded-md border border-border-strong bg-surface-raised">
        <div class="border-b border-border px-5 py-4">
            <h2 class="text-lg font-semibold text-text-primary">Buyer contact</h2>
        </div>
        <dl class="divide-y divide-border px-5">
            <div class="flex items-start justify-between gap-4 py-3">
                <dt class="shrink-0 text-sm text-text-secondary">Phone</dt>
                <dd class="text-right font-mono text-sm font-medium text-text-primary">{{ $offerRequest->phone }}</dd>
            </div>
            <div class="flex items-start justify-between gap-4 py-3">
                <dt class="shrink-0 text-sm text-text-secondary">Company</dt>
                <dd class="text-right text-sm font-medium text-text-primary">{{ $offerRequest->company_name ?: '—' }}</dd>
            </div>
            <div class="flex items-start justify-between gap-4 py-3">
                <dt class="shrink-0 text-sm text-text-secondary">Email</dt>
                <dd class="text-right text-sm font-medium text-text-primary">{{ $offerRequest->email ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Line items, grouped by seller — a request can span multiple
         sellers, so each gets its own subheading + subtotal. --}}
    <div class="mb-6 rounded-md border border-border-strong bg-surface-raised">
        <div class="border-b border-border px-5 py-4">
            <h2 class="text-lg font-semibold text-text-primary">Requested items</h2>
        </div>

        @forelse ($itemsBySeller as $sellerName => $sellerItems)
            <div class="border-b border-border last:border-b-0">
                <div class="flex items-center justify-between bg-surface-subtle px-5 py-2.5">
                    <h3 class="text-sm font-semibold text-text-primary">{{ $sellerName }}</h3>
                    <span class="text-xs text-text-secondary">{{ $sellerItems->count() }} {{ \Illuminate\Support\Str::plural('item', $sellerItems->count()) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] border-collapse">
                        <thead>
                            <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                                <th class="px-5 py-2">Product</th>
                                <th class="px-4 py-2">Unit</th>
                                <th class="px-4 py-2 text-right">Quantity</th>
                                <th class="px-4 py-2 text-right">Price</th>
                                <th class="px-5 py-2 text-right">Line total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($sellerItems as $item)
                                <tr class="h-row-comfortable text-sm text-text-primary">
                                    <td class="max-w-xs truncate px-5 py-2 font-medium" title="{{ $item->product?->name }}">
                                        {{ $item->product?->name ?: '(deleted product)' }}
                                    </td>
                                    <td class="px-4 py-2 text-text-secondary">{{ $item->unit }}</td>
                                    <td class="px-4 py-2 text-right font-mono text-xs text-text-secondary tabular-nums">{{ $item->quantity }}</td>
                                    <td class="px-4 py-2 text-right font-mono text-xs text-text-secondary tabular-nums">{{ number_format((float) $item->price_at_request, 2) }}</td>
                                    <td class="px-5 py-2 text-right font-mono text-xs font-medium text-text-primary tabular-nums">{{ number_format($item->lineTotal(), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-border/60 text-sm">
                                <td colspan="4" class="px-5 py-2 text-right text-xs font-medium tracking-wide text-text-muted uppercase">Seller subtotal</td>
                                <td class="px-5 py-2 text-right font-mono text-sm font-semibold text-text-primary tabular-nums">{{ number_format($sellerItems->sum(fn ($item) => $item->lineTotal()), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @empty
            <p class="px-5 py-6 text-sm text-text-secondary">No line items on this request.</p>
        @endforelse

        @if ($itemsBySeller->isNotEmpty())
            <div class="flex items-center justify-end gap-4 border-t border-border-strong bg-surface-subtle px-5 py-3">
                <span class="text-xs font-medium tracking-wide text-text-muted uppercase">Grand total</span>
                <span class="font-mono text-base font-semibold text-text-primary tabular-nums">{{ number_format($grandTotal, 2) }}</span>
            </div>
        @endif
    </div>
</div>
