<div class="max-w-4xl">
    <x-page-header :title="$product->name ?: 'Product'" subtitle="Product listing review.">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.products.index') }}" wire:navigate variant="ghost">
                ← All products
            </x-button>
            @if ($product->status !== 'approved')
                <x-button variant="primary" wire:click="approve" wire:loading.attr="disabled" wire:target="approve">
                    Approve
                </x-button>
            @endif
            {{-- Reject is an initial-review decision (pending -> rejected)
                 only. An already-approved, live product gets "Block"
                 instead — see Product::suspend()'s docblock. --}}
            @if ($product->status === 'pending')
                <x-button variant="danger-solid" wire:click="openRejectForm">
                    Reject
                </x-button>
            @elseif ($product->status === 'approved')
                <x-button variant="danger-solid" wire:click="openSuspendForm">
                    Block
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    {{-- Status summary — understated, mirrors the Seller review page treatment --}}
    <div class="mb-6 flex flex-wrap gap-x-8 gap-y-4 rounded-md bg-surface-subtle p-5">
        <div>
            <p class="text-xs font-medium tracking-wide text-text-muted uppercase">Status</p>
            <div class="mt-1.5">
                @php
                    $variant = match ($product->status) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'suspended' => 'muted',
                        default => 'muted',
                    };
                @endphp
                <x-badge :variant="$variant" dot>{{ ucfirst($product->status) }}</x-badge>
            </div>
        </div>

        <div>
            <p class="text-xs font-medium tracking-wide text-text-muted uppercase">Submitted</p>
            <p class="mt-1.5 text-sm text-text-primary">{{ $product->created_at->format('M j, Y') }}</p>
        </div>

        @if ($product->moderated_at)
            <div>
                <p class="text-xs font-medium tracking-wide text-text-muted uppercase">
                    @if ($product->status === 'rejected')
                        Rejected
                    @elseif ($product->status === 'suspended')
                        Suspended
                    @else
                        Last reviewed
                    @endif
                </p>
                <p class="mt-1.5 text-sm text-text-primary">
                    {{ $product->moderated_at->format('M j, Y') }}
                    @if ($product->moderatedBy)
                        by {{ $product->moderatedBy->name }}
                    @endif
                </p>
            </div>
        @endif

        @if (in_array($product->status, ['rejected', 'suspended']) && $product->rejection_reason)
            <div class="w-full basis-full border-t border-border/60 pt-3">
                <p class="text-xs font-medium tracking-wide text-text-muted uppercase">
                    {{ $product->status === 'suspended' ? 'Suspension reason' : 'Rejection reason' }}
                </p>
                <p class="mt-1.5 text-sm text-text-primary">{{ $product->rejection_reason }}</p>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Listing details --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border px-5 py-4">
                <h2 class="text-lg font-semibold text-text-primary">Listing details</h2>
            </div>
            <dl class="divide-y divide-border px-5">
                <div class="flex items-start justify-between gap-4 py-3">
                    <dt class="shrink-0 text-sm text-text-secondary">Name</dt>
                    <dd class="max-w-xs text-right text-sm font-medium text-text-primary">{{ $product->name ?: '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4 py-3">
                    <dt class="shrink-0 text-sm text-text-secondary">Category</dt>
                    <dd class="text-right text-sm font-medium text-text-primary">{{ $product->category?->name[$defaultLocale] ?? '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4 py-3">
                    <dt class="shrink-0 text-sm text-text-secondary">Brand</dt>
                    <dd class="text-right text-sm font-medium text-text-primary">{{ $product->brand?->name ?? '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4 py-3">
                    <dt class="shrink-0 text-sm text-text-secondary">Seller</dt>
                    <dd class="text-right text-sm font-medium text-text-primary">
                        @if ($product->seller)
                            <a href="{{ route('admin.sellers.show', $product->seller) }}" wire:navigate class="text-accent-700 hover:underline">
                                {{ $product->seller->name }}
                            </a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex items-start justify-between gap-4 py-3">
                    <dt class="shrink-0 text-sm text-text-secondary">Vitrin price</dt>
                    <dd class="text-right text-sm font-medium text-text-primary">
                        @if ($this->vitrinPrice)
                            {{ number_format((float) $this->vitrinPrice->price) }} UZS / {{ __('sellers.products.unit.'.$this->vitrinPrice->unit) }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Category parameters --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border px-5 py-4">
                <h2 class="text-lg font-semibold text-text-primary">Specifications</h2>
            </div>
            @if (count($this->parameterRows) > 0)
                <dl class="divide-y divide-border px-5">
                    @foreach ($this->parameterRows as $row)
                        <div class="flex items-start justify-between gap-4 py-3">
                            <dt class="shrink-0 text-sm text-text-secondary">{{ $row['label'] }}</dt>
                            <dd class="max-w-xs text-right text-sm font-medium text-text-primary">{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="px-5 py-6 text-sm text-text-secondary">No category parameter values filled in for this product.</p>
            @endif
        </div>
    </div>

    {{-- Reject confirmation modal --}}
    @if ($showRejectForm)
        <div class="fixed inset-0 z-modal-backdrop bg-slate-900/40" wire:click="cancelReject"></div>
        <div class="fixed inset-0 z-modal flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface-overlay p-5 shadow-lg">
                <h2 class="text-lg font-semibold text-text-primary">Reject "{{ $product->name }}"?</h2>
                <p class="mt-2 text-sm text-text-secondary">
                    Provide a reason — it's recorded on the listing and can be shared with the seller.
                </p>

                <form wire:submit="reject" class="mt-4">
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        Reason <span class="text-danger-600">*</span>
                    </label>
                    <textarea
                        wire:model.blur="rejectReason"
                        rows="3"
                        autofocus
                        class="block w-full rounded-sm border bg-surface px-3 py-2 text-base text-text-primary placeholder:text-text-muted focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none {{ $errors->has('rejectReason') ? 'border-danger-600' : 'border-border' }}"
                        placeholder="e.g. Photos don't match declared specifications"
                    ></textarea>
                    @error('rejectReason')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-5 flex items-center justify-end gap-2">
                        <x-button type="button" variant="ghost" wire:click="cancelReject">Cancel</x-button>
                        <x-button type="submit" variant="danger-solid" wire:loading.attr="disabled" wire:target="reject">
                            Reject listing
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Block confirmation modal --}}
    @if ($showSuspendForm)
        <div class="fixed inset-0 z-modal-backdrop bg-slate-900/40" wire:click="cancelSuspend"></div>
        <div class="fixed inset-0 z-modal flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface-overlay p-5 shadow-lg">
                <h2 class="text-lg font-semibold text-text-primary">Block "{{ $product->name }}"?</h2>
                <p class="mt-2 text-sm text-text-secondary">
                    This listing is currently live — blocking removes it from the storefront. Provide a reason — it's recorded on the listing.
                </p>

                <form wire:submit="suspend" class="mt-4">
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        Reason <span class="text-danger-600">*</span>
                    </label>
                    <textarea
                        wire:model.blur="suspendReason"
                        rows="3"
                        autofocus
                        class="block w-full rounded-sm border bg-surface px-3 py-2 text-base text-text-primary placeholder:text-text-muted focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none {{ $errors->has('suspendReason') ? 'border-danger-600' : 'border-border' }}"
                        placeholder="e.g. Reported counterfeit consumables"
                    ></textarea>
                    @error('suspendReason')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror

                    <div class="mt-5 flex items-center justify-end gap-2">
                        <x-button type="button" variant="ghost" wire:click="cancelSuspend">Cancel</x-button>
                        <x-button type="submit" variant="danger-solid" wire:loading.attr="disabled" wire:target="suspend">
                            Block product
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
