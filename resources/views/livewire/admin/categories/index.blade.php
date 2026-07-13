<div>
    <x-page-header title="Categories" subtitle="Flat list — no nesting. Each category owns its own set of parameters.">
        <x-slot:actions>
            <x-button variant="primary" wire:click="toggleCreateForm">
                <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                Add Category
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    {{-- Create form — a simple inline card, not a drawer (small enough record). --}}
    @if ($showCreateForm)
        <div class="mb-6 rounded-md border border-border bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">New Category</h2>
            </div>
            <form wire:submit="createCategory" class="px-5 py-5">
                <x-locale-tabs :incomplete="$this->incompleteLocales">
                    @foreach (config('ribbon.locales') as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak>
                            <label class="mb-1 block text-sm font-medium text-text-primary">
                                Name <span class="text-danger-600">*</span>
                            </label>
                            <x-input type="text" wire:model.blur="name.{{ $locale }}" :error="$errors->has('name.'.$locale)" placeholder="e.g. Thermal Transfer Ribbons" />
                            @error('name.'.$locale)
                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-text-muted">
                                Slug: <span class="font-mono">{{ $this->slugPreview($locale) }}</span>
                            </p>
                        </div>
                    @endforeach
                </x-locale-tabs>

                <div class="mt-5 border-t border-border pt-4">
                    <label class="mb-1 block text-sm font-medium text-text-primary">Image</label>

                    @if ($imageUpload)
                        <img src="{{ $imageUpload->temporaryUrl() }}" alt="New category image preview" class="mb-2 h-20 w-20 rounded-sm border border-border object-cover">
                    @endif

                    <input type="file" wire:model="imageUpload" accept="image/jpeg,image/png" class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

                    <div wire:loading wire:target="imageUpload" class="mt-1 text-xs text-text-muted">Uploading…</div>

                    @error('imageUpload')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">Optional. JPG or PNG, max 1MB.</p>

                    @if ($imageUpload)
                        <button type="button" wire:click="removeImageUpload" class="mt-1 text-xs text-danger-600 hover:underline">Remove image</button>
                    @endif
                </div>

                <div class="mt-5 border-t border-border pt-4">
                    <x-toggle wire:model="isActive" label="Active" caption="Inactive categories are hidden from the buyer catalog." />
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <x-button variant="ghost" wire:click="toggleCreateForm">Cancel</x-button>
                    <x-button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createCategory, imageUpload">
                        Save Category
                    </x-button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4 flex items-center gap-3">
        <div class="w-full max-w-xs">
            <x-input type="search" wire:model.live.debounce.400ms="search" placeholder="Search categories…" class="h-8" />
        </div>
        @if ($search !== '')
            <button type="button" wire:click="$set('search', '')" class="text-xs text-text-secondary hover:text-text-primary">Clear</button>
        @endif
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] border-collapse">
                <thead class="sticky top-0 z-sticky bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5"><span class="sr-only">Image</span></th>
                        <th class="px-4 py-2.5">Name</th>
                        <th class="px-4 py-2.5">Slug</th>
                        <th class="px-4 py-2.5">
                            <button type="button" wire:click="sortBy('is_active')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                Status
                                @if ($sortField === 'is_active')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5 text-right">Parameters</th>
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
                    @forelse ($categories as $category)
                        <tr wire:key="category-{{ $category->id }}" class="h-row-comfortable text-sm text-text-primary hover:bg-surface-hover">
                            <td class="px-4 py-2">
                                @if ($category->image_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) }}" alt="{{ $category->name[$defaultLocale] ?? '' }}" class="h-8 w-8 rounded-sm border border-border object-cover">
                                @else
                                    <span class="flex h-8 w-8 items-center justify-center rounded-sm border border-dashed border-border-strong text-xs text-text-muted">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 font-medium">
                                <a href="{{ route('admin.categories.show', $category) }}" wire:navigate class="hover:text-accent-700 hover:underline">
                                    {{ $category->name[$defaultLocale] ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs text-text-secondary">{{ $category->slug[$defaultLocale] ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if ($category->is_active)
                                    <x-badge variant="success" dot>Active</x-badge>
                                @else
                                    <x-badge variant="muted" dot>Inactive</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right font-mono tabular-nums">{{ $category->parameters_count }}</td>
                            <td class="px-4 py-2 text-right font-mono tabular-nums text-text-secondary">{{ $category->sort_order }}</td>
                            <td class="px-4 py-2 text-right">
                                <x-button tag="a" href="{{ route('admin.categories.show', $category) }}" wire:navigate variant="secondary" size="sm">
                                    Manage parameters
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                @if ($search !== '')
                                    <p class="text-sm font-medium text-text-primary">No results match your filters</p>
                                    <p class="mt-1 text-sm text-text-secondary">Try a different search term.</p>
                                    <button type="button" wire:click="$set('search', '')" class="mt-3 text-sm font-medium text-accent-700 hover:underline">Clear filters</button>
                                @else
                                    <p class="text-sm font-medium text-text-primary">No categories yet</p>
                                    <p class="mt-1 text-sm text-text-secondary">Create the first category to start defining its parameters.</p>
                                    <x-button variant="primary" size="sm" wire:click="toggleCreateForm" class="mt-3">Add Category</x-button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($categories->hasPages())
            <div class="border-t border-border px-4 py-3">
                {{ $categories->links() }}
            </div>
        @endif
    </div>
</div>
