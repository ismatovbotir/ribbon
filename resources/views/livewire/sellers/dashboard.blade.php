<div>
    <x-page-header
        :title="__('sellers.dashboard.title')"
        :subtitle="__('sellers.dashboard.welcome', ['name' => \Illuminate\Support\Facades\Auth::user()->name])"
    />

    @php
        $subStatus = $subscription['status'];
        $subHidden = $subscription['hidden_products_count'];
        $subPaidUntil = $subscription['paid_until'];
    @endphp
    <div data-subscription-banner="{{ $subStatus }}">
        @if ($subStatus === 'grace')
            <x-alert variant="warning">
                <p class="font-medium">{{ __('sellers.sub.grace', ['date' => $subscription['grace_ends_at']->format('d.m.Y')]) }}</p>
                <p class="mt-0.5"><a href="{{ route('storefront.contact') }}" class="font-medium underline underline-offset-2 hover:no-underline">{{ __('sellers.sub.contact_link') }}</a></p>
            </x-alert>
        @elseif ($subStatus === 'lapsed' || $subStatus === 'none')
            @if ($subHidden > 0)
                <x-alert variant="danger">
                    <p class="font-medium">{{ __('sellers.sub.hidden', ['count' => $subHidden]) }}</p>
                    <p class="mt-0.5">{{ __('sellers.sub.hidden_cta') }} <a href="{{ route('storefront.contact') }}" class="font-medium underline underline-offset-2 hover:no-underline">{{ __('sellers.sub.contact_link') }}</a></p>
                </x-alert>
            @elseif ($subStatus === 'none')
                <x-alert variant="info">{{ __('sellers.sub.none_note') }}</x-alert>
            @endif
        @elseif ($subStatus === 'active' && $subscription['days_left'] <= $reminderDays)
            <x-alert variant="info">{{ __('sellers.sub.expiring', ['count' => $subscription['days_left'], 'date' => $subPaidUntil->format('d.m.Y')]) }}</x-alert>
        @elseif ($subStatus === 'active')
            <p class="mb-4 text-sm text-text-secondary">{{ __('sellers.sub.active', ['date' => $subPaidUntil->format('d.m.Y')]) }}</p>
        @endif
    </div>

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
            <x-icon name="catalog" class="mx-auto size-8 text-text-muted" />
            <p class="mt-3 text-sm font-medium text-text-primary">{{ __('sellers.dashboard.no_products_title') }}</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-text-secondary">{{ __('sellers.dashboard.no_products_body') }}</p>
            <div class="mt-4">
                <x-button tag="a" href="{{ route('seller.products.create') }}" wire:navigate variant="primary">
                    {{ __('sellers.dashboard.add_product_cta') }}
                </x-button>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-4 border border-border-strong bg-surface p-4 md:flex-row md:items-center md:justify-between md:p-5">
            <div>
                <p class="text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('sellers.nav.products') }}</p>
                <p class="mt-1.5 text-lg font-semibold text-text-primary">{{ __('sellers.dashboard.products_summary', ['count' => $productsCount]) }}</p>
            </div>
            <div class="flex gap-2 [&>*]:flex-1 md:[&>*]:flex-none">
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
