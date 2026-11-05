<div>
    <x-page-header :title="__('admin.ui.categories')" :subtitle="__('admin.ui.flat_list_no_nesting_each_category_owns_its_own_set_of_param')">
        <x-slot:actions>
            <x-button variant="primary" wire:click="toggleCreateForm">
                <x-icon name="add" class="size-4" />
                {{ __('admin.ui.add_category') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($showCreateForm)
        <x-modal kind="form" :title="__('admin.ui.new_category')" close="toggleCreateForm">
            <form id="create-category" wire:submit="createCategory" class="space-y-4">
                <x-locale-tabs :incomplete="$this->incompleteLocales">
                    @foreach (config('ribbon.locales') as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak role="tabpanel" id="panel-{{ $locale }}" aria-labelledby="tab-{{ $locale }}">
                            <x-field :label="__('admin.ui.name')" for="name-{{ $locale }}" :required="true" :error="$errors->first('name.'.$locale)">
                                <x-input id="name-{{ $locale }}" type="text" wire:model.blur="name.{{ $locale }}" :error="$errors->has('name.'.$locale)" aria-invalid="{{ $errors->has('name.'.$locale) ? 'true' : 'false' }}" :placeholder="__('admin.ui.e_g_thermal_transfer_ribbons')" />
                            </x-field>
                            <p class="mt-1.5 text-xs text-text-muted">{{ __('admin.ui.slug') }} <span class="font-mono">{{ $this->slugPreview($locale) }}</span></p>
                        </div>
                    @endforeach
                </x-locale-tabs>

                <x-field :label="__('admin.ui.image')" for="imageUpload" :hint="__('admin.ui.optional_jpg_or_png_max_1mb')" :error="$errors->first('imageUpload')">
                    @if ($imageUpload)
                        <img src="{{ $imageUpload->temporaryUrl() }}" alt="{{ __('admin.ui.new_category_image_preview') }}" class="mb-2 size-16 border border-border object-cover">
                    @endif
                    <x-file-input id="imageUpload" wire:model="imageUpload" accept="image/jpeg,image/png" />
                    <div wire:loading wire:target="imageUpload" class="mt-1 text-xs text-text-muted">{{ __('admin.ui.uploading') }}</div>
                    @if ($imageUpload)
                        <button type="button" wire:click="removeImageUpload" class="mt-1 inline-flex h-11 items-center text-sm font-medium text-danger-700 hover:underline">{{ __('admin.ui.remove_image') }}</button>
                    @endif
                </x-field>

                <x-toggle wire:model="isActive" :label="__('admin.ui.active')" :caption="__('admin.ui.inactive_categories_are_hidden_from_the_buyer_catalog')" />
                @can('super-admin')
                    <x-toggle wire:model="isFree" :label="__('admin.categories.free_toggle')" :caption="__('admin.categories.free_caption')" />
                @endcan
            </form>

            <x-slot:footer>
                <x-button variant="ghost" wire:click="toggleCreateForm">{{ __('admin.ui.cancel') }}</x-button>
                <x-button variant="primary" type="submit" form="create-category" wire:loading.attr="disabled" wire:target="createCategory, imageUpload">{{ __('admin.ui.save_category') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif

    @php($target = 'search, sortBy')
    <x-table>
        <x-slot:toolbar>
            <x-table.toolbar :active-count="$search !== '' ? 1 : 0" :result-count="$categories->total()">
                <x-slot:search>
                    <x-input type="search" enterkeyhint="search" wire:model.live.debounce.400ms="search" :placeholder="__('admin.ui.search_categories')" :aria-label="__('admin.ui.search_categories_2')" />
                </x-slot:search>
                <x-slot:chips>
                    <x-table.chip wire:click="$set('search', '')">{{ __('admin.ui.search_chip', ['value' => $search]) }}</x-table.chip>
                </x-slot:chips>
            </x-table.toolbar>
        </x-slot:toolbar>

        <x-slot:head>
            <x-table.th>{{ __('admin.ui.name') }}</x-table.th>
            <x-table.th :sortable="true" :sort="$sortField === 'is_active' ? $sortDirection : null" wire:click="sortBy('is_active')">{{ __('admin.ui.status') }}</x-table.th>
            <x-table.th align="right">{{ __('admin.ui.parameters') }}</x-table.th>
            <x-table.th :tier="3">{{ __('admin.ui.slug_2') }}</x-table.th>
            <x-table.th :tier="3" align="right" :sortable="true" :sort="$sortField === 'sort_order' ? $sortDirection : null" wire:click="sortBy('sort_order')">{{ __('admin.ui.sort_order') }}</x-table.th>
            <x-table.th><span class="sr-only">{{ __('admin.ui.actions') }}</span></x-table.th>
        </x-slot:head>

        @if ($categories->isEmpty())
            <tbody class="block md:table-row-group">
                <tr class="block md:table-row"><td colspan="6" class="block md:table-cell">
                    @if ($search !== '')
                        <x-empty-state variant="filtered" :title="__('admin.ui.no_results_match_your_filters')" :description="__('admin.ui.try_a_different_search_term')">
                            <x-slot:actions><x-button variant="secondary" wire:click="$set('search', '')">{{ __('admin.ui.clear_filters') }}</x-button></x-slot:actions>
                        </x-empty-state>
                    @else
                        <x-empty-state :title="__('admin.ui.no_categories_yet')" :description="__('admin.ui.empty_categories')">
                            <x-slot:actions><x-button variant="primary" wire:click="toggleCreateForm">{{ __('admin.ui.add_category') }}</x-button></x-slot:actions>
                        </x-empty-state>
                    @endif
                </td></tr>
            </tbody>
        @else
            <tbody role="rowgroup" wire:loading.remove wire:target="{{ $target }}" class="block divide-y divide-border md:table-row-group">
                @foreach ($categories as $category)
                    <x-table.row wire:key="category-{{ $category->id }}">
                        <x-table.cell role="primary">
                            <a href="{{ route('admin.categories.show', $category) }}" wire:navigate class="line-clamp-2 after:absolute after:inset-0 hover:text-accent-700 hover:underline md:line-clamp-none">{{ $category->name[app()->getLocale()] ?? '—' }}</a>
                        </x-table.cell>
                        <x-table.cell role="status">
                            <x-badge :variant="$category->is_active ? 'success' : 'muted'">{{ $category->is_active ? __('admin.ui.active') : __('admin.ui.inactive') }}</x-badge>
                            @unless ($category->requires_subscription)
                                <x-badge variant="info">{{ __('admin.categories.free_badge') }}</x-badge>
                            @endunless
                        </x-table.cell>
                        <x-table.cell role="meta" :label="__('admin.ui.parameters')" :mono="true" align="right">{{ $category->parameters_count }}</x-table.cell>
                        <x-table.cell role="meta" :tier="3" class="font-mono text-xs">{{ $category->slug[$defaultLocale] ?? '—' }}</x-table.cell>
                        <x-table.cell role="meta" :tier="3" :mono="true" align="right">{{ $category->sort_order }}</x-table.cell>
                        <x-table.cell role="actions">
                            <x-menu :title="$category->name[app()->getLocale()] ?? 'Category'" :label="__('admin.ui.row_actions')">
                                <x-menu.item icon="filter" :href="route('admin.categories.show', $category)" wire:navigate>{{ __('admin.ui.manage_parameters') }}</x-menu.item>
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
