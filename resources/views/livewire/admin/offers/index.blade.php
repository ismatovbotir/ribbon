<div>
    <x-page-header title="Commercial Offers" subtitle="Multi-seller price-quote requests submitted by buyers through the public storefront." />

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="w-full max-w-xs">
            <x-input type="search" wire:model.live.debounce.400ms="search" placeholder="Search by phone or company…" class="h-8" />
        </div>
        @if ($search !== '')
            <button type="button" wire:click="$set('search', '')" class="text-xs text-text-secondary hover:text-text-primary">Clear</button>
        @endif

        <div class="flex items-center gap-1 rounded-sm border border-border p-0.5 text-xs sm:ml-auto">
            <button
                type="button"
                wire:click="filterByStatus('')"
                class="rounded-sm px-2.5 py-1 font-medium transition-colors {{ $status === '' ? 'bg-accent-50 text-accent-700' : 'text-text-muted hover:text-text-primary' }}"
            >
                All
            </button>
            @foreach ($statuses as $option)
                <button
                    type="button"
                    wire:click="filterByStatus('{{ $option }}')"
                    class="rounded-sm px-2.5 py-1 font-medium capitalize transition-colors {{ $status === $option ? 'bg-accent-50 text-accent-700' : 'text-text-muted hover:text-text-primary' }}"
                >
                    {{ $option === 'pending' ? 'New' : ucfirst($option) }} ({{ $statusCounts[$option] ?? 0 }})
                </button>
            @endforeach
        </div>
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] border-collapse">
                <thead class="sticky top-0 z-sticky bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">Phone</th>
                        <th class="px-4 py-2.5">Company</th>
                        <th class="px-4 py-2.5 text-right">Items</th>
                        <th class="px-4 py-2.5">Status</th>
                        <th class="px-4 py-2.5 text-right">Submitted</th>
                        <th class="px-4 py-2.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody wire:loading.class="opacity-50" wire:target="search, filterByStatus" class="divide-y divide-border">
                    @forelse ($requests as $request)
                        <tr wire:key="request-{{ $request->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                            <td class="px-4 py-2 font-mono text-xs font-medium">
                                <a href="{{ route('admin.offers.show', $request) }}" wire:navigate class="hover:text-accent-700 hover:underline">
                                    {{ $request->phone }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-text-secondary">{{ $request->company_name ?: '—' }}</td>
                            <td class="px-4 py-2 text-right font-mono text-xs text-text-secondary tabular-nums">{{ $request->items_count }}</td>
                            <td class="px-4 py-2">
                                @php
                                    $variant = match ($request->status) {
                                        'fulfilled' => 'success',
                                        'pending' => 'warning',
                                        'contacted' => 'info',
                                        'cancelled' => 'muted',
                                        default => 'muted',
                                    };
                                    $label = $request->status === 'pending' ? 'New' : ucfirst($request->status);
                                @endphp
                                <x-badge :variant="$variant" dot>{{ $label }}</x-badge>
                            </td>
                            <td class="px-4 py-2 text-right text-xs text-text-secondary">{{ $request->created_at->format('M j, Y') }}</td>
                            <td class="px-4 py-2 text-right">
                                <x-button tag="a" href="{{ route('admin.offers.show', $request) }}" wire:navigate variant="secondary" size="sm">
                                    View
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                @if ($search !== '' || $status !== '')
                                    <p class="text-sm font-medium text-text-primary">No results match your filters</p>
                                    <p class="mt-1 text-sm text-text-secondary">Try a different search term or status.</p>
                                    <button type="button" wire:click="clearFilters" class="mt-3 text-sm font-medium text-accent-700 hover:underline">Clear filters</button>
                                @else
                                    <p class="text-sm font-medium text-text-primary">No commercial offer requests yet</p>
                                    <p class="mt-1 text-sm text-text-secondary">Requests submitted via the public storefront will appear here.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($requests->hasPages())
            <div class="border-t border-border px-4 py-3">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>
