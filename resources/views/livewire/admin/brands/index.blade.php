<div>
    <x-page-header title="Brands" subtitle="Sellers pick a brand when creating a product. &quot;No Brand&quot; is the default and can't be removed.">
        <x-slot:actions>
            <x-button variant="primary" wire:click="toggleCreateForm">
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                Add Brand
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    {{-- Create form — a simple inline card, not a drawer (single-field record). --}}
    @if ($showCreateForm)
        <div class="mb-6 rounded-md border border-border bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">New Brand</h2>
            </div>
            <form wire:submit="createBrand" class="px-5 py-5">
                <label class="mb-1 block text-sm font-medium text-text-primary">
                    Name <span class="text-danger-600">*</span>
                </label>
                <x-input type="text" wire:model.blur="name" :error="$errors->has('name')" placeholder="e.g. Zebra" />
                @error('name')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror

                <div class="mt-4">
                    <label class="mb-1 block text-sm font-medium text-text-primary">Logo</label>

                    @if ($logoUpload)
                        <img src="{{ $logoUpload->temporaryUrl() }}" alt="New brand logo preview" class="mb-2 h-14 w-14 rounded-sm border border-border object-cover">
                    @endif

                    <input type="file" wire:model="logoUpload" accept="image/jpeg,image/png" class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

                    <div wire:loading wire:target="logoUpload" class="mt-1 text-xs text-text-muted">Uploading…</div>

                    @error('logoUpload')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">Optional. JPG or PNG, max 1MB.</p>

                    @if ($logoUpload)
                        <button type="button" wire:click="removeLogoUpload" class="mt-1 text-xs text-danger-600 hover:underline">Remove logo</button>
                    @endif
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <x-button variant="ghost" wire:click="toggleCreateForm">Cancel</x-button>
                    <x-button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createBrand, logoUpload">
                        Save Brand
                    </x-button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4 flex items-center gap-3">
        <div class="w-full max-w-xs">
            <x-input type="search" wire:model.live.debounce.400ms="search" placeholder="Search brands…" class="h-8" />
        </div>
        @if ($search !== '')
            <button type="button" wire:click="$set('search', '')" class="text-xs text-text-secondary hover:text-text-primary">Clear</button>
        @endif
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] border-collapse">
                <thead class="sticky top-0 z-sticky bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5"><span class="sr-only">Logo</span></th>
                        <th class="px-4 py-2.5">
                            <button type="button" wire:click="sortBy('name')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                Name
                                @if ($sortField === 'name')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5 text-right">Products</th>
                        <th class="px-4 py-2.5">
                            <button type="button" wire:click="sortBy('created_at')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                Created
                                @if ($sortField === 'created_at')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody wire:loading.class="opacity-50" wire:target="search, sortBy" class="divide-y divide-border">
                    @forelse ($brands as $brand)
                        <tr wire:key="brand-{{ $brand->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                            @if ($editingBrandId === $brand->id)
                                <td class="px-4 py-2" colspan="4">
                                    <form wire:submit="updateBrand" class="flex flex-col gap-3">
                                        <div class="flex items-start gap-2">
                                            <div class="w-full max-w-xs">
                                                <x-input type="text" wire:model.blur="editingName" :error="$errors->has('editingName')" autofocus />
                                                @error('editingName')
                                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <x-button type="submit" variant="primary" size="sm" wire:loading.attr="disabled" wire:target="updateBrand, editingLogoUpload">Save</x-button>
                                            <x-button type="button" variant="ghost" size="sm" wire:click="cancelEdit">Cancel</x-button>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            @if ($editingLogoUpload)
                                                <img src="{{ $editingLogoUpload->temporaryUrl() }}" alt="New logo preview" class="h-10 w-10 rounded-sm border border-border object-cover">
                                            @elseif ($editingExistingLogoPath)
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($editingExistingLogoPath) }}" alt="Current logo" class="h-10 w-10 rounded-sm border border-border object-cover">
                                            @else
                                                <span class="flex h-10 w-10 items-center justify-center rounded-sm border border-dashed border-border-strong text-xs text-text-muted">—</span>
                                            @endif

                                            <div>
                                                <input type="file" wire:model="editingLogoUpload" accept="image/jpeg,image/png" class="block text-xs text-text-secondary file:mr-2 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-2 file:py-1 file:text-xs file:font-medium file:text-text-primary hover:file:bg-surface-hover">
                                                <div wire:loading wire:target="editingLogoUpload" class="mt-1 text-xs text-text-muted">Uploading…</div>
                                                @error('editingLogoUpload')
                                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                                @enderror
                                                @if ($editingLogoUpload || $editingExistingLogoPath)
                                                    <button type="button" wire:click="removeEditingLogo" class="mt-1 text-xs text-danger-600 hover:underline">Remove logo</button>
                                                @endif
                                            </div>
                                        </div>
                                    </form>
                                </td>
                            @else
                                <td class="px-4 py-2">
                                    @if ($brand->logo_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brand->logo_path) }}" alt="{{ $brand->name }} logo" class="h-8 w-8 rounded-sm border border-border object-cover">
                                    @else
                                        <span class="flex h-8 w-8 items-center justify-center rounded-sm border border-dashed border-border-strong text-xs font-medium text-text-muted">
                                            {{ \Illuminate\Support\Str::of($brand->name)->substr(0, 1)->upper() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 font-medium">
                                    {{ $brand->name }}
                                    @if ($brand->name === 'No Brand')
                                        <span class="ml-1.5 text-xs font-normal text-text-muted">(default, can't be deleted)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums">{{ $brand->products_count }}</td>
                                <td class="px-4 py-2 text-text-secondary">{{ $brand->created_at?->format('Y-m-d') }}</td>
                            @endif
                            <td class="px-4 py-2 text-right">
                                @if ($editingBrandId !== $brand->id)
                                    <x-dropdown align="right">
                                        <x-slot:trigger>
                                            <button type="button" class="rounded-sm p-1.5 text-text-muted hover:bg-surface-selected hover:text-text-primary" aria-label="Row actions">
                                                ⋯
                                            </button>
                                        </x-slot:trigger>

                                        <button type="button" wire:click="startEdit({{ $brand->id }})" class="block w-full px-3 py-1.5 text-left text-sm text-text-primary hover:bg-surface-hover">
                                            Edit
                                        </button>
                                        @if ($brand->name !== 'No Brand')
                                            <button type="button" wire:click="confirmDeleteBrand({{ $brand->id }})" class="block w-full px-3 py-1.5 text-left text-sm text-danger-600 hover:bg-danger-50">
                                                Delete
                                            </button>
                                        @endif
                                    </x-dropdown>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center">
                                @if ($search !== '')
                                    <p class="text-sm font-medium text-text-primary">No results match your filters</p>
                                    <p class="mt-1 text-sm text-text-secondary">Try a different search term.</p>
                                    <button type="button" wire:click="$set('search', '')" class="mt-3 text-sm font-medium text-accent-700 hover:underline">Clear filters</button>
                                @else
                                    <p class="text-sm font-medium text-text-primary">No brands yet</p>
                                    <p class="mt-1 text-sm text-text-secondary">Add the first brand for sellers to pick from.</p>
                                    <x-button variant="primary" size="sm" wire:click="toggleCreateForm" class="mt-3">Add Brand</x-button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($brands->hasPages())
            <div class="border-t border-border px-4 py-3">
                {{ $brands->links() }}
            </div>
        @endif
    </div>

    {{-- Delete confirmation modal --}}
    @if ($showDeleteConfirm)
        <div class="fixed inset-0 z-modal-backdrop bg-slate-900/40" wire:click="cancelDelete"></div>
        <div class="fixed inset-0 z-modal flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface-overlay p-5 shadow-lg">
                <h2 class="text-lg font-semibold text-text-primary">
                    Delete brand "{{ $this->deletingBrand?->name }}"?
                </h2>

                @if ($this->deleteAffectedProductCount > 0)
                    <p class="mt-2 text-sm text-text-secondary">
                        This brand can't be deleted while products still reference it.
                    </p>
                    <p class="mt-2 text-sm font-medium text-danger-600">
                        Used by {{ $this->deleteAffectedProductCount }} {{ \Illuminate\Support\Str::plural('product', $this->deleteAffectedProductCount) }}. Reassign or remove those products first.
                    </p>
                @else
                    <p class="mt-2 text-sm text-text-secondary">
                        This can't be undone.
                    </p>
                @endif

                <div class="mt-5 flex items-center justify-end gap-2">
                    <x-button variant="ghost" wire:click="cancelDelete">Cancel</x-button>
                    @if ($this->deleteAffectedProductCount === 0)
                        <x-button variant="danger-solid" wire:click="deleteBrand" wire:loading.attr="disabled" wire:target="deleteBrand">
                            Delete brand
                        </x-button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
