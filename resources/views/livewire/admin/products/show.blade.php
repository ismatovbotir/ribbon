<div class="mx-auto w-full max-w-4xl">
    <x-page-header :title="$product->name ?: 'Product'" :subtitle="__('admin.ui.product_listing_review')" :back-url="route('admin.products.index')" :back-label="__('admin.ui.all_products')">
        <x-slot:meta>
            <x-status-badge :status="$product->status" />
            <span class="font-mono text-xs text-text-muted">#{{ $product->id }}</span>
            <span class="font-mono text-xs text-text-muted">{{ $product->created_at->translatedFormat('d M Y') }}</span>
        </x-slot:meta>
    </x-page-header>

    @error('approve')
        <x-alert variant="danger">{{ $message }}</x-alert>
    @enderror

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <div class="space-y-6">
        <x-section :title="__('admin.ui.moderation')">
            <dl>
                <x-fact :label="__('admin.ui.status')"><x-status-badge :status="$product->status" /></x-fact>
                <x-fact :label="__('admin.ui.submitted')" :mono="true">{{ $product->created_at->translatedFormat('d M Y H:i') }}</x-fact>
                @if ($product->moderated_at)
                    <x-fact :label="$product->status === 'rejected' ? __('admin.ui.rejected_at') : ($product->status === 'suspended' ? __('admin.ui.suspended_at') : __('admin.ui.last_reviewed'))">
                        <span class="font-mono">{{ $product->moderated_at->translatedFormat('d M Y H:i') }}</span>
                        @if ($product->moderatedBy) {{ __('admin.ui.by_name', ['name' => $product->moderatedBy->name]) }} @endif
                    </x-fact>
                @endif
                @if (in_array($product->status, ['rejected', 'suspended']) && $product->rejection_reason)
                    <x-fact :label="$product->status === 'suspended' ? __('admin.ui.suspension_reason') : __('admin.ui.rejection_reason')">
                        <blockquote class="border-l-[3px] border-border-strong pl-3 text-text-secondary">{{ $product->rejection_reason }}</blockquote>
                    </x-fact>
                @endif
            </dl>
        </x-section>

        <x-section :title="__('admin.ui.listing_details')">
            <dl>
                <x-fact :label="__('admin.ui.name')">{{ $product->name ?: '—' }}</x-fact>
                <x-fact :label="__('admin.ui.category')">{{ $product->category?->name[app()->getLocale()] ?? '—' }}</x-fact>
                <x-fact :label="__('admin.ui.brand')">{{ $product->brand?->name ?? '—' }}</x-fact>
                <x-fact :label="__('admin.ui.seller')">
                    @if ($product->seller)
                        <a href="{{ route('admin.sellers.show', $product->seller) }}" wire:navigate class="text-accent-700 hover:underline">{{ $product->seller->name }}</a>
                    @else
                        —
                    @endif
                </x-fact>
                <x-fact :label="__('admin.ui.vitrin_price')" :mono="true">
                    @if ($this->vitrinPrice)
                        {{ number_format((float) $this->vitrinPrice->price) }} UZS / {{ __('sellers.products.unit.'.$this->vitrinPrice->unit) }}
                    @else
                        —
                    @endif
                </x-fact>
            </dl>
        </x-section>

        <x-section :title="__('admin.ui.specifications')">
            @if (count($this->parameterRows) > 0)
                <dl>
                    @foreach ($this->parameterRows as $row)
                        <x-fact :label="$row['label']">{{ $row['value'] }}</x-fact>
                    @endforeach
                </dl>
            @else
                <p class="text-sm text-text-secondary">{{ __('admin.ui.no_category_parameter_values_filled_in_for_this_product') }}</p>
            @endif
        </x-section>
    </div>

    {{-- Decision bar. Reject is an initial-review decision only; a live product gets "Block" instead (see Product::suspend()). --}}
    <x-action-bar>
        @if ($product->status !== 'approved')
            <x-button variant="primary" wire:click="approve" wire:loading.attr="disabled" wire:target="approve">{{ __('admin.ui.approve') }}</x-button>
        @else
            <span class="self-center text-sm text-text-muted">{{ __('admin.ui.already_approved') }}</span>
        @endif
        @if (in_array($product->status, ['pending', 'approved'], true))
            <x-menu :title="__('admin.ui.more_decisions')" :label="__('admin.ui.more')">
                <x-slot:trigger><x-button variant="secondary" aria-haspopup="menu">{{ __('admin.ui.more') }}</x-button></x-slot:trigger>
                @if ($product->status === 'pending')
                    <x-menu.item icon="reject" wire:click="openRejectForm" :destructive="true">{{ __('admin.ui.reject') }}</x-menu.item>
                @else
                    <x-menu.item icon="block" wire:click="openSuspendForm" :destructive="true">{{ __('admin.ui.block') }}</x-menu.item>
                @endif
            </x-menu>
        @endif
    </x-action-bar>

    @if ($showRejectForm)
        <x-modal kind="confirm" :title="__('admin.ui.reject_this_listing')" close="cancelReject">
            <p>{{ __('admin.ui.provide_a_reason_it_is_recorded_on_the_listing_and_can_be_sh') }}</p>
            <form id="reject-form" wire:submit="reject" class="mt-4">
                <x-field :label="__('admin.ui.reason')" for="rejectReason" :required="true" :error="$errors->first('rejectReason')">
                    <x-textarea id="rejectReason" wire:model.blur="rejectReason" rows="3" autofocus :error="$errors->has('rejectReason')" :placeholder="__('admin.ui.e_g_photos_don_t_match_declared_specifications')" />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelReject">{{ __('admin.ui.cancel') }}</x-button>
                <x-button type="submit" form="reject-form" variant="danger-solid" wire:loading.attr="disabled" wire:target="reject">{{ __('admin.ui.reject_listing') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif

    @if ($showSuspendForm)
        <x-modal kind="confirm" :title="__('admin.ui.block_this_product')" close="cancelSuspend">
            <p>{{ __('admin.ui.this_listing_is_currently_live_blocking_removes_it_from_the') }}</p>
            <form id="suspend-form" wire:submit="suspend" class="mt-4">
                <x-field :label="__('admin.ui.reason')" for="suspendReason" :required="true" :error="$errors->first('suspendReason')">
                    <x-textarea id="suspendReason" wire:model.blur="suspendReason" rows="3" autofocus :error="$errors->has('suspendReason')" :placeholder="__('admin.ui.e_g_reported_counterfeit_consumables')" />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelSuspend">{{ __('admin.ui.cancel') }}</x-button>
                <x-button type="submit" form="suspend-form" variant="danger-solid" wire:loading.attr="disabled" wire:target="suspend">{{ __('admin.ui.block_product') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
