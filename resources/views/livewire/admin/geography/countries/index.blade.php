<div>
    <x-page-header :title="__('admin.ui.geography')" :subtitle="__('admin.ui.country_region_city_sellers_pick_their_company_address_from')">
        <x-slot:actions>
            <x-button variant="primary" wire:click="toggleCreateForm">
                <x-icon name="add" class="size-4" />
                {{ __('admin.ui.add_country') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    {{-- Create form — a simple inline card, same pattern as Categories. --}}
    @if ($showCreateForm)
        <div class="mb-6 rounded-md border border-border bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.new_country') }}</h2>
            </div>
            <form wire:submit="createCountry" class="px-5 py-5">
                <x-locale-tabs :incomplete="$this->incompleteLocales">
                    @foreach (config('ribbon.locales') as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak>
                            <label for="field-name-{{ $locale }}" class="mb-1 block text-sm font-medium text-text-primary">
                                {{ __('admin.ui.name') }} <span class="text-danger-600">*</span>
                            </label>
                            <x-input id="field-name-{{ $locale }}" type="text" wire:model.blur="name.{{ $locale }}" :error="$errors->has('name.'.$locale)" :placeholder="__('admin.ui.e_g_uzbekistan')" />
                            @error('name.'.$locale)
                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </x-locale-tabs>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <x-button variant="ghost" wire:click="toggleCreateForm">{{ __('admin.ui.cancel') }}</x-button>
                    <x-button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createCountry">
                        {{ __('admin.ui.save_country') }}
                    </x-button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4 flex items-center gap-3">
        <div class="w-full max-w-xs">
            <x-input type="search" wire:model.live.debounce.400ms="search" :aria-label="__('admin.ui.search_countries')" :placeholder="__('admin.ui.search_countries_2')" class="h-8" />
        </div>
        @if ($search !== '')
            <button type="button" wire:click="$set('search', '')" class="text-xs text-text-secondary hover:text-text-primary">{{ __('admin.ui.clear') }}</button>
        @endif
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[640px] border-collapse">
                <thead class="sticky top-0 z-(--z-sticky) bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">{{ __('admin.ui.name') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ __('admin.ui.regions') }}</th>
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
                    @forelse ($countries as $country)
                        <tr wire:key="country-{{ $country->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                            @if ($editingCountryId === $country->id)
                                <td class="px-4 py-3" colspan="4">
                                    <form wire:submit="updateCountry" class="max-w-lg">
                                        <x-locale-tabs :incomplete="$this->incompleteEditingLocales">
                                            @foreach (config('ribbon.locales') as $locale)
                                                <div x-show="locale === '{{ $locale }}'" x-cloak>
                                                    <x-input type="text" wire:model.blur="editingName.{{ $locale }}" :aria-label="__('admin.ui.name')" :error="$errors->has('editingName.'.$locale)" autofocus />
                                                    @error('editingName.'.$locale)
                                                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            @endforeach
                                        </x-locale-tabs>
                                        <div class="mt-3 flex items-center gap-2">
                                            <x-button type="submit" variant="primary" size="sm" wire:loading.attr="disabled" wire:target="updateCountry">{{ __('admin.ui.save') }}</x-button>
                                            <x-button type="button" variant="ghost" size="sm" wire:click="cancelEdit">{{ __('admin.ui.cancel') }}</x-button>
                                        </div>
                                    </form>
                                </td>
                            @else
                                <td class="px-4 py-2 font-medium">
                                    <a href="{{ route('admin.geography.regions.index', $country) }}" wire:navigate class="hover:text-accent-700 hover:underline">
                                        {{ $country->name[app()->getLocale()] ?? '—' }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums">{{ $country->regions_count }}</td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums text-text-secondary">{{ $country->sort_order }}</td>
                                <td class="px-4 py-2 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-button tag="a" href="{{ route('admin.geography.regions.index', $country) }}" wire:navigate variant="secondary" size="sm">
                                            {{ __('admin.ui.manage_regions') }}
                                        </x-button>
                                        <x-menu :label="__('admin.ui.row_actions')">
<x-menu.item icon="edit" wire:click="startEdit({{ $country->id }})">{{ __('admin.ui.edit') }}</x-menu.item>
                                            <x-menu.item destructive wire:click="confirmDeleteCountry({{ $country->id }})">{{ __('admin.ui.delete') }}</x-menu.item>
                                        </x-menu>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-16 text-center">
                                @if ($search !== '')
                                    <p class="text-sm font-medium text-text-primary">{{ __('admin.ui.no_results_match_your_filters') }}</p>
                                    <p class="mt-1 text-sm text-text-secondary">{{ __('admin.ui.try_a_different_search_term') }}</p>
                                    <button type="button" wire:click="$set('search', '')" class="mt-3 text-sm font-medium text-accent-700 hover:underline">{{ __('admin.ui.clear_filters') }}</button>
                                @else
                                    <p class="text-sm font-medium text-text-primary">{{ __('admin.ui.no_countries_yet') }}</p>
                                    <p class="mt-1 text-sm text-text-secondary">{{ __('admin.ui.add_the_first_country_to_start_building_the_geography_tree') }}</p>
                                    <x-button variant="primary" size="sm" wire:click="toggleCreateForm" class="mt-3">{{ __('admin.ui.add_country') }}</x-button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($countries->hasPages())
            <div class="border-t border-border px-4 py-3">
                {{ $countries->links() }}
            </div>
        @endif
    </div>

    {{-- Delete confirmation modal --}}
    @if ($showDeleteConfirm)
        <x-modal :title="__('admin.ui.delete_named', ['name' => $this->deletingCountry?->name[app()->getLocale()] ?? ''])" close="cancelDelete">
            @if ($this->deleteBlockedByRegionsCount > 0 || $this->deleteBlockedBySellersCount > 0)
                <p class="mt-2 text-sm text-text-secondary">
                    {{ __('admin.ui.this_country_can_t_be_deleted_while_it_s_still_in_use') }}
                </p>
                @if ($this->deleteBlockedByRegionsCount > 0)
                    <p class="mt-2 text-sm font-medium text-danger-600">
                        {{ __('admin.ui.has_remove_first', ['what' => trans_choice('admin.ui.regions_count', $this->deleteBlockedByRegionsCount)]) }}
                    </p>
                @endif
                @if ($this->deleteBlockedBySellersCount > 0)
                    <p class="mt-2 text-sm font-medium text-danger-600">
                        {{ __('admin.ui.used_by_what', ['what' => trans_choice('admin.ui.sellers_count', $this->deleteBlockedBySellersCount)]) }}
                    </p>
                @endif
            @else
                <p class="mt-2 text-sm text-text-secondary">
                    {{ __('admin.ui.this_can_t_be_undone') }}
                </p>
            @endif
            <x-slot:footer>
                <x-button variant="ghost" wire:click="cancelDelete">{{ __('admin.ui.cancel') }}</x-button>
                @if ($this->deleteBlockedByRegionsCount === 0 && $this->deleteBlockedBySellersCount === 0)
                    <x-button variant="danger-solid" wire:click="deleteCountry" wire:loading.attr="disabled" wire:target="deleteCountry">
                        {{ __('admin.ui.delete_country') }}
                    </x-button>
                @endif
            </x-slot:footer>
        </x-modal>
    @endif
</div>
