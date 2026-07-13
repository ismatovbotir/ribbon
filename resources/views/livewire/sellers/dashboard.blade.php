<div>
    <x-page-header
        :title="__('sellers.dashboard.title')"
        :subtitle="__('sellers.dashboard.welcome', ['name' => \Illuminate\Support\Facades\Auth::user()->name])"
    />

    <div class="mb-6 rounded-md border border-border bg-surface-raised p-5">
        <p class="text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('sellers.dashboard.company_label') }}</p>
        <p class="mt-1.5 text-lg font-semibold text-text-primary">{{ $seller->name }}</p>

        @php
            $statusVariant = match ($seller->status) {
                'approved' => 'success',
                'pending' => 'warning',
                'rejected' => 'danger',
                'suspended' => 'muted',
                default => 'muted',
            };
        @endphp
        <div class="mt-2">
            <x-badge :variant="$statusVariant" dot>{{ __('sellers.status.'.$seller->status) }}</x-badge>
        </div>
    </div>

    @if ($productsCount === 0)
        <div class="rounded-md border border-dashed border-border-strong bg-surface-subtle p-10 text-center">
            <svg class="mx-auto h-8 w-8 text-text-muted" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <rect x="2.5" y="4" width="15" height="12" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                <path d="M2.5 8h15" stroke="currentColor" stroke-width="1.4" />
            </svg>
            <p class="mt-3 text-sm font-medium text-text-primary">{{ __('sellers.dashboard.no_products_title') }}</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-text-secondary">{{ __('sellers.dashboard.no_products_body') }}</p>
            <div class="mt-4">
                <x-button tag="a" href="{{ route('seller.products.create') }}" wire:navigate variant="primary">
                    {{ __('sellers.dashboard.add_product_cta') }}
                </x-button>
            </div>
        </div>
    @else
        <div class="flex items-center justify-between rounded-md border border-border bg-surface-raised p-5">
            <div>
                <p class="text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('sellers.nav.products') }}</p>
                <p class="mt-1.5 text-lg font-semibold text-text-primary">{{ __('sellers.dashboard.products_summary', ['count' => $productsCount]) }}</p>
            </div>
            <div class="flex items-center gap-2">
                <x-button tag="a" href="{{ route('seller.products.index') }}" wire:navigate variant="secondary">
                    {{ __('sellers.dashboard.view_products_cta') }}
                </x-button>
                <x-button tag="a" href="{{ route('seller.products.create') }}" wire:navigate variant="primary">
                    {{ __('sellers.dashboard.add_product_cta') }}
                </x-button>
            </div>
        </div>
    @endif
</div>
