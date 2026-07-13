<div>
    <x-page-header title="Sellers" subtitle="Review and approve seller applications submitted through the public registration form." />

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="w-full max-w-xs">
            <x-input type="search" wire:model.live.debounce.400ms="search" placeholder="Search by name or phone…" class="h-8" />
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
                    {{ $option }} ({{ $statusCounts[$option] ?? 0 }})
                </button>
            @endforeach
        </div>
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] border-collapse">
                <thead class="sticky top-0 z-sticky bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">
                            <button type="button" wire:click="sortBy('name')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                Company
                                @if ($sortField === 'name')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5">Phone</th>
                        <th class="px-4 py-2.5">
                            <button type="button" wire:click="sortBy('status')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                Status
                                @if ($sortField === 'status')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5 text-right">
                            <button type="button" wire:click="sortBy('created_at')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                Submitted
                                @if ($sortField === 'created_at')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody wire:loading.class="opacity-50" wire:target="search, sortBy, filterByStatus" class="divide-y divide-border">
                    @forelse ($sellers as $seller)
                        <tr wire:key="seller-{{ $seller->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                            <td class="px-4 py-2 font-medium">
                                <a href="{{ route('admin.sellers.show', $seller) }}" wire:navigate class="hover:text-accent-700 hover:underline">
                                    {{ $seller->name }}
                                </a>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs text-text-secondary">{{ $seller->phone }}</td>
                            <td class="px-4 py-2">
                                @php
                                    $variant = match ($seller->status) {
                                        'approved' => 'success',
                                        'pending' => 'warning',
                                        'rejected' => 'danger',
                                        'suspended' => 'muted',
                                        default => 'muted',
                                    };
                                @endphp
                                <x-badge :variant="$variant" dot>{{ ucfirst($seller->status) }}</x-badge>
                            </td>
                            <td class="px-4 py-2 text-right text-xs text-text-secondary">{{ $seller->created_at->format('M j, Y') }}</td>
                            <td class="px-4 py-2 text-right">
                                <x-button tag="a" href="{{ route('admin.sellers.show', $seller) }}" wire:navigate variant="secondary" size="sm">
                                    Review
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center">
                                @if ($search !== '' || $status !== '')
                                    <p class="text-sm font-medium text-text-primary">No results match your filters</p>
                                    <p class="mt-1 text-sm text-text-secondary">Try a different search term or status.</p>
                                    <button type="button" wire:click="clearFilters" class="mt-3 text-sm font-medium text-accent-700 hover:underline">Clear filters</button>
                                @else
                                    <p class="text-sm font-medium text-text-primary">No seller applications yet</p>
                                    <p class="mt-1 text-sm text-text-secondary">Applications submitted via the public registration form will appear here.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sellers->hasPages())
            <div class="border-t border-border px-4 py-3">
                {{ $sellers->links() }}
            </div>
        @endif
    </div>
</div>
