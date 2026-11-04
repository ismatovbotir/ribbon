<div class="max-w-4xl">
    <x-page-header :title="$product->name ?: 'Product'" subtitle="Product listing review." :back-url="route('admin.products.index')" back-label="All products">
        <x-slot:meta>
            <x-status-badge :status="$product->status" />
            <span class="font-mono text-xs text-text-muted">#{{ $product->id }}</span>
            <span class="font-mono text-xs text-text-muted">{{ $product->created_at->format('d M Y') }}</span>
        </x-slot:meta>
    </x-page-header>

    @error('approve')
        <x-alert variant="danger">{{ $message }}</x-alert>
    @enderror

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <div class="space-y-6">
        <x-section title="Moderation">
            <dl>
                <x-fact label="Status"><x-status-badge :status="$product->status" /></x-fact>
                <x-fact label="Submitted" :mono="true">{{ $product->created_at->format('d M Y H:i') }}</x-fact>
                @if ($product->moderated_at)
                    <x-fact label="{{ $product->status === 'rejected' ? 'Rejected' : ($product->status === 'suspended' ? 'Suspended' : 'Last reviewed') }}">
                        <span class="font-mono">{{ $product->moderated_at->format('d M Y H:i') }}</span>
                        @if ($product->moderatedBy) by {{ $product->moderatedBy->name }} @endif
                    </x-fact>
                @endif
                @if (in_array($product->status, ['rejected', 'suspended']) && $product->rejection_reason)
                    <x-fact label="{{ $product->status === 'suspended' ? 'Suspension reason' : 'Rejection reason' }}">
                        <blockquote class="border-l-[3px] border-border-strong pl-3 text-text-secondary">{{ $product->rejection_reason }}</blockquote>
                    </x-fact>
                @endif
            </dl>
        </x-section>

        <x-section title="Listing details">
            <dl>
                <x-fact label="Name">{{ $product->name ?: '—' }}</x-fact>
                <x-fact label="Category">{{ $product->category?->name[$defaultLocale] ?? '—' }}</x-fact>
                <x-fact label="Brand">{{ $product->brand?->name ?? '—' }}</x-fact>
                <x-fact label="Seller">
                    @if ($product->seller)
                        <a href="{{ route('admin.sellers.show', $product->seller) }}" wire:navigate class="text-accent-700 hover:underline">{{ $product->seller->name }}</a>
                    @else
                        —
                    @endif
                </x-fact>
                <x-fact label="Vitrin price" :mono="true">
                    @if ($this->vitrinPrice)
                        {{ number_format((float) $this->vitrinPrice->price) }} UZS / {{ __('sellers.products.unit.'.$this->vitrinPrice->unit) }}
                    @else
                        —
                    @endif
                </x-fact>
            </dl>
        </x-section>

        <x-section title="Specifications">
            @if (count($this->parameterRows) > 0)
                <dl>
                    @foreach ($this->parameterRows as $row)
                        <x-fact :label="$row['label']">{{ $row['value'] }}</x-fact>
                    @endforeach
                </dl>
            @else
                <p class="text-sm text-text-secondary">No category parameter values filled in for this product.</p>
            @endif
        </x-section>
    </div>

    {{-- Decision bar. Reject is an initial-review decision only; a live product gets "Block" instead (see Product::suspend()). --}}
    <x-action-bar>
        @if ($product->status !== 'approved')
            <x-button variant="primary" wire:click="approve" wire:loading.attr="disabled" wire:target="approve">Approve</x-button>
        @else
            <span class="self-center text-sm text-text-muted">Already approved.</span>
        @endif
        @if (in_array($product->status, ['pending', 'approved'], true))
            <x-menu title="More decisions" label="More">
                <x-slot:trigger><x-button variant="secondary" aria-haspopup="menu">More</x-button></x-slot:trigger>
                @if ($product->status === 'pending')
                    <x-menu.item icon="reject" wire:click="openRejectForm" :destructive="true">Reject</x-menu.item>
                @else
                    <x-menu.item icon="block" wire:click="openSuspendForm" :destructive="true">Block</x-menu.item>
                @endif
            </x-menu>
        @endif
    </x-action-bar>

    @if ($showRejectForm)
        <x-modal kind="confirm" title="Reject this listing?" close="cancelReject">
            <p>Provide a reason. It is recorded on the listing and can be shared with the seller.</p>
            <form id="reject-form" wire:submit="reject" class="mt-4">
                <x-field label="Reason" for="rejectReason" :required="true" :error="$errors->first('rejectReason')">
                    <x-textarea id="rejectReason" wire:model.blur="rejectReason" rows="3" autofocus :error="$errors->has('rejectReason')" placeholder="e.g. Photos don't match declared specifications" />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelReject">Cancel</x-button>
                <x-button type="submit" form="reject-form" variant="danger-solid" wire:loading.attr="disabled" wire:target="reject">Reject listing</x-button>
            </x-slot:footer>
        </x-modal>
    @endif

    @if ($showSuspendForm)
        <x-modal kind="confirm" title="Block this product?" close="cancelSuspend">
            <p>This listing is currently live. Blocking removes it from the storefront. Provide a reason; it is recorded on the listing.</p>
            <form id="suspend-form" wire:submit="suspend" class="mt-4">
                <x-field label="Reason" for="suspendReason" :required="true" :error="$errors->first('suspendReason')">
                    <x-textarea id="suspendReason" wire:model.blur="suspendReason" rows="3" autofocus :error="$errors->has('suspendReason')" placeholder="e.g. Reported counterfeit consumables" />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelSuspend">Cancel</x-button>
                <x-button type="submit" form="suspend-form" variant="danger-solid" wire:loading.attr="disabled" wire:target="suspend">Block product</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
