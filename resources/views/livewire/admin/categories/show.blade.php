<div>
    <x-page-header
        :title="$category->name[$defaultLocale] ?? 'Category'"
        subtitle="Manage this category's parameters: the fields sellers fill in and buyers filter by."
        :back-url="route('admin.categories.index')"
        back-label="All categories"
    >
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.categories.index') }}" wire:navigate variant="ghost" class="hidden md:inline-flex">All categories</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <div class="sr-only" aria-live="polite">{{ $announcement }}</div>

    {{-- 1. Category details: collapsed on phones, summary row + Edit --}}
    <section x-data="{ open: window.matchMedia('(min-width: 48rem)').matches }" class="-mx-4 mb-6 border-y border-border-strong bg-surface md:mx-0 md:border-x">
        <header class="flex min-h-12 items-center justify-between gap-3 border-b border-border bg-surface-subtle px-4 py-2">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-text-primary">Category details</h2>
                <p class="flex items-center gap-2 text-xs text-text-muted md:hidden" x-show="! open">
                    <span class="truncate">{{ $category->name[$previewLocale ?: $defaultLocale] ?? '' }}</span>
                    <x-badge :variant="$category->is_active ? 'success' : 'muted'">{{ $category->is_active ? 'Active' : 'Inactive' }}</x-badge>
                </p>
            </div>
            <x-button class="md:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="category-details">
                <span x-text="open ? 'Close' : 'Edit'"></span>
            </x-button>
        </header>

        <form id="category-details" wire:submit="saveCategoryDetails" x-show="open" class="space-y-4 p-4 md:p-5">
            <x-locale-tabs :incomplete="$this->incompleteCategoryLocales">
                @foreach (config('ribbon.locales') as $locale)
                    <div x-show="locale === '{{ $locale }}'" x-cloak role="tabpanel" id="panel-{{ $locale }}" aria-labelledby="tab-{{ $locale }}">
                        <x-field label="Name" for="catName-{{ $locale }}" :required="true" :error="$errors->first('catName.'.$locale)">
                            <x-input id="catName-{{ $locale }}" type="text" wire:model.blur="catName.{{ $locale }}" :error="$errors->has('catName.'.$locale)" aria-invalid="{{ $errors->has('catName.'.$locale) ? 'true' : 'false' }}" />
                        </x-field>
                        <p class="mt-1.5 text-xs text-text-muted">Slug: <span class="font-mono">{{ $category->slug[$locale] ?? '—' }}</span></p>
                    </div>
                @endforeach
            </x-locale-tabs>

            <x-field label="Image" for="catImageUpload" hint="Optional. JPG or PNG, max 1MB." :error="$errors->first('catImageUpload')">
                <div class="flex items-center gap-3">
                    @if ($catImageUpload)
                        <img src="{{ $catImageUpload->temporaryUrl() }}" alt="New category image preview" class="size-16 border border-border object-cover">
                    @elseif ($catExistingImagePath)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($catExistingImagePath) }}" alt="Current category image" class="size-16 border border-border object-cover">
                    @else
                        <span class="flex size-16 items-center justify-center border border-dashed border-border-input text-xs text-text-muted">No image</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <x-file-input id="catImageUpload" wire:model="catImageUpload" accept="image/jpeg,image/png" />
                        <div wire:loading wire:target="catImageUpload" class="mt-1 text-xs text-text-muted">Uploading…</div>
                        @if ($catImageUpload || $catExistingImagePath)
                            <button type="button" wire:click="removeCategoryImage" class="inline-flex h-11 items-center text-sm font-medium text-danger-700 hover:underline">Remove image</button>
                        @endif
                    </div>
                </div>
            </x-field>

            {{-- Mega-menu icon (spec 22). Native radios: arrow keys move within the group. --}}
            <fieldset>
                <legend class="text-sm font-medium text-text-primary">Menu icon</legend>
                <p class="mt-0.5 text-xs text-text-muted">Shown next to the category in the storefront catalog menu.</p>
                <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-7">
                    @foreach (\App\Support\Icon::CATEGORY_ICONS as $iconName)
                        <label class="relative flex min-h-11 cursor-pointer flex-col items-center gap-1 rounded-sm border px-2 py-2 text-center text-xs has-checked:border-accent-700 has-checked:bg-accent-50 has-checked:text-accent-800 has-focus-visible:outline-2 has-focus-visible:outline-focus-ring border-border-input text-text-secondary hover:bg-surface-hover">
                            <input type="radio" name="catIcon" value="{{ $iconName }}" wire:model="catIcon" class="sr-only">
                            <x-icon :name="$iconName" class="size-6" />
                            <span class="w-full truncate">{{ $iconName }}</span>
                        </label>
                    @endforeach
                </div>
                @if ($errors->has('catIcon'))
                    <p class="mt-1 text-xs text-danger-700">{{ $errors->first('catIcon') }}</p>
                @endif
            </fieldset>

            <x-field label="Buying guide" for="catGuideArticleId" hint="Optional. A published article shown as “How to choose” in the catalog menu." :error="$errors->first('catGuideArticleId')">
                <x-select id="catGuideArticleId" wire:model="catGuideArticleId" :error="$errors->has('catGuideArticleId')">
                    <option value="">None</option>
                    @foreach ($this->guideOptions as $articleId => $articleTitle)
                        <option value="{{ $articleId }}">{{ $articleTitle }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <x-toggle wire:model="catIsActive" label="Active" caption="Inactive categories are hidden from the buyer catalog." />
            @can('super-admin')
                <x-toggle wire:model="catIsFree" :label="__('admin.categories.free_toggle')" :caption="__('admin.categories.free_caption')" />
            @endcan

            <div class="flex">
                <x-button type="submit" variant="primary" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="saveCategoryDetails, catImageUpload">Save details</x-button>
            </div>
        </form>
    </section>

    {{-- 2. Parameters: the primary content of this screen --}}
    <section class="-mx-4 border-y border-border-strong bg-surface md:mx-0 md:border-x">
        <header class="flex flex-col gap-3 border-b border-border bg-surface-subtle px-4 py-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text-primary">Parameters</h2>
                <p class="mt-0.5 text-xs text-text-muted">
                    {{ $this->parameterStats['total'] }} {{ \Illuminate\Support\Str::plural('parameter', $this->parameterStats['total']) }}
                    · {{ $this->parameterStats['required'] }} required
                    · {{ $this->parameterStats['filterable'] }} filterable
                </p>
            </div>

            <div class="flex flex-col gap-3 md:flex-row md:items-center">
                <div class="grid grid-cols-3 divide-x divide-border border border-border-input bg-surface md:w-36" role="group" aria-label="Preview language">
                    @foreach (config('ribbon.locales') as $locale)
                        <button type="button" wire:click="$set('previewLocale', '{{ $locale }}')" aria-pressed="{{ $previewLocale === $locale ? 'true' : 'false' }}"
                            class="h-control text-xs font-semibold {{ $previewLocale === $locale ? 'bg-accent-50 text-accent-700' : 'text-text-secondary hover:bg-surface-hover' }}">{{ strtoupper($locale) }}</button>
                    @endforeach
                </div>
                <x-button variant="primary" wire:click="openCreateDrawer" class="w-full md:w-auto">
                    <x-icon name="add" class="size-4" />
                    Add parameter
                </x-button>
            </div>
        </header>

        @if ($category->parameters->isEmpty())
            <x-empty-state title="This category has no parameters yet" description="Sellers won't be able to add meaningful specs until you add at least one.">
                <x-slot:actions><x-button variant="primary" wire:click="openCreateDrawer">Add parameter</x-button></x-slot:actions>
            </x-empty-state>
        @else
            <ul
                x-data="{ dragging: null }"
                class="divide-y divide-border"
            >
                @foreach ($category->parameters as $parameter)
                    <li
                        wire:key="parameter-{{ $parameter->id }}"
                        data-id="{{ $parameter->id }}"
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
                        class="flex items-stretch hover:bg-surface-hover"
                    >
                        {{-- Drag handle: pointer-fine only. Touch/keyboard use Move up / Move down in the row menu. --}}
                        <span
                            class="hidden w-10 shrink-0 cursor-grab items-center justify-center text-text-muted select-none dense:flex"
                            draggable="true"
                            aria-hidden="true"
                            x-on:dragstart="dragging = $el.parentElement.dataset.id; $el.parentElement.classList.add('opacity-40')"
                            x-on:dragend="$el.parentElement.classList.remove('opacity-40')"
                        >⠿</span>

                        <button type="button" wire:click="openEditDrawer({{ $parameter->id }})" class="min-w-0 flex-1 px-4 py-3 text-left dense:pl-0 md:flex md:items-center md:gap-4">
                            <span class="block truncate text-sm font-medium text-text-primary md:min-w-0 md:flex-1">{{ $parameter->name[$previewLocale] ?? '—' }}</span>
                            <span class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 md:mt-0 md:shrink-0">
                                <x-type-chip>{{ $typeLabels[$parameter->type] ?? $parameter->type }}</x-type-chip>
                                @if ($parameter->type === 'number' && $parameter->unit)
                                    <span class="font-mono text-xs text-text-secondary">{{ $parameter->unit }}</span>
                                @endif
                                @if (in_array($parameter->type, ['select_single', 'select_multiple']))
                                    <span class="font-mono text-xs text-text-muted">{{ $parameter->options->count() }} options</span>
                                @endif
                            </span>
                            <span class="mt-1.5 flex gap-4 text-xs text-text-secondary md:mt-0 md:shrink-0">
                                <span class="flex items-center gap-1.5"><span class="size-2.5 {{ $parameter->is_required ? 'bg-accent-700' : 'border border-border-input' }}"></span>Required<span class="sr-only">: {{ $parameter->is_required ? 'yes' : 'no' }}</span></span>
                                <span class="flex items-center gap-1.5"><span class="size-2.5 {{ $parameter->is_filterable ? 'bg-accent-700' : 'border border-border-input' }}"></span>Filterable<span class="sr-only">: {{ $parameter->is_filterable ? 'yes' : 'no' }}</span></span>
                            </span>
                        </button>

                        <div class="self-start pt-1 pr-1 md:self-center md:pt-0">
                            <x-menu :title="$parameter->name[$previewLocale] ?? 'Parameter'" label="Row actions">
                                <x-menu.item icon="edit" wire:click="openEditDrawer({{ $parameter->id }})">Edit</x-menu.item>
                                <x-menu.item icon="copy" wire:click="duplicateParameter({{ $parameter->id }})">Duplicate</x-menu.item>
                                <x-menu.item icon="arrow-up" wire:click="moveParameter({{ $parameter->id }}, 'up')" :disabled="$loop->first" reason="Already first">Move up</x-menu.item>
                                <x-menu.item icon="arrow-down" wire:click="moveParameter({{ $parameter->id }}, 'down')" :disabled="$loop->last" reason="Already last">Move down</x-menu.item>
                                <x-menu.item icon="delete" wire:click="confirmDeleteParameter({{ $parameter->id }})" :destructive="true">Delete</x-menu.item>
                            </x-menu>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Add/Edit Parameter drawer --}}
    @if ($showDrawer)
        <x-drawer :title="$editingParameterId ? 'Edit parameter: '.($paramName[$defaultLocale] ?? '') : 'New parameter'" close="closeDrawer">
            <form id="parameter-form" wire:submit="saveParameter" class="space-y-4">
                <x-locale-tabs :incomplete="$this->incompleteParameterLocales">
                    @foreach (config('ribbon.locales') as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak role="tabpanel" id="panel-{{ $locale }}" aria-labelledby="tab-{{ $locale }}" class="mb-4">
                            <x-field label="Name" for="paramName-{{ $locale }}" :required="true" :error="$errors->first('paramName.'.$locale)">
                                <x-input id="paramName-{{ $locale }}" type="text" wire:model.blur="paramName.{{ $locale }}" :error="$errors->has('paramName.'.$locale)" aria-invalid="{{ $errors->has('paramName.'.$locale) ? 'true' : 'false' }}" />
                            </x-field>
                        </div>
                    @endforeach

                    @php($typeLocked = $this->isTypeLocked($editingParameterId))
                    <div class="mb-4">
                        <x-field label="Type" for="paramType" :required="true" :hint="$typeLocked ? 'Type cannot be changed once sellers have entered values for this parameter.' : null">
                            <x-select id="paramType" wire:model="paramType" :disabled="$typeLocked">
                                @foreach ($typeLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                    </div>

                    <div x-show="$wire.paramType === 'number'" x-collapse class="mb-4">
                        <x-field label="Unit" for="paramUnit">
                            <x-input id="paramUnit" type="text" wire:model.blur="paramUnit" placeholder="e.g. mm, dpi, m/s" class="font-mono" />
                        </x-field>
                    </div>

                    <div class="mb-4 divide-y divide-border border-y border-border md:grid md:grid-cols-2 md:gap-4 md:divide-y-0 md:border-y-0">
                        <x-toggle wire:model="paramRequired" label="Required" caption="Sellers must fill this in to publish a product." />
                        <x-toggle wire:model="paramFilterable" label="Filterable" caption="Buyers can filter the catalog by this value." />
                    </div>

                    <div x-show="$wire.paramType === 'select_single' || $wire.paramType === 'select_multiple'" x-collapse>
                        <p class="mb-2 text-sm font-medium text-text-primary">Options</p>

                        @error('options')
                            <p class="mb-2 flex items-start gap-1.5 text-xs font-medium text-danger-700" role="alert">{{ $message }}</p>
                        @enderror

                        <ul class="divide-y divide-border border border-border-strong">
                            @foreach ($options as $index => $option)
                                <li wire:key="option-{{ $option['key'] }}" class="grid grid-cols-[minmax(0,1fr)_2.75rem] items-center gap-1 p-2">
                                    <div class="min-w-0">
                                        @foreach (config('ribbon.locales') as $locale)
                                            <div x-show="locale === '{{ $locale }}'" x-cloak>
                                                <x-input
                                                    type="text"
                                                    enterkeyhint="next"
                                                    wire:model.blur="options.{{ $index }}.value.{{ $locale }}"
                                                    wire:keydown.enter.prevent="addOption"
                                                    :error="$errors->has('options.'.$index.'.value.'.$locale)"
                                                    aria-label="Option {{ $index + 1 }} ({{ strtoupper($locale) }})"
                                                    placeholder="Option value"
                                                />
                                            </div>
                                        @endforeach
                                    </div>
                                    <x-menu title="Option {{ $index + 1 }}" label="Option actions">
                                        <x-menu.item icon="arrow-up" wire:click="moveOption('{{ $option['key'] }}', 'up')" :disabled="$loop->first" reason="Already first">Move up</x-menu.item>
                                        <x-menu.item icon="arrow-down" wire:click="moveOption('{{ $option['key'] }}', 'down')" :disabled="$loop->last" reason="Already last">Move down</x-menu.item>
                                        <x-menu.item icon="delete" wire:click="removeOption('{{ $option['key'] }}')" :destructive="true">Remove</x-menu.item>
                                    </x-menu>
                                </li>
                            @endforeach
                        </ul>

                        <button type="button" wire:click="addOption" class="mt-3 h-control w-full border border-dashed border-border-input text-sm font-medium text-accent-700 hover:bg-surface-hover">Add option</button>
                    </div>
                </x-locale-tabs>

                @if ($editingParameterId)
                    <section class="mt-6 border border-danger-200 md:hidden">
                        <h3 class="bg-danger-50 px-4 py-2 text-sm font-semibold text-danger-700">Danger zone</h3>
                        <div class="space-y-3 p-4">
                            <p class="text-sm text-text-secondary">Deleting removes this parameter and every value sellers entered for it.</p>
                            <x-button type="button" variant="danger-solid" class="w-full" wire:click="confirmDeleteParameter({{ $editingParameterId }})">Delete parameter</x-button>
                        </div>
                    </section>
                @endif
            </form>

            <x-slot:footer>
                @if ($editingParameterId)
                    <x-button type="button" variant="danger" class="mr-auto hidden md:inline-flex" wire:click="confirmDeleteParameter({{ $editingParameterId }})">Delete</x-button>
                @endif
                <div class="flex flex-1 gap-2 md:flex-none [&>*]:flex-1 md:[&>*]:flex-none">
                    <x-button type="button" variant="ghost" wire:click="closeDrawer">Cancel</x-button>
                    <x-button type="submit" form="parameter-form" variant="primary" wire:loading.attr="disabled" wire:target="saveParameter">Save parameter</x-button>
                </div>
            </x-slot:footer>
        </x-drawer>
    @endif

    {{-- Delete confirmation --}}
    @if ($showDeleteConfirm)
        <x-modal kind="confirm" :title="'Delete parameter “'.($this->deletingParameter?->name[$defaultLocale] ?? '').'”?'" close="cancelDelete">
            <p>This permanently removes it and any values sellers have entered for it on their products. This cannot be undone.</p>
            @if ($this->deleteAffectedProductCount > 0)
                <p class="mt-2 font-medium text-danger-700">
                    Used by <span class="font-mono">{{ $this->deleteAffectedProductCount }}</span> {{ \Illuminate\Support\Str::plural('product', $this->deleteAffectedProductCount) }}.
                </p>
            @endif
            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelDelete">Cancel</x-button>
                <x-button variant="danger-solid" wire:click="deleteParameter" wire:loading.attr="disabled" wire:target="deleteParameter">Delete parameter</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
