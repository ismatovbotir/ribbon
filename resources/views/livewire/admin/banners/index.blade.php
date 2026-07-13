<div>
    <x-page-header title="Banners" subtitle="Promotional banners scheduled for the buyer storefront — the storefront itself doesn't render them yet.">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.banners.create') }}" wire:navigate variant="primary">
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                Add Banner
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 flex items-center gap-3">
        <div class="w-full max-w-xs">
            <x-input type="search" wire:model.live.debounce.400ms="search" placeholder="Search banners…" class="h-8" />
        </div>
        @if ($search !== '')
            <button type="button" wire:click="$set('search', '')" class="text-xs text-text-secondary hover:text-text-primary">Clear</button>
        @endif
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] border-collapse">
                <thead class="sticky top-0 z-sticky bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">Image</th>
                        <th class="px-4 py-2.5">Title</th>
                        <th class="px-4 py-2.5">Placement</th>
                        <th class="px-4 py-2.5">
                            <button type="button" wire:click="sortBy('is_active')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                Status
                                @if ($sortField === 'is_active')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5 text-right">
                            <button type="button" wire:click="sortBy('sort_order')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                Sort order
                                @if ($sortField === 'sort_order')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody wire:loading.class="opacity-50" wire:target="search, sortBy" class="divide-y divide-border">
                    @forelse ($banners as $banner)
                        @php($status = \App\Livewire\Admin\Banners\Index::statusMeta($banner))
                        <tr wire:key="banner-{{ $banner->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                            <td class="px-4 py-2">
                                <div class="h-10 w-16 overflow-hidden rounded-sm border border-border bg-surface-sunken">
                                    <img
                                        src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($banner->image_path) }}"
                                        alt="{{ $banner->title[$defaultLocale] ?? 'Banner' }}"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    >
                                </div>
                            </td>
                            <td class="px-4 py-2 font-medium">
                                <a href="{{ route('admin.banners.edit', $banner) }}" wire:navigate class="hover:text-accent-700 hover:underline">
                                    {{ $banner->title[$defaultLocale] ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-text-secondary">{{ $placementLabels[$banner->placement] ?? $banner->placement }}</td>
                            <td class="px-4 py-2">
                                <x-badge :variant="$status['variant']" dot>{{ $status['label'] }}</x-badge>
                            </td>
                            <td class="px-4 py-2 text-right font-mono tabular-nums text-text-secondary">{{ $banner->sort_order }}</td>
                            <td class="px-4 py-2 text-right">
                                <x-button tag="a" href="{{ route('admin.banners.edit', $banner) }}" wire:navigate variant="secondary" size="sm">
                                    Edit
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                @if ($search !== '')
                                    <p class="text-sm font-medium text-text-primary">No results match your filters</p>
                                    <p class="mt-1 text-sm text-text-secondary">Try a different search term.</p>
                                    <button type="button" wire:click="$set('search', '')" class="mt-3 text-sm font-medium text-accent-700 hover:underline">Clear filters</button>
                                @else
                                    <p class="text-sm font-medium text-text-primary">No banners yet</p>
                                    <p class="mt-1 text-sm text-text-secondary">Create the first banner to have it ready once the storefront can display them.</p>
                                    <x-button tag="a" href="{{ route('admin.banners.create') }}" wire:navigate variant="primary" size="sm" class="mt-3">Add Banner</x-button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($banners->hasPages())
            <div class="border-t border-border px-4 py-3">
                {{ $banners->links() }}
            </div>
        @endif
    </div>
</div>
