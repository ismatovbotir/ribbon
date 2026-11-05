<div>
    <x-page-header :title="__('admin.ui.brands')" :subtitle="__('admin.ui.brands_subtitle')">
        <x-slot:actions>
            <x-button variant="primary" wire:click="toggleCreateForm">
                <x-icon name="add" class="size-4" />
                {{ __('admin.ui.add_brand') }}
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
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.new_brand') }}</h2>
            </div>
            <form wire:submit="createBrand" class="px-5 py-5">
                <label for="field-name" class="mb-1 block text-sm font-medium text-text-primary">
                    {{ __('admin.ui.name') }} <span class="text-danger-600">*</span>
                </label>
                <x-input id="field-name" type="text" wire:model.blur="name" :error="$errors->has('name')" placeholder="e.g. Zebra" />
                @error('name')
                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                @enderror

                <div class="mt-4">
                    <label for="field-logoUpload" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.logo') }}</label>

                    @if ($logoUpload)
                        <img src="{{ $logoUpload->temporaryUrl() }}" alt="{{ __('admin.ui.new_brand_logo_preview') }}" class="mb-2 h-14 w-14 rounded-sm border border-border object-cover">
                    @endif

                    <input id="field-logoUpload" type="file" wire:model="logoUpload" accept="image/jpeg,image/png" class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

                    <div wire:loading wire:target="logoUpload" class="mt-1 text-xs text-text-muted">{{ __('admin.ui.uploading') }}</div>

                    @error('logoUpload')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.optional_jpg_or_png_max_1mb') }}</p>

                    @if ($logoUpload)
                        <button type="button" wire:click="removeLogoUpload" class="mt-1 text-xs text-danger-600 hover:underline">{{ __('admin.ui.remove_logo') }}</button>
                    @endif
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <x-button variant="ghost" wire:click="toggleCreateForm">{{ __('admin.ui.cancel') }}</x-button>
                    <x-button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="createBrand, logoUpload">
                        {{ __('admin.ui.save_brand') }}
                    </x-button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4 flex items-center gap-3">
        <div class="w-full max-w-xs">
            <x-input type="search" wire:model.live.debounce.400ms="search" :placeholder="__('admin.ui.search_brands')" :aria-label="__('admin.ui.search_brands_2')" class="h-8" />
        </div>
        @if ($search !== '')
            <button type="button" wire:click="$set('search', '')" class="text-xs text-text-secondary hover:text-text-primary">{{ __('admin.ui.clear') }}</button>
        @endif
    </div>

    <div class="overflow-hidden rounded-md border border-border-strong bg-surface-raised">
        <div class="relative overflow-x-auto">
            <table class="w-full min-w-[560px] border-collapse">
                <thead class="sticky top-0 z-(--z-sticky) bg-surface-subtle">
                    <tr class="border-b border-border text-left text-xs font-medium text-text-muted uppercase">
                        <th class="px-4 py-2.5"><span class="sr-only">{{ __('admin.ui.logo') }}</span></th>
                        <th class="px-4 py-2.5">
                            <button type="button" wire:click="sortBy('name')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                {{ __('admin.ui.name') }}
                                @if ($sortField === 'name')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5 text-right">{{ __('admin.ui.products') }}</th>
                        <th class="px-4 py-2.5">
                            <button type="button" wire:click="sortBy('created_at')" class="inline-flex items-center gap-1 hover:text-text-secondary">
                                {{ __('admin.ui.created') }}
                                @if ($sortField === 'created_at')
                                    <span>{{ $sortDirection === 'asc' ? '▲' : '▼' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-2.5"><span class="sr-only">{{ __('admin.ui.actions') }}</span></th>
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
                                                <x-input type="text" wire:model.blur="editingName" :aria-label="__('admin.ui.name')" :error="$errors->has('editingName')" autofocus />
                                                @error('editingName')
                                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                            <x-button type="submit" variant="primary" size="sm" wire:loading.attr="disabled" wire:target="updateBrand, editingLogoUpload">{{ __('admin.ui.save') }}</x-button>
                                            <x-button type="button" variant="ghost" size="sm" wire:click="cancelEdit">{{ __('admin.ui.cancel') }}</x-button>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            @if ($editingLogoUpload)
                                                <img src="{{ $editingLogoUpload->temporaryUrl() }}" alt="{{ __('admin.ui.new_logo_preview') }}" class="h-10 w-10 rounded-sm border border-border object-cover">
                                            @elseif ($editingExistingLogoPath)
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($editingExistingLogoPath) }}" alt="{{ __('admin.ui.current_logo') }}" class="h-10 w-10 rounded-sm border border-border object-cover">
                                            @else
                                                <span class="flex h-10 w-10 items-center justify-center rounded-sm border border-dashed border-border-strong text-xs text-text-muted">—</span>
                                            @endif

                                            <div>
                                                <input type="file" wire:model="editingLogoUpload" accept="image/jpeg,image/png" class="block text-xs text-text-secondary file:mr-2 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-2 file:py-1 file:text-xs file:font-medium file:text-text-primary hover:file:bg-surface-hover">
                                                <div wire:loading wire:target="editingLogoUpload" class="mt-1 text-xs text-text-muted">{{ __('admin.ui.uploading') }}</div>
                                                @error('editingLogoUpload')
                                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                                @enderror
                                                @if ($editingLogoUpload || $editingExistingLogoPath)
                                                    <button type="button" wire:click="removeEditingLogo" class="mt-1 text-xs text-danger-600 hover:underline">{{ __('admin.ui.remove_logo') }}</button>
                                                @endif
                                            </div>
                                        </div>
                                    </form>
                                </td>
                            @else
                                <td class="px-4 py-2">
                                    @if ($brand->logo_path)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brand->logo_path) }}" alt="{{ __('admin.ui.logo_of', ['name' => $brand->name]) }}" class="h-8 w-8 rounded-sm border border-border object-cover">
                                    @else
                                        <span class="flex h-8 w-8 items-center justify-center rounded-sm border border-dashed border-border-strong text-xs font-medium text-text-muted">
                                            {{ \Illuminate\Support\Str::of($brand->name)->substr(0, 1)->upper() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 font-medium">
                                    {{ $brand->name }}
                                    @if ($brand->name === 'No Brand')
                                        <span class="ml-1.5 text-xs font-normal text-text-muted">{{ __('admin.ui.default_can_t_be_deleted') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right font-mono tabular-nums">{{ $brand->products_count }}</td>
                                <td class="px-4 py-2 text-text-secondary">{{ $brand->created_at?->format('Y-m-d') }}</td>
                            @endif
                            <td class="px-4 py-2 text-right">
                                @if ($editingBrandId !== $brand->id)
                                    <x-menu :label="__('admin.ui.row_actions')">
<x-menu.item icon="edit" wire:click="startEdit({{ $brand->id }})">{{ __('admin.ui.edit') }}</x-menu.item>
                                        @if ($brand->name !== 'No Brand')
                                            <x-menu.item destructive wire:click="confirmDeleteBrand({{ $brand->id }})">{{ __('admin.ui.delete') }}</x-menu.item>
                                        @endif
                                    </x-menu>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center">
                                @if ($search !== '')
                                    <p class="text-sm font-medium text-text-primary">{{ __('admin.ui.no_results_match_your_filters') }}</p>
                                    <p class="mt-1 text-sm text-text-secondary">{{ __('admin.ui.try_a_different_search_term') }}</p>
                                    <button type="button" wire:click="$set('search', '')" class="mt-3 text-sm font-medium text-accent-700 hover:underline">{{ __('admin.ui.clear_filters') }}</button>
                                @else
                                    <p class="text-sm font-medium text-text-primary">{{ __('admin.ui.no_brands_yet') }}</p>
                                    <p class="mt-1 text-sm text-text-secondary">{{ __('admin.ui.add_the_first_brand_for_sellers_to_pick_from') }}</p>
                                    <x-button variant="primary" size="sm" wire:click="toggleCreateForm" class="mt-3">{{ __('admin.ui.add_brand') }}</x-button>
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
        <x-modal :title="__('admin.ui.delete_named', ['name' => $this->deletingBrand?->name])" close="cancelDelete">
            @if ($this->deleteAffectedProductCount > 0)
                <p class="mt-2 text-sm text-text-secondary">
                    {{ __('admin.ui.this_brand_can_t_be_deleted_while_products_still_reference_i') }}
                </p>
                <p class="mt-2 text-sm font-medium text-danger-600">
                    {{ __('admin.ui.used_by_reassign', ['products' => trans_choice('admin.ui.products_count', $this->deleteAffectedProductCount)]) }}
                </p>
            @else
                <p class="mt-2 text-sm text-text-secondary">
                    {{ __('admin.ui.this_can_t_be_undone') }}
                </p>
            @endif
            <x-slot:footer>
                <x-button variant="ghost" wire:click="cancelDelete">{{ __('admin.ui.cancel') }}</x-button>
                @if ($this->deleteAffectedProductCount === 0)
                    <x-button variant="danger-solid" wire:click="deleteBrand" wire:loading.attr="disabled" wire:target="deleteBrand">
                        {{ __('admin.ui.delete_brand') }}
                    </x-button>
                @endif
            </x-slot:footer>
        </x-modal>
    @endif
</div>
