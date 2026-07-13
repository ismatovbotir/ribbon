<div>
    <x-page-header :title="__('sellers.products.create.title')" :subtitle="__('sellers.products.create.subtitle')">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('seller.products.index') }}" wire:navigate variant="ghost">
                {{ __('sellers.products.create.cancel_button') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-6">
        {{-- Category & Details --}}
        <div class="rounded-md border border-border bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('sellers.products.create.section_details') }}</h2>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.products.create.category_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-select wire:model.live="categoryId" :error="$errors->has('categoryId')">
                        <option value="">{{ __('sellers.products.create.category_placeholder') }}</option>
                        @foreach ($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name[app()->getLocale()] ?? $category->name[config('ribbon.locales')[0]] }}</option>
                        @endforeach
                    </x-select>
                    @error('categoryId')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.products.create.brand_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-select wire:model="brandId" :error="$errors->has('brandId')">
                        @foreach ($this->brands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                        @endforeach
                    </x-select>
                    @error('brandId')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-text-primary">{{ __('sellers.products.create.generated_name_label') }}</label>
                    <p class="flex min-h-9 items-center rounded-sm border border-border bg-surface-sunken px-3 py-1.5 font-mono text-sm text-text-secondary">
                        {{ $this->previewName !== '' ? $this->previewName : __('sellers.products.create.generated_name_empty') }}
                    </p>
                    <p class="mt-1 text-xs text-text-muted">{{ __('sellers.products.create.generated_name_caption') }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-text-primary">{{ __('sellers.products.create.name_extra_label') }}</label>
                    <x-input type="text" wire:model.blur="nameExtra" placeholder="{{ __('sellers.products.create.name_extra_placeholder') }}" :error="$errors->has('nameExtra')" />
                    <p class="mt-1 text-xs text-text-muted">{{ __('sellers.products.create.name_extra_caption') }}</p>
                    @error('nameExtra')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Specifications --}}
        <div class="rounded-md border border-border bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('sellers.products.create.section_specifications') }}</h2>
            </div>

            <div class="p-5">
                @if (! $this->selectedCategory)
                    <p class="text-sm text-text-secondary">{{ __('sellers.products.create.specifications_hint') }}</p>
                @elseif ($this->selectedCategory->parameters->isEmpty())
                    <p class="text-sm text-text-secondary">{{ __('sellers.products.no_parameters') }}</p>
                @else
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach ($this->selectedCategory->parameters as $parameter)
                            <div wire:key="create-param-{{ $parameter->id }}" class="{{ $parameter->type === 'select_multiple' ? 'sm:col-span-2' : '' }}">
                                @include('livewire.sellers.products.partials.parameter-field', ['parameter' => $parameter])
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Images (up to 4, attached once the product is created) --}}
        <div class="rounded-md border border-border bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('sellers.products.images.section_title') }}</h2>
            </div>

            <div class="p-5">
                <p class="mb-3 text-sm text-text-secondary">{{ __('sellers.products.images.caption') }}</p>

                <div class="flex flex-wrap gap-3">
                    @foreach ($stagedImages as $index => $staged)
                        <div wire:key="staged-image-{{ $staged['key'] }}" class="relative h-24 w-24 overflow-hidden rounded-md border border-border">
                            <img src="{{ $staged['file']->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                            @if ($index === 0)
                                <span class="absolute top-1 left-1 rounded-sm bg-accent-600 px-1.5 py-0.5 text-[10px] font-medium text-white">{{ __('sellers.products.images.primary_badge') }}</span>
                            @endif
                            <button
                                type="button"
                                wire:click="removeStagedImage('{{ $staged['key'] }}')"
                                class="absolute top-1 right-1 flex h-5 w-5 items-center justify-center rounded-full bg-slate-900/60 text-xs text-white hover:bg-slate-900/80"
                                aria-label="{{ __('sellers.products.images.remove') }}"
                            >
                                ✕
                            </button>
                        </div>
                    @endforeach

                    @if (count($stagedImages) < 4)
                        <label wire:key="staged-image-upload-slot" class="flex h-24 w-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed border-border-strong text-xs text-text-secondary hover:border-accent-300 hover:text-text-primary">
                            <span class="text-lg leading-none">+</span>
                            <span>{{ __('sellers.products.images.add') }}</span>
                            <input type="file" wire:model="newImageUpload" accept="image/jpeg,image/png" class="hidden">
                        </label>
                    @endif
                </div>

                <div wire:loading wire:target="newImageUpload" class="mt-2 text-xs text-text-muted">{{ __('sellers.uploading') }}</div>

                @error('newImageUpload')
                    <p class="mt-2 text-xs text-danger-600">{{ $message }}</p>
                @enderror

                <p class="mt-2 text-xs text-text-muted">{{ __('sellers.products.images.count_caption', ['count' => count($stagedImages)]) }}</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <x-button tag="a" href="{{ route('seller.products.index') }}" wire:navigate variant="ghost">
                {{ __('sellers.products.create.cancel_button') }}
            </x-button>
            <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save, newImageUpload">
                {{ __('sellers.products.create.submit_button') }}
            </x-button>
        </div>
    </form>
</div>
