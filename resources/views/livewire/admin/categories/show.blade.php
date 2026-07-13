<div>
    <x-page-header
        :title="$category->name[$defaultLocale] ?? 'Category'"
        subtitle="Manage this category's parameters — the fields sellers fill in and buyers filter by."
    >
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.categories.index') }}" wire:navigate variant="ghost">
                ← All categories
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    {{-- 1. Category details — understated relative to the parameter list below --}}
    <div class="mb-6 rounded-md bg-surface-subtle p-5">
        <h2 class="mb-4 text-sm font-semibold text-text-secondary uppercase tracking-wide">Category details</h2>

        <form wire:submit="saveCategoryDetails">
            <x-locale-tabs :incomplete="$this->incompleteCategoryLocales">
                @foreach (config('ribbon.locales') as $locale)
                    <div x-show="locale === '{{ $locale }}'" x-cloak>
                        <label class="mb-1 block text-sm font-medium text-text-primary">
                            Name <span class="text-danger-600">*</span>
                        </label>
                        <x-input type="text" wire:model.blur="catName.{{ $locale }}" :error="$errors->has('catName.'.$locale)" />
                        @error('catName.'.$locale)
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-text-muted">
                            Slug: <span class="font-mono">{{ $category->slug[$locale] ?? '—' }}</span>
                        </p>
                    </div>
                @endforeach
            </x-locale-tabs>

            <div class="mt-4 border-t border-border/60 pt-4">
                <label class="mb-1 block text-sm font-medium text-text-primary">Image</label>

                <div class="flex items-center gap-3">
                    @if ($catImageUpload)
                        <img src="{{ $catImageUpload->temporaryUrl() }}" alt="New category image preview" class="h-16 w-16 rounded-sm border border-border object-cover">
                    @elseif ($catExistingImagePath)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($catExistingImagePath) }}" alt="Current category image" class="h-16 w-16 rounded-sm border border-border object-cover">
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-sm border border-dashed border-border-strong text-xs text-text-muted">No image</span>
                    @endif

                    <div>
                        <input type="file" wire:model="catImageUpload" accept="image/jpeg,image/png" class="block text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">
                        <div wire:loading wire:target="catImageUpload" class="mt-1 text-xs text-text-muted">Uploading…</div>
                        @error('catImageUpload')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-text-muted">Optional. JPG or PNG, max 1MB.</p>
                        @if ($catImageUpload || $catExistingImagePath)
                            <button type="button" wire:click="removeCategoryImage" class="mt-1 text-xs text-danger-600 hover:underline">Remove image</button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-border/60 pt-4">
                <x-toggle wire:model="catIsActive" label="Active" caption="Inactive categories are hidden from the buyer catalog." />
                <x-button type="submit" variant="secondary" size="sm" wire:loading.attr="disabled" wire:target="saveCategoryDetails, catImageUpload">
                    Save details
                </x-button>
            </div>
        </form>
    </div>

    {{-- 2. Parameters — the primary content of this screen --}}
    <div class="rounded-md border border-border-strong bg-surface-raised">
        <div class="flex flex-col gap-3 border-b border-border px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text-primary">Parameters</h2>
                <p class="mt-0.5 text-xs text-text-muted">
                    {{ $this->parameterStats['total'] }} {{ \Illuminate\Support\Str::plural('parameter', $this->parameterStats['total']) }}
                    · {{ $this->parameterStats['required'] }} required
                    · {{ $this->parameterStats['filterable'] }} filterable
                </p>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1 rounded-sm border border-border p-0.5 text-xs">
                    @foreach (config('ribbon.locales') as $locale)
                        <button
                            type="button"
                            wire:click="$set('previewLocale', '{{ $locale }}')"
                            title="Preview language: {{ strtoupper($locale) }}"
                            class="rounded-sm px-2 py-1 font-medium transition-colors {{ $previewLocale === $locale ? 'bg-accent-50 text-accent-700' : 'text-text-muted hover:text-text-primary' }}"
                        >
                            {{ strtoupper($locale) }}
                        </button>
                    @endforeach
                </div>
                <x-button variant="primary" size="sm" wire:click="openCreateDrawer">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>
                    Add Parameter
                </x-button>
            </div>
        </div>

        @if ($category->parameters->isEmpty())
            <div class="px-5 py-16 text-center">
                <p class="text-sm font-medium text-text-primary">This category has no parameters yet</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-text-secondary">
                    Sellers won't be able to add meaningful specs until you add at least one.
                </p>
                <x-button variant="primary" size="sm" wire:click="openCreateDrawer" class="mt-4">Add Parameter</x-button>
            </div>
        @else
            <ul
                x-data="{ dragging: null }"
                class="divide-y divide-border"
            >
                @foreach ($category->parameters as $parameter)
                    <li
                        wire:key="parameter-{{ $parameter->id }}"
                        data-id="{{ $parameter->id }}"
                        draggable="true"
                        x-on:dragstart="dragging = $event.currentTarget.dataset.id; $event.currentTarget.classList.add('opacity-40')"
                        x-on:dragend="$event.currentTarget.classList.remove('opacity-40')"
                        x-on:dragover.prevent
                        x-on:drop.prevent="
                            let ids = Array.from($el.parentElement.children).map(li => li.dataset.id);
                            let from = ids.indexOf(dragging);
                            let to = ids.indexOf($event.currentTarget.dataset.id);
                            if (from !== -1 && to !== -1 && from !== to) {
                                ids.splice(to, 0, ids.splice(from, 1)[0]);
                                $wire.reorderParameters(ids);
                            }
                        "
                        wire:click="openEditDrawer({{ $parameter->id }})"
                        class="flex h-row-comfortable cursor-pointer items-center gap-3 px-5 text-sm hover:bg-surface-hover"
                    >
                        <span
                            class="shrink-0 cursor-grab text-text-muted select-none"
                            title="Drag to reorder"
                            x-on:click.stop
                        >⠿</span>

                        <span class="min-w-0 flex-1 truncate font-medium text-text-primary">
                            {{ $parameter->name[$previewLocale] ?? '—' }}
                        </span>

                        <x-type-chip>{{ $typeLabels[$parameter->type] ?? $parameter->type }}</x-type-chip>

                        @if ($parameter->type === 'number' && $parameter->unit)
                            <span class="shrink-0 text-xs text-text-secondary">{{ $parameter->unit }}</span>
                        @endif

                        <span class="flex shrink-0 items-center gap-1 text-xs text-text-secondary">
                            <span class="h-1.5 w-1.5 rounded-full {{ $parameter->is_required ? 'bg-accent-600' : 'border border-border' }}"></span>
                            Required
                        </span>
                        <span class="flex shrink-0 items-center gap-1 text-xs text-text-secondary">
                            <span class="h-1.5 w-1.5 rounded-full {{ $parameter->is_filterable ? 'bg-accent-600' : 'border border-border' }}"></span>
                            Filterable
                        </span>

                        @if (in_array($parameter->type, ['select_single', 'select_multiple']))
                            <span class="shrink-0 text-xs text-text-muted">{{ $parameter->options->count() }} options</span>
                        @endif

                        <x-dropdown align="right">
                            <x-slot:trigger>
                                <button type="button" x-on:click.stop class="rounded-sm p-1.5 text-text-muted hover:bg-surface-selected hover:text-text-primary" aria-label="Row actions">
                                    ⋯
                                </button>
                            </x-slot:trigger>

                            <button type="button" wire:click="openEditDrawer({{ $parameter->id }})" x-on:click.stop class="block w-full px-3 py-1.5 text-left text-sm text-text-primary hover:bg-surface-hover">
                                Edit
                            </button>
                            <button type="button" wire:click="duplicateParameter({{ $parameter->id }})" x-on:click.stop class="block w-full px-3 py-1.5 text-left text-sm text-text-primary hover:bg-surface-hover">
                                Duplicate
                            </button>
                            <button type="button" wire:click="confirmDeleteParameter({{ $parameter->id }})" x-on:click.stop class="block w-full px-3 py-1.5 text-left text-sm text-danger-600 hover:bg-danger-50">
                                Delete
                            </button>
                        </x-dropdown>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Add/Edit Parameter drawer --}}
    @if ($showDrawer)
        <div class="fixed inset-0 z-modal-backdrop bg-slate-900/40" wire:click="closeDrawer"></div>

        <div class="fixed inset-y-0 right-0 z-modal flex w-full max-w-[28rem] flex-col border-l border-border bg-surface-overlay shadow-md">
            <div class="flex items-center justify-between border-b border-border px-5 py-4">
                <h2 class="text-lg font-semibold text-text-primary">
                    {{ $editingParameterId ? 'Edit Parameter: '.($paramName[$defaultLocale] ?? '') : 'New Parameter' }}
                </h2>
                <button type="button" wire:click="closeDrawer" class="text-text-muted hover:text-text-primary" aria-label="Close">✕</button>
            </div>

            <form wire:submit="saveParameter" class="flex flex-1 flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto px-5 py-5">
                    <x-locale-tabs :incomplete="$this->incompleteParameterLocales">
                        {{-- Name --}}
                        @foreach (config('ribbon.locales') as $locale)
                            <div x-show="locale === '{{ $locale }}'" x-cloak class="mb-5">
                                <label class="mb-1 block text-sm font-medium text-text-primary">
                                    Name <span class="text-danger-600">*</span>
                                </label>
                                <x-input type="text" wire:model.blur="paramName.{{ $locale }}" :error="$errors->has('paramName.'.$locale)" />
                                @error('paramName.'.$locale)
                                    <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach

                        {{-- Type --}}
                        @php($typeLocked = $this->isTypeLocked($editingParameterId))
                        <div class="mb-5">
                            <label class="mb-1 block text-sm font-medium text-text-primary">Type <span class="text-danger-600">*</span></label>
                            <x-select wire:model="paramType" :disabled="$typeLocked">
                                @foreach ($typeLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-select>
                            @if ($typeLocked)
                                <p class="mt-1 text-xs text-text-muted">Type can't be changed once sellers have entered values for this parameter.</p>
                            @endif
                        </div>

                        {{-- Unit (Number only) --}}
                        <div x-show="$wire.paramType === 'number'" x-transition class="mb-5">
                            <label class="mb-1 block text-sm font-medium text-text-primary">Unit</label>
                            <x-input type="text" wire:model.blur="paramUnit" placeholder="e.g. mm, dpi, m/s" />
                        </div>

                        {{-- Required / Filterable --}}
                        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-toggle wire:model="paramRequired" label="Required" caption="Sellers must fill this in to publish a product." />
                            <x-toggle wire:model="paramFilterable" label="Filterable" caption="Buyers can filter the catalog by this value." />
                        </div>

                        {{-- Options (select types only) --}}
                        <div x-show="$wire.paramType === 'select_single' || $wire.paramType === 'select_multiple'" x-transition>
                            <div class="mb-2 flex items-center justify-between">
                                <label class="text-sm font-medium text-text-primary">Options</label>
                            </div>

                            @error('options')
                                <p class="mb-2 text-xs text-danger-600">{{ $message }}</p>
                            @enderror

                            <div class="space-y-2">
                                @foreach ($options as $index => $option)
                                    <div wire:key="option-{{ $option['key'] }}" class="flex items-center gap-2">
                                        <span class="shrink-0 cursor-grab text-text-muted select-none" title="Reorder by dragging (drag support: parameters list only in this pass)">⠿</span>
                                        <div class="min-w-0 flex-1">
                                            @foreach (config('ribbon.locales') as $locale)
                                                <div x-show="locale === '{{ $locale }}'" x-cloak>
                                                    <x-input
                                                        type="text"
                                                        wire:model.blur="options.{{ $index }}.value.{{ $locale }}"
                                                        :error="$errors->has('options.'.$index.'.value.'.$locale)"
                                                        placeholder="Option value"
                                                    />
                                                </div>
                                            @endforeach
                                        </div>
                                        <button type="button" wire:click="removeOption('{{ $option['key'] }}')" class="shrink-0 rounded-sm p-1.5 text-text-muted hover:bg-danger-50 hover:text-danger-600" aria-label="Remove option">
                                            ✕
                                        </button>
                                    </div>
                                @endforeach
                            </div>

                            <button type="button" wire:click="addOption" class="mt-3 text-sm font-medium text-accent-700 hover:underline">
                                + Add option
                            </button>
                        </div>
                    </x-locale-tabs>
                </div>

                <div class="flex items-center justify-between border-t border-border px-5 py-4">
                    <div>
                        @if ($editingParameterId)
                            <x-button type="button" variant="danger" size="sm" wire:click="confirmDeleteParameter({{ $editingParameterId }})">
                                Delete
                            </x-button>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <x-button type="button" variant="ghost" wire:click="closeDrawer">Cancel</x-button>
                        <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveParameter">
                            Save Parameter
                        </x-button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    {{-- Delete confirmation modal --}}
    @if ($showDeleteConfirm)
        <div class="fixed inset-0 z-modal-backdrop bg-slate-900/40" wire:click="cancelDelete"></div>
        <div class="fixed inset-0 z-modal flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface-overlay p-5 shadow-lg">
                <h2 class="text-lg font-semibold text-text-primary">
                    Delete parameter "{{ $this->deletingParameter?->name[$defaultLocale] ?? '' }}"?
                </h2>
                <p class="mt-2 text-sm text-text-secondary">
                    This permanently removes it and any values sellers have entered for it on their products. This can't be undone.
                </p>
                @if ($this->deleteAffectedProductCount > 0)
                    <p class="mt-2 text-sm font-medium text-danger-600">
                        Used by {{ $this->deleteAffectedProductCount }} {{ \Illuminate\Support\Str::plural('product', $this->deleteAffectedProductCount) }}.
                    </p>
                @endif

                <div class="mt-5 flex items-center justify-end gap-2">
                    <x-button variant="ghost" wire:click="cancelDelete">Cancel</x-button>
                    <x-button variant="danger-solid" wire:click="deleteParameter" wire:loading.attr="disabled" wire:target="deleteParameter">
                        Delete parameter
                    </x-button>
                </div>
            </div>
        </div>
    @endif
</div>
