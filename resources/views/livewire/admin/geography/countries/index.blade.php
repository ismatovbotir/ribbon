<div>
    <x-page-header title="Geography" subtitle="Country → Region → City. Sellers pick their service territory from this tree.">
        <x-slot:actions>
            <x-button variant="primary" wire:click="toggleCreateForm">
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                Add Country
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
                <h2 class="text-lg font-semibold text-text-primary">New Country</h2>
            </div>
            <form wire:submit="createCountry" class="px-5 py-5">
                <x-locale-tabs :incomplete="$this->incompleteLocales">
                    @foreach (config('ribbon.locales') as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak>
                            <label class="mb-1 block text-sm font-medium text-text-primary">
                                Name <span class="text-danger-600">*</span>
                            </label>
                            <x-input type="text" wire:model.blur="name.{{ $locale }}" :error="$errors->has('name.'.$locale)" placeholder="e.g. Uzbekistan" />
                            @error('name.'.$locale)
                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </x-locale-tabs>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <x-button variant="ghost" wire:click="toggleCreateForm">Cancel</x-button>
                    <x-button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createCountry">
                        Save Country
                    </x-button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4 flex items-center gap-3">
        <div class="w-full max-w-xs">
            <x-input type="search" wire:model.live.debounce.400ms="search" placeholder="Search countries…" class="h-8" />
        </div>
        @if ($search !== '')
            <button type="button" wire:click="$set('search', '')" class="text-xs text-text-secondary hover:text-text-primary">Clear</button>
        @endif
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] border-collapse">
                <thead class="sticky top-0 z-sticky bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5">Name</th>
                        <th class="px-4 py-2.5 text-right">Regions</th>
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
                    @forelse ($countries as $country)
                        <tr wire:key="country-{{ $country->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                            @if ($editingCountryId === $country->id)
                                <td class="px-4 py-3" colspan="4">
                                    <form wire:submit="updateCountry" class="max-w-lg">
                                        <x-locale-tabs :incomplete="$this->incompleteEditingLocales">
                                            @foreach (config('ribbon.locales') as $locale)
                                                <div x-show="locale === '{{ $locale }}'" x-cloak>
                                                    <x-input type="text" wire:model.blur="editingName.{{ $locale }}" :error="$errors->has('editingName.'.$locale)" autofocus />
                                                    @error('editingName.'.$locale)
                                                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            @endforeach
                                        </x-locale-tabs>
                                        <div class="mt-3 flex items-center gap-2">
                                            <x-button type="submit" variant="primary" size="sm" wire:loading.attr="disabled" wire:target="updateCountry">Save</x-button>
                                            <x-button type="button" variant="ghost" size="sm" wire:click="cancelEdit">Cancel</x-button>
                                        </div>
                                    </form>
                                </td>
                            @else
                                <td class="px-4 py-2 font-medium">
                                    <a href="{{ route('admin.geography.regions.index', $country) }}" wire:navigate class="hover:text-accent-700 hover:underline">
                                        {{ $country->name[$defaultLocale] ?? '—' }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums">{{ $country->regions_count }}</td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums text-text-secondary">{{ $country->sort_order }}</td>
                                <td class="px-4 py-2 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-button tag="a" href="{{ route('admin.geography.regions.index', $country) }}" wire:navigate variant="secondary" size="sm">
                                            Manage regions
                                        </x-button>
                                        <x-dropdown align="right">
                                            <x-slot:trigger>
                                                <button type="button" class="rounded-sm p-1.5 text-text-muted hover:bg-surface-selected hover:text-text-primary" aria-label="Row actions">
                                                    ⋯
                                                </button>
                                            </x-slot:trigger>

                                            <button type="button" wire:click="startEdit({{ $country->id }})" class="block w-full px-3 py-1.5 text-left text-sm text-text-primary hover:bg-surface-hover">
                                                Edit
                                            </button>
                                            <button type="button" wire:click="confirmDeleteCountry({{ $country->id }})" class="block w-full px-3 py-1.5 text-left text-sm text-danger-600 hover:bg-danger-50">
                                                Delete
                                            </button>
                                        </x-dropdown>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-16 text-center">
                                @if ($search !== '')
                                    <p class="text-sm font-medium text-text-primary">No results match your filters</p>
                                    <p class="mt-1 text-sm text-text-secondary">Try a different search term.</p>
                                    <button type="button" wire:click="$set('search', '')" class="mt-3 text-sm font-medium text-accent-700 hover:underline">Clear filters</button>
                                @else
                                    <p class="text-sm font-medium text-text-primary">No countries yet</p>
                                    <p class="mt-1 text-sm text-text-secondary">Add the first country to start building the geography tree.</p>
                                    <x-button variant="primary" size="sm" wire:click="toggleCreateForm" class="mt-3">Add Country</x-button>
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
        <div class="fixed inset-0 z-modal-backdrop bg-slate-900/40" wire:click="cancelDelete"></div>
        <div class="fixed inset-0 z-modal flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface-overlay p-5 shadow-lg">
                <h2 class="text-lg font-semibold text-text-primary">
                    Delete country "{{ $this->deletingCountry?->name[$defaultLocale] ?? '' }}"?
                </h2>

                @if ($this->deleteBlockedByRegionsCount > 0 || $this->deleteBlockedBySellersCount > 0)
                    <p class="mt-2 text-sm text-text-secondary">
                        This country can't be deleted while it's still in use.
                    </p>
                    @if ($this->deleteBlockedByRegionsCount > 0)
                        <p class="mt-2 text-sm font-medium text-danger-600">
                            Has {{ $this->deleteBlockedByRegionsCount }} {{ \Illuminate\Support\Str::plural('region', $this->deleteBlockedByRegionsCount) }}. Remove them first.
                        </p>
                    @endif
                    @if ($this->deleteBlockedBySellersCount > 0)
                        <p class="mt-2 text-sm font-medium text-danger-600">
                            Used by {{ $this->deleteBlockedBySellersCount }} {{ \Illuminate\Support\Str::plural('seller', $this->deleteBlockedBySellersCount) }}.
                        </p>
                    @endif
                @else
                    <p class="mt-2 text-sm text-text-secondary">
                        This can't be undone.
                    </p>
                @endif

                <div class="mt-5 flex items-center justify-end gap-2">
                    <x-button variant="ghost" wire:click="cancelDelete">Cancel</x-button>
                    @if ($this->deleteBlockedByRegionsCount === 0 && $this->deleteBlockedBySellersCount === 0)
                        <x-button variant="danger-solid" wire:click="deleteCountry" wire:loading.attr="disabled" wire:target="deleteCountry">
                            Delete country
                        </x-button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
