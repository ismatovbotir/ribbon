<div>
    <x-page-header
        :title="$category->name[app()->getLocale()] ?? __('admin.ui.category')"
        :subtitle="__('admin.ui.manage_this_category_s_parameters_the_fields_sellers_fill_in')"
        :back-url="route('admin.categories.index')"
        :back-label="__('admin.ui.all_categories')"
    >
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.categories.index') }}" wire:navigate variant="ghost" class="hidden md:inline-flex">{{ __('admin.ui.all_categories') }}</x-button>
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
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.category_details') }}</h2>
                <p class="flex items-center gap-2 text-xs text-text-muted md:hidden" x-show="! open">
                    <span class="truncate">{{ $category->name[$previewLocale ?: $defaultLocale] ?? '' }}</span>
                    <x-badge :variant="$category->is_active ? 'success' : 'muted'">{{ $category->is_active ? __('admin.ui.active') : __('admin.ui.inactive') }}</x-badge>
                </p>
            </div>
            <x-button class="md:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="category-details">
                <span x-text="open ? @js(__('admin.ui.close')) : @js(__('admin.ui.edit'))"></span>
            </x-button>
        </header>

        <form id="category-details" wire:submit="saveCategoryDetails" x-show="open" class="space-y-4 p-4 md:p-5">
            <x-locale-tabs :incomplete="$this->incompleteCategoryLocales">
                @foreach (config('ribbon.locales') as $locale)
                    <div x-show="locale === '{{ $locale }}'" x-cloak role="tabpanel" id="panel-{{ $locale }}" aria-labelledby="tab-{{ $locale }}">
                        <x-field :label="__('admin.ui.name')" for="catName-{{ $locale }}" :required="true" :error="$errors->first('catName.'.$locale)">
                            <x-input id="catName-{{ $locale }}" type="text" wire:model.blur="catName.{{ $locale }}" :error="$errors->has('catName.'.$locale)" aria-invalid="{{ $errors->has('catName.'.$locale) ? 'true' : 'false' }}" />
                        </x-field>
                        <p class="mt-1.5 text-xs text-text-muted">{{ __('admin.ui.slug') }} <span class="font-mono">{{ $category->slug[$locale] ?? '—' }}</span></p>
                    </div>
                @endforeach
            </x-locale-tabs>

            <x-field :label="__('admin.ui.image')" for="catImageUpload" :hint="__('admin.ui.optional_jpg_or_png_max_1mb')" :error="$errors->first('catImageUpload')">
                <div class="flex items-center gap-3">
                    @if ($catImageUpload)
                        <img src="{{ $catImageUpload->temporaryUrl() }}" alt="{{ __('admin.ui.new_category_image_preview') }}" class="size-16 border border-border object-cover">
                    @elseif ($catExistingImagePath)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($catExistingImagePath) }}" alt="{{ __('admin.ui.current_category_image') }}" class="size-16 border border-border object-cover">
                    @else
                        <span class="flex size-16 items-center justify-center border border-dashed border-border-input text-xs text-text-muted">{{ __('admin.ui.no_image') }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <x-file-input id="catImageUpload" wire:model="catImageUpload" accept="image/jpeg,image/png" />
                        <div wire:loading wire:target="catImageUpload" class="mt-1 text-xs text-text-muted">{{ __('admin.ui.uploading') }}</div>
                        @if ($catImageUpload || $catExistingImagePath)
                            <button type="button" wire:click="removeCategoryImage" class="inline-flex h-11 items-center text-sm font-medium text-danger-700 hover:underline">{{ __('admin.ui.remove_image') }}</button>
                        @endif
                    </div>
                </div>
            </x-field>

            {{-- Mega-menu icon (spec 22). Native radios: arrow keys move within the group. --}}
            <fieldset>
                <legend class="text-sm font-medium text-text-primary">{{ __('admin.ui.menu_icon') }}</legend>
                <p class="mt-0.5 text-xs text-text-muted">{{ __('admin.ui.shown_next_to_the_category_in_the_storefront_catalog_menu') }}</p>
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

            <x-field :label="__('admin.ui.buying_guide')" for="catGuideArticleId" :hint="__('admin.ui.optional_a_published_article_shown_as_how_to_choose_in_the_c')" :error="$errors->first('catGuideArticleId')">
                <x-select id="catGuideArticleId" wire:model="catGuideArticleId" :error="$errors->has('catGuideArticleId')">
                    <option value="">{{ __('admin.ui.none') }}</option>
                    @foreach ($this->guideOptions as $articleId => $articleTitle)
                        <option value="{{ $articleId }}">{{ $articleTitle }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <x-toggle wire:model="catIsActive" :label="__('admin.ui.active')" :caption="__('admin.ui.inactive_categories_are_hidden_from_the_buyer_catalog')" />
            @can('super-admin')
                <x-toggle wire:model="catIsFree" :label="__('admin.categories.free_toggle')" :caption="__('admin.categories.free_caption')" />
            @endcan

            <div class="flex">
                <x-button type="submit" variant="primary" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="saveCategoryDetails, catImageUpload">{{ __('admin.ui.save_details') }}</x-button>
            </div>
        </form>
    </section>

    {{-- 2. Parameters: the primary content of this screen --}}
    <section class="-mx-4 border-y border-border-strong bg-surface md:mx-0 md:border-x">
        <header class="flex flex-col gap-3 border-b border-border bg-surface-subtle px-4 py-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.parameters') }}</h2>
                <p class="mt-0.5 text-xs text-text-muted">
                    {{ trans_choice('admin.ui.parameters_count', $this->parameterStats['total']) }}
                    · {{ __('admin.ui.required_count', ['count' => $this->parameterStats['required']]) }}
                    · {{ __('admin.ui.filterable_count', ['count' => $this->parameterStats['filterable']]) }}
                </p>
            </div>

            <div class="flex flex-col gap-3 md:flex-row md:items-center">
                <div class="grid grid-cols-3 divide-x divide-border border border-border-input bg-surface md:w-36" role="group" aria-label="{{ __('admin.ui.preview_language') }}">
                    @foreach (config('ribbon.locales') as $locale)
                        <button type="button" wire:click="$set('previewLocale', '{{ $locale }}')" aria-pressed="{{ $previewLocale === $locale ? 'true' : 'false' }}"
                            class="h-control text-xs font-semibold {{ $previewLocale === $locale ? 'bg-accent-50 text-accent-700' : 'text-text-secondary hover:bg-surface-hover' }}">{{ strtoupper($locale) }}</button>
                    @endforeach
                </div>
                <x-button variant="primary" wire:click="openCreateDrawer" class="w-full md:w-auto">
                    <x-icon name="add" class="size-4" />
                    {{ __('admin.ui.add_parameter') }}
                </x-button>
            </div>
        </header>

        @if ($category->parameters->isEmpty())
            <x-empty-state :title="__('admin.ui.this_category_has_no_parameters_yet')" :description="__('admin.ui.empty_parameters')">
                <x-slot:actions><x-button variant="primary" wire:click="openCreateDrawer">{{ __('admin.ui.add_parameter') }}</x-button></x-slot:actions>
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
                                    <span class="font-mono text-xs text-text-muted">{{ trans_choice('admin.ui.options_count', $parameter->options->count()) }}</span>
                                @endif
                            </span>
                            <span class="mt-1.5 flex gap-4 text-xs text-text-secondary md:mt-0 md:shrink-0">
                                <span class="flex items-center gap-1.5"><span class="size-2.5 {{ $parameter->is_required ? 'bg-accent-700' : 'border border-border-input' }}"></span>{{ __('admin.ui.required') }}<span class="sr-only">: {{ $parameter->is_required ? __('admin.ui.yes') : __('admin.ui.no') }}</span></span>
                                <span class="flex items-center gap-1.5"><span class="size-2.5 {{ $parameter->is_filterable ? 'bg-accent-700' : 'border border-border-input' }}"></span>{{ __('admin.ui.filterable') }}<span class="sr-only">: {{ $parameter->is_filterable ? __('admin.ui.yes') : __('admin.ui.no') }}</span></span>
                            </span>
                        </button>

                        <div class="self-start pt-1 pr-1 md:self-center md:pt-0">
                            <x-menu :title="$parameter->name[$previewLocale] ?? 'Parameter'" :label="__('admin.ui.row_actions')">
                                <x-menu.item icon="edit" wire:click="openEditDrawer({{ $parameter->id }})">{{ __('admin.ui.edit') }}</x-menu.item>
                                <x-menu.item icon="copy" wire:click="duplicateParameter({{ $parameter->id }})">{{ __('admin.ui.duplicate') }}</x-menu.item>
                                <x-menu.item icon="arrow-up" wire:click="moveParameter({{ $parameter->id }}, 'up')" :disabled="$loop->first" :reason="__('admin.ui.already_first')">{{ __('admin.ui.move_up') }}</x-menu.item>
                                <x-menu.item icon="arrow-down" wire:click="moveParameter({{ $parameter->id }}, 'down')" :disabled="$loop->last" :reason="__('admin.ui.already_last')">{{ __('admin.ui.move_down') }}</x-menu.item>
                                <x-menu.item icon="delete" wire:click="confirmDeleteParameter({{ $parameter->id }})" :destructive="true">{{ __('admin.ui.delete') }}</x-menu.item>
                            </x-menu>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Add/Edit Parameter drawer --}}
    @if ($showDrawer)
        <x-drawer :title="$editingParameterId ? __('admin.ui.edit_parameter', ['name' => $paramName[app()->getLocale()] ?? '']) : __('admin.ui.new_parameter')" close="closeDrawer">
            <form id="parameter-form" wire:submit="saveParameter" class="space-y-4">
                <x-locale-tabs :incomplete="$this->incompleteParameterLocales">
                    @foreach (config('ribbon.locales') as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak role="tabpanel" id="panel-{{ $locale }}" aria-labelledby="tab-{{ $locale }}" class="mb-4">
                            <x-field :label="__('admin.ui.name')" for="paramName-{{ $locale }}" :required="true" :error="$errors->first('paramName.'.$locale)">
                                <x-input id="paramName-{{ $locale }}" type="text" wire:model.blur="paramName.{{ $locale }}" :error="$errors->has('paramName.'.$locale)" aria-invalid="{{ $errors->has('paramName.'.$locale) ? 'true' : 'false' }}" />
                            </x-field>
                        </div>
                    @endforeach

                    @php($typeLocked = $this->isTypeLocked($editingParameterId))
                    <div class="mb-4">
                        <x-field :label="__('admin.ui.type')" for="paramType" :required="true" :hint="$typeLocked ? 'Type cannot be changed once sellers have entered values for this parameter.' : null">
                            <x-select id="paramType" wire:model="paramType" :disabled="$typeLocked">
                                @foreach ($typeLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                    </div>

                    <div x-show="$wire.paramType === 'number'" x-collapse class="mb-4">
                        <x-field :label="__('admin.ui.unit')" for="paramUnit">
                            <x-input id="paramUnit" type="text" wire:model.blur="paramUnit" :placeholder="__('admin.ui.e_g_mm_dpi_m_s')" class="font-mono" />
                        </x-field>
                    </div>

                    <div class="mb-4 divide-y divide-border border-y border-border md:grid md:grid-cols-2 md:gap-4 md:divide-y-0 md:border-y-0">
                        <x-toggle wire:model="paramRequired" :label="__('admin.ui.required')" :caption="__('admin.ui.sellers_must_fill_this_in_to_publish_a_product')" />
                        <x-toggle wire:model="paramFilterable" :label="__('admin.ui.filterable')" :caption="__('admin.ui.buyers_can_filter_the_catalog_by_this_value')" />
                    </div>

                    <div x-show="$wire.paramType === 'select_single' || $wire.paramType === 'select_multiple'" x-collapse>
                        <p class="mb-2 text-sm font-medium text-text-primary">{{ __('admin.ui.options') }}</p>

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
                                                    aria-label="{{ __('admin.ui.option_n', ['n' => $index + 1]) }} ({{ strtoupper($locale) }})"
                                                    :placeholder="__('admin.ui.option_value')"
                                                />
                                            </div>
                                        @endforeach
                                    </div>
                                    <x-menu :title="__('admin.ui.option_n', ['n' => $index + 1])" :label="__('admin.ui.option_actions')">
                                        <x-menu.item icon="arrow-up" wire:click="moveOption('{{ $option['key'] }}', 'up')" :disabled="$loop->first" :reason="__('admin.ui.already_first')">{{ __('admin.ui.move_up') }}</x-menu.item>
                                        <x-menu.item icon="arrow-down" wire:click="moveOption('{{ $option['key'] }}', 'down')" :disabled="$loop->last" :reason="__('admin.ui.already_last')">{{ __('admin.ui.move_down') }}</x-menu.item>
                                        <x-menu.item icon="delete" wire:click="removeOption('{{ $option['key'] }}')" :destructive="true">{{ __('admin.ui.remove') }}</x-menu.item>
                                    </x-menu>
                                </li>
                            @endforeach
                        </ul>

                        <button type="button" wire:click="addOption" class="mt-3 h-control w-full border border-dashed border-border-input text-sm font-medium text-accent-700 hover:bg-surface-hover">{{ __('admin.ui.add_option') }}</button>
                    </div>
                </x-locale-tabs>

                @if ($editingParameterId)
                    <section class="mt-6 border border-danger-200 md:hidden">
                        <h3 class="bg-danger-50 px-4 py-2 text-sm font-semibold text-danger-700">{{ __('admin.ui.danger_zone') }}</h3>
                        <div class="space-y-3 p-4">
                            <p class="text-sm text-text-secondary">{{ __('admin.ui.deleting_removes_this_parameter_and_every_value_sellers_ente') }}</p>
                            <x-button type="button" variant="danger-solid" class="w-full" wire:click="confirmDeleteParameter({{ $editingParameterId }})">{{ __('admin.ui.delete_parameter') }}</x-button>
                        </div>
                    </section>
                @endif
            </form>

            <x-slot:footer>
                @if ($editingParameterId)
                    <x-button type="button" variant="danger" class="mr-auto hidden md:inline-flex" wire:click="confirmDeleteParameter({{ $editingParameterId }})">{{ __('admin.ui.delete') }}</x-button>
                @endif
                <div class="flex flex-1 gap-2 md:flex-none [&>*]:flex-1 md:[&>*]:flex-none">
                    <x-button type="button" variant="ghost" wire:click="closeDrawer">{{ __('admin.ui.cancel') }}</x-button>
                    <x-button type="submit" form="parameter-form" variant="primary" wire:loading.attr="disabled" wire:target="saveParameter">{{ __('admin.ui.save_parameter') }}</x-button>
                </div>
            </x-slot:footer>
        </x-drawer>
    @endif

    {{-- Delete confirmation --}}
    @if ($showDeleteConfirm)
        <x-modal kind="confirm" :title="__('admin.ui.delete_named', ['name' => $this->deletingParameter?->name[app()->getLocale()] ?? ''])" close="cancelDelete">
            <p>{{ __('admin.ui.this_permanently_removes_it_and_any_values_sellers_have_ente') }}</p>
            @if ($this->deleteAffectedProductCount > 0)
                <p class="mt-2 font-medium text-danger-700">
                    {{ __('admin.ui.used_by') }} <span class="font-mono">{{ $this->deleteAffectedProductCount }}</span> {{ \Illuminate\Support\Str::plural('product', $this->deleteAffectedProductCount) }}.
                </p>
            @endif
            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelDelete">{{ __('admin.ui.cancel') }}</x-button>
                <x-button variant="danger-solid" wire:click="deleteParameter" wire:loading.attr="disabled" wire:target="deleteParameter">{{ __('admin.ui.delete_parameter') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
