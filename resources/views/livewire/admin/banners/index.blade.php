<div>
    <x-page-header :title="__('admin.ui.banners')" :subtitle="__('admin.ui.promotional_banners_shown_on_the_buyer_storefront_while_they')">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.banners.create') }}" wire:navigate variant="primary">
                <x-icon name="add" class="size-4" />
                {{ __('admin.ui.add_banner') }}
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
            <x-input type="search" wire:model.live.debounce.400ms="search" :aria-label="__('admin.ui.search_banners')" :placeholder="__('admin.ui.search_banners_2')" class="h-8" />
        </div>
        @if ($search !== '')
            <button type="button" wire:click="$set('search', '')" class="text-xs text-text-secondary hover:text-text-primary">{{ __('admin.ui.clear') }}</button>
        @endif
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[820px] border-collapse">
                <thead class="sticky top-0 z-(--z-sticky) bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">{{ __('admin.ui.image') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.ui.title') }}</th>
                        <th class="px-4 py-2.5">{{ __('admin.ui.placement') }}</th>
                        <th class="px-4 py-2.5">
                            <button type="button" wire:click="sortBy('is_active')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                {{ __('admin.ui.status') }}
                                @if ($sortField === 'is_active')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5 text-right">
                            <button type="button" wire:click="sortBy('sort_order')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                {{ __('admin.ui.sort_order') }}
                                @if ($sortField === 'sort_order')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5"><span class="sr-only">{{ __('admin.ui.actions') }}</span></th>
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
                                        alt="{{ $banner->title[app()->getLocale()] ?? 'Banner' }}"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    >
                                </div>
                            </td>
                            <td class="px-4 py-2 font-medium">
                                <a href="{{ route('admin.banners.edit', $banner) }}" wire:navigate class="hover:text-accent-700 hover:underline">
                                    {{ $banner->title[app()->getLocale()] ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-text-secondary">{{ __('admin.ui.placement_'.$banner->placement) }}</td>
                            <td class="px-4 py-2">
                                <x-badge :variant="$status['variant']" dot>{{ $status['label'] }}</x-badge>
                            </td>
                            <td class="px-4 py-2 text-right font-mono tabular-nums text-text-secondary">{{ $banner->sort_order }}</td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-button tag="a" href="{{ route('admin.banners.edit', $banner) }}" wire:navigate variant="secondary" size="sm">{{ __('admin.ui.edit') }}</x-button>
                                    <x-button variant="danger" size="sm" wire:click="confirmDelete({{ $banner->id }})">{{ __('admin.ui.delete') }}</x-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                @if ($search !== '')
                                    <p class="text-sm font-medium text-text-primary">{{ __('admin.ui.no_results_match_your_filters') }}</p>
                                    <p class="mt-1 text-sm text-text-secondary">{{ __('admin.ui.try_a_different_search_term') }}</p>
                                    <button type="button" wire:click="$set('search', '')" class="mt-3 text-sm font-medium text-accent-700 hover:underline">{{ __('admin.ui.clear_filters') }}</button>
                                @else
                                    <p class="text-sm font-medium text-text-primary">{{ __('admin.ui.no_banners_yet') }}</p>
                                    <p class="mt-1 text-sm text-text-secondary">{{ __('admin.ui.create_the_first_banner_to_show_it_on_the_storefront') }}</p>
                                    <x-button tag="a" href="{{ route('admin.banners.create') }}" wire:navigate variant="primary" size="sm" class="mt-3">{{ __('admin.ui.add_banner') }}</x-button>
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

    @if ($this->deletingBanner)
        <x-modal :title="__('admin.ui.delete_named', ['name' => $this->deletingBanner->title[app()->getLocale()] ?? ''])" close="cancelDelete">
            <p class="text-sm text-text-secondary">{{ __('admin.ui.it_is_removed_from_the_storefront_immediately_this_can_t_be') }}</p>
            <x-slot:footer>
                <x-button variant="ghost" wire:click="cancelDelete">{{ __('admin.ui.cancel') }}</x-button>
                <x-button variant="danger-solid" wire:click="deleteBanner" wire:loading.attr="disabled" wire:target="deleteBanner">{{ __('admin.ui.delete_banner') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
