<div>
    <x-page-header :title="__('sellers.products.index.title')" :subtitle="__('sellers.products.index.subtitle')">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('seller.products.create') }}" wire:navigate variant="primary">
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                {{ __('sellers.products.index.add_button') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($products->isEmpty())
        <div class="rounded-md border border-dashed border-border-strong bg-surface-subtle p-10 text-center">
            <svg class="mx-auto h-8 w-8 text-text-muted" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <rect x="2.5" y="4" width="15" height="12" rx="1.5" stroke="currentColor" stroke-width="1.4" />
                <path d="M2.5 8h15" stroke="currentColor" stroke-width="1.4" />
            </svg>
            <p class="mt-3 text-sm font-medium text-text-primary">{{ __('sellers.products.index.empty_title') }}</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-text-secondary">{{ __('sellers.products.index.empty_body') }}</p>
            <div class="mt-4">
                <x-button tag="a" href="{{ route('seller.products.create') }}" wire:navigate variant="primary">
                    {{ __('sellers.products.index.empty_cta') }}
                </x-button>
            </div>
        </div>
    @else
        <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] border-collapse">
                    <thead class="sticky top-0 z-sticky bg-surface-subtle">
                        <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                            <th class="px-4 py-2.5">{{ __('sellers.products.index.table.name') }}</th>
                            <th class="px-4 py-2.5">{{ __('sellers.products.index.table.category') }}</th>
                            <th class="px-4 py-2.5">{{ __('sellers.products.index.table.brand') }}</th>
                            <th class="px-4 py-2.5">{{ __('sellers.products.index.table.status') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('sellers.products.index.table.vitrin_price') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('sellers.products.index.table.created') }}</th>
                            <th class="px-4 py-2.5"><span class="sr-only">{{ __('sellers.products.index.table.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($products as $product)
                            @php
                                $locale = app()->getLocale();
                                $fallbackLocale = config('ribbon.locales')[0];
                                $categoryName = $product->category->name[$locale] ?? $product->category->name[$fallbackLocale] ?? '—';
                                $vitrinPrice = $product->prices->first();
                                $statusVariant = match ($product->status) {
                                    'approved' => 'success',
                                    'pending' => 'warning',
                                    'rejected' => 'danger',
                                    'suspended' => 'muted',
                                    default => 'muted',
                                };
                            @endphp
                            <tr wire:key="product-{{ $product->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                                <td class="px-4 py-2 font-medium">
                                    <a href="{{ route('seller.products.edit', $product) }}" wire:navigate class="hover:text-accent-700 hover:underline">
                                        {{ $product->name ?: $categoryName }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-text-secondary">{{ $categoryName }}</td>
                                <td class="px-4 py-2 text-text-secondary">{{ $product->brand->name }}</td>
                                <td class="px-4 py-2">
                                    <x-badge :variant="$statusVariant" dot>{{ __('sellers.status.'.$product->status) }}</x-badge>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @if ($vitrinPrice)
                                        <span class="font-mono text-xs tabular-nums">{{ number_format((float) $vitrinPrice->price) }} UZS</span>
                                        <span class="text-xs text-text-muted">/ {{ __('sellers.products.unit.'.$vitrinPrice->unit) }}</span>
                                    @else
                                        <span class="text-xs text-text-muted">{{ __('sellers.products.index.no_vitrin_price') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right text-xs text-text-secondary">{{ $product->created_at->format('M j, Y') }}</td>
                                <td class="px-4 py-2 text-right">
                                    <x-button tag="a" href="{{ route('seller.products.edit', $product) }}" wire:navigate variant="secondary" size="sm">
                                        {{ __('sellers.products.index.view_action') }}
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="border-t border-border px-4 py-3">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
