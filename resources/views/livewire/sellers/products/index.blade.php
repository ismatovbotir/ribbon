<div>
    <x-page-header :title="__('sellers.products.index.title')" :subtitle="__('sellers.products.index.subtitle')">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('seller.products.create') }}" wire:navigate variant="primary">
                <x-icon name="add" class="size-4" />
                {{ __('sellers.products.index.add_button') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    {{-- One collapsible section per category (admin sort order). Only the open one loads its products. --}}
    <div class="divide-y divide-border border border-border-strong bg-surface">
        @foreach ($categories as $category)
            @php
                $c = $counts[$category->id] ?? ['total' => 0, 'pending' => 0, 'rejected' => 0, 'hidden' => 0];
                $isOpen = $openId === $category->id;
                $locked = ! $canList[$category->id];
                $categoryName = $category->name[$locale] ?? $category->name[$fallbackLocale] ?? '—';
                $panelId = 'category-panel-'.$category->id;
                $addUrl = route('seller.products.create', ['category' => $category->id]);
            @endphp

            <section wire:key="category-{{ $category->id }}" data-category="{{ $category->id }}" @if ($locked) data-locked @endif>
                <div class="flex items-center gap-2 pr-3 {{ $isOpen ? 'bg-surface-subtle' : '' }}">
                    <button
                        type="button"
                        wire:click="toggle({{ $category->id }})"
                        aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                        aria-controls="{{ $panelId }}"
                        class="flex min-h-14 min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1 py-2 pl-4 text-left hover:bg-surface-hover"
                    >
                        <x-icon name="chevron-down" class="size-4 text-text-muted {{ $isOpen ? 'rotate-180' : '' }}" />
                        <span class="min-w-0 truncate text-base font-semibold text-text-primary">{{ $categoryName }}</span>
                        <span class="font-mono text-xs text-text-muted tabular-nums" aria-label="{{ trans_choice('sellers.products.index.count_label', $c['total'], ['count' => $c['total']]) }}">{{ $c['total'] }}</span>

                        @if ($locked)
                            <x-badge variant="muted"><x-icon name="lock" class="size-3.5" />{{ __('sellers.products.index.locked_badge') }}</x-badge>
                        @elseif (! $category->requires_subscription)
                            <x-badge variant="success">{{ __('sellers.products.index.free_badge') }}</x-badge>
                        @endif

                        {{-- What needs the seller's attention, without opening the section. --}}
                        @if ($c['rejected'])
                            <x-badge variant="danger">{{ __('sellers.products.index.rejected_count', ['count' => $c['rejected']]) }}</x-badge>
                        @endif
                        @if ($c['pending'])
                            <x-badge variant="info">{{ __('sellers.products.index.pending_count', ['count' => $c['pending']]) }}</x-badge>
                        @endif
                        @if ($c['hidden'])
                            <x-badge variant="warning">{{ __('sellers.products.index.hidden_count', ['count' => $c['hidden']]) }}</x-badge>
                        @endif
                    </button>

                    @unless ($locked)
                        <x-button tag="a" href="{{ $addUrl }}" wire:navigate size="sm" aria-label="{{ __('sellers.products.index.add_in_category', ['category' => $categoryName]) }}">
                            <x-icon name="add" class="size-4" />
                            <span class="hidden sm:inline">{{ __('sellers.products.index.add_short') }}</span>
                        </x-button>
                    @endunless
                </div>

                @if ($isOpen)
                    <div id="{{ $panelId }}" class="border-t border-border">
                        @if ($locked)
                            <div class="px-4 pt-4">
                                <x-alert variant="warning">
                                    <p class="font-medium">{{ __('sellers.products.index.locked_title') }}</p>
                                    <p class="mt-0.5">{{ __('sellers.products.index.locked_body') }} <a href="{{ route('storefront.contact') }}" class="font-medium underline underline-offset-2 hover:no-underline">{{ __('sellers.sub.contact_link') }}</a></p>
                                </x-alert>
                            </div>
                        @endif

                        @if ($products->isEmpty())
                            <div class="flex flex-col items-start gap-3 px-4 py-6 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm text-text-secondary">{{ __('sellers.products.index.category_empty') }}</p>
                                @unless ($locked)
                                    <x-button tag="a" href="{{ $addUrl }}" wire:navigate variant="primary" size="sm">
                                        <x-icon name="add" class="size-4" />
                                        {{ __('sellers.products.index.add_in_category', ['category' => $categoryName]) }}
                                    </x-button>
                                @endunless
                            </div>
                        @else
                            <x-table class="border-0!">
                                <x-slot:head>
                                    <x-table.th>{{ __('sellers.products.index.table.name') }}</x-table.th>
                                    <x-table.th>{{ __('sellers.products.index.table.status') }}</x-table.th>
                                    <x-table.th align="right">{{ __('sellers.products.index.table.vitrin_price') }}</x-table.th>
                                    <x-table.th :tier="3">{{ __('sellers.products.index.table.brand') }}</x-table.th>
                                    <x-table.th :tier="3">{{ __('sellers.products.index.table.created') }}</x-table.th>
                                    <x-table.th><span class="sr-only">{{ __('sellers.products.index.table.actions') }}</span></x-table.th>
                                </x-slot:head>

                                <tbody role="rowgroup" class="block divide-y divide-border md:table-row-group">
                                    @foreach ($products as $product)
                                        @php $vitrinPrice = $product->prices->first(); @endphp
                                        <x-table.row wire:key="product-{{ $product->id }}">
                                            <x-table.cell role="primary">
                                                <a href="{{ route('seller.products.edit', $product) }}" wire:navigate class="line-clamp-2 after:absolute after:inset-0 hover:text-accent-700 hover:underline md:line-clamp-none">{{ $product->name ?: $categoryName }}</a>
                                            </x-table.cell>
                                            <x-table.cell role="status">
                                                <x-status-badge :status="$product->status">{{ __('sellers.status.'.$product->status) }}</x-status-badge>
                                                @if ($hiddenIds->has($product->id))
                                                    <x-badge variant="warning" class="ml-1">{{ __('sellers.sub.hidden_badge') }}</x-badge>
                                                @endif
                                            </x-table.cell>
                                            <x-table.cell role="meta" :label="__('sellers.products.index.table.vitrin_price')" align="right">
                                                @if ($vitrinPrice)
                                                    <span class="font-mono tabular-nums">{{ number_format((float) $vitrinPrice->price) }} UZS</span>
                                                    <span class="text-xs text-text-muted">/ {{ __('sellers.products.unit.'.$vitrinPrice->unit) }}</span>
                                                @else
                                                    <span class="text-xs text-text-muted">{{ __('sellers.products.index.no_vitrin_price') }}</span>
                                                @endif
                                            </x-table.cell>
                                            <x-table.cell role="meta" :tier="3">{{ $product->brand->name }}</x-table.cell>
                                            <x-table.cell role="meta" :tier="3" class="font-mono text-xs">{{ $product->created_at->format('d M Y') }}</x-table.cell>
                                            <x-table.cell role="actions">
                                                <x-menu :title="$product->name ?: $categoryName" :label="__('sellers.products.index.table.actions')">
                                                    <x-menu.item icon="view" :href="route('seller.products.edit', $product)" wire:navigate>{{ __('sellers.products.index.view_action') }}</x-menu.item>
                                                </x-menu>
                                            </x-table.cell>
                                        </x-table.row>
                                    @endforeach
                                </tbody>

                                @if ($products->hasPages())
                                    <x-slot:footer>{{ $products->links('pagination.admin') }}</x-slot:footer>
                                @endif
                            </x-table>
                        @endif
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</div>
