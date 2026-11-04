<div>
    <x-page-header title="Categories" subtitle="Flat list — no nesting. Each category owns its own set of parameters.">
        <x-slot:actions>
            <x-button variant="primary" wire:click="toggleCreateForm">
                <x-icon name="add" class="size-4" />
                Add category
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($showCreateForm)
        <x-modal kind="form" title="New category" close="toggleCreateForm">
            <form id="create-category" wire:submit="createCategory" class="space-y-4">
                <x-locale-tabs :incomplete="$this->incompleteLocales">
                    @foreach (config('ribbon.locales') as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak role="tabpanel" id="panel-{{ $locale }}" aria-labelledby="tab-{{ $locale }}">
                            <x-field label="Name" for="name-{{ $locale }}" :required="true" :error="$errors->first('name.'.$locale)">
                                <x-input id="name-{{ $locale }}" type="text" wire:model.blur="name.{{ $locale }}" :error="$errors->has('name.'.$locale)" aria-invalid="{{ $errors->has('name.'.$locale) ? 'true' : 'false' }}" placeholder="e.g. Thermal Transfer Ribbons" />
                            </x-field>
                            <p class="mt-1.5 text-xs text-text-muted">Slug: <span class="font-mono">{{ $this->slugPreview($locale) }}</span></p>
                        </div>
                    @endforeach
                </x-locale-tabs>

                <x-field label="Image" for="imageUpload" hint="Optional. JPG or PNG, max 1MB." :error="$errors->first('imageUpload')">
                    @if ($imageUpload)
                        <img src="{{ $imageUpload->temporaryUrl() }}" alt="New category image preview" class="mb-2 size-16 border border-border object-cover">
                    @endif
                    <x-file-input id="imageUpload" wire:model="imageUpload" accept="image/jpeg,image/png" />
                    <div wire:loading wire:target="imageUpload" class="mt-1 text-xs text-text-muted">Uploading…</div>
                    @if ($imageUpload)
                        <button type="button" wire:click="removeImageUpload" class="mt-1 inline-flex h-11 items-center text-sm font-medium text-danger-700 hover:underline">Remove image</button>
                    @endif
                </x-field>

                <x-toggle wire:model="isActive" label="Active" caption="Inactive categories are hidden from the buyer catalog." />
                @can('super-admin')
                    <x-toggle wire:model="isFree" :label="__('admin.categories.free_toggle')" :caption="__('admin.categories.free_caption')" />
                @endcan
            </form>

            <x-slot:footer>
                <x-button variant="ghost" wire:click="toggleCreateForm">Cancel</x-button>
                <x-button variant="primary" type="submit" form="create-category" wire:loading.attr="disabled" wire:target="createCategory, imageUpload">Save category</x-button>
            </x-slot:footer>
        </x-modal>
    @endif

    @php($target = 'search, sortBy')
    <x-table>
        <x-slot:toolbar>
            <x-table.toolbar :active-count="$search !== '' ? 1 : 0" :result-count="$categories->total()">
                <x-slot:search>
                    <x-input type="search" enterkeyhint="search" wire:model.live.debounce.400ms="search" placeholder="Search categories…" aria-label="Search categories" />
                </x-slot:search>
                <x-slot:chips>
                    <x-table.chip wire:click="$set('search', '')">Search: {{ $search }}</x-table.chip>
                </x-slot:chips>
            </x-table.toolbar>
        </x-slot:toolbar>

        <x-slot:head>
            <x-table.th>Name</x-table.th>
            <x-table.th :sortable="true" :sort="$sortField === 'is_active' ? $sortDirection : null" wire:click="sortBy('is_active')">Status</x-table.th>
            <x-table.th align="right">Parameters</x-table.th>
            <x-table.th :tier="3">Slug</x-table.th>
            <x-table.th :tier="3" align="right" :sortable="true" :sort="$sortField === 'sort_order' ? $sortDirection : null" wire:click="sortBy('sort_order')">Sort order</x-table.th>
            <x-table.th><span class="sr-only">Actions</span></x-table.th>
        </x-slot:head>

        @if ($categories->isEmpty())
            <tbody class="block md:table-row-group">
                <tr class="block md:table-row"><td colspan="6" class="block md:table-cell">
                    @if ($search !== '')
                        <x-empty-state variant="filtered" title="No results match your filters" description="Try a different search term.">
                            <x-slot:actions><x-button variant="secondary" wire:click="$set('search', '')">Clear filters</x-button></x-slot:actions>
                        </x-empty-state>
                    @else
                        <x-empty-state title="No categories yet" description="Create the first category to start defining its parameters.">
                            <x-slot:actions><x-button variant="primary" wire:click="toggleCreateForm">Add category</x-button></x-slot:actions>
                        </x-empty-state>
                    @endif
                </td></tr>
            </tbody>
        @else
            <tbody role="rowgroup" wire:loading.remove wire:target="{{ $target }}" class="block divide-y divide-border md:table-row-group">
                @foreach ($categories as $category)
                    <x-table.row wire:key="category-{{ $category->id }}">
                        <x-table.cell role="primary">
                            <a href="{{ route('admin.categories.show', $category) }}" wire:navigate class="line-clamp-2 after:absolute after:inset-0 hover:text-accent-700 hover:underline md:line-clamp-none">{{ $category->name[$defaultLocale] ?? '—' }}</a>
                        </x-table.cell>
                        <x-table.cell role="status">
                            <x-badge :variant="$category->is_active ? 'success' : 'muted'">{{ $category->is_active ? 'Active' : 'Inactive' }}</x-badge>
                            @unless ($category->requires_subscription)
                                <x-badge variant="info">{{ __('admin.categories.free_badge') }}</x-badge>
                            @endunless
                        </x-table.cell>
                        <x-table.cell role="meta" label="Parameters" :mono="true" align="right">{{ $category->parameters_count }}</x-table.cell>
                        <x-table.cell role="meta" :tier="3" class="font-mono text-xs">{{ $category->slug[$defaultLocale] ?? '—' }}</x-table.cell>
                        <x-table.cell role="meta" :tier="3" :mono="true" align="right">{{ $category->sort_order }}</x-table.cell>
                        <x-table.cell role="actions">
                            <x-menu :title="$category->name[$defaultLocale] ?? 'Category'" label="Row actions">
                                <x-menu.item icon="filter" :href="route('admin.categories.show', $category)" wire:navigate>Manage parameters</x-menu.item>
                            </x-menu>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </tbody>
            <x-table.skeleton :target="$target" :rows="6" :cols="5" />
        @endif

        <x-slot:footer>{{ $categories->links('pagination.admin') }}</x-slot:footer>
    </x-table>
</div>
