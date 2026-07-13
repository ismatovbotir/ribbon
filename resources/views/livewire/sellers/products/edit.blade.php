@php
    $locale = app()->getLocale();
    $fallbackLocale = config('ribbon.locales')[0];
    $category = $product->category;
    $categoryName = $category->name[$locale] ?? $category->name[$fallbackLocale] ?? '';
    $statusVariant = match ($product->status) {
        'approved' => 'success',
        'pending' => 'warning',
        'rejected' => 'danger',
        'suspended' => 'muted',
        default => 'muted',
    };
@endphp

<div>
    <x-page-header :title="$product->name ?: $categoryName" :subtitle="__('sellers.products.edit.subtitle')">
        <x-slot:actions>
            <x-badge :variant="$statusVariant" dot>{{ __('sellers.status.'.$product->status) }}</x-badge>
            <x-button tag="a" href="{{ route('seller.products.index') }}" wire:navigate variant="ghost">
                ← {{ __('sellers.nav.products') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($product->status === 'pending')
        <div class="mb-6 rounded-md border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
            {{ __('sellers.products.edit.pending_notice') }}
        </div>
    @elseif ($product->status === 'rejected' && $product->rejection_reason)
        <div class="mb-6 rounded-md border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
            {{ __('sellers.products.edit.rejected_notice', ['reason' => $product->rejection_reason]) }}
        </div>
    @elseif ($product->status === 'suspended')
        <div class="mb-6 rounded-md border border-border-strong bg-surface-subtle p-3 text-sm text-text-secondary">
            {{ __('sellers.products.edit.suspended_notice') }}
        </div>
    @endif

    <div class="space-y-6">
        {{-- 1. Category & Details --}}
        <div class="rounded-md border border-border bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('sellers.products.edit.section_details') }}</h2>
            </div>
            <form wire:submit="saveDetails" class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">{{ __('sellers.products.edit.category_label') }}</label>
                    <p class="flex h-9 items-center rounded-sm border border-border bg-surface-sunken px-3 text-base text-text-secondary">
                        {{ $categoryName }}
                    </p>
                    <p class="mt-1 text-xs text-text-muted">{{ __('sellers.products.edit.category_locked_caption') }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.products.edit.brand_label') }} <span class="text-danger-600">*</span>
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
                    <label class="mb-1 block text-sm font-medium text-text-primary">{{ __('sellers.products.edit.generated_name_label') }}</label>
                    <p class="flex min-h-9 items-center rounded-sm border border-border bg-surface-sunken px-3 py-1.5 font-mono text-sm text-text-secondary">
                        {{ $this->previewName !== '' ? $this->previewName : __('sellers.products.edit.generated_name_empty') }}
                    </p>
                    <p class="mt-1 text-xs text-text-muted">{{ __('sellers.products.edit.generated_name_caption') }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-text-primary">{{ __('sellers.products.edit.name_extra_label') }}</label>
                    <x-input type="text" wire:model.blur="nameExtra" placeholder="{{ __('sellers.products.create.name_extra_placeholder') }}" :error="$errors->has('nameExtra')" />
                    <p class="mt-1 text-xs text-text-muted">{{ __('sellers.products.edit.name_extra_caption') }}</p>
                    @error('nameExtra')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end sm:col-span-2">
                    <x-button type="submit" variant="secondary" size="sm" wire:loading.attr="disabled" wire:target="saveDetails">
                        {{ __('sellers.products.edit.save_details') }}
                    </x-button>
                </div>
            </form>
        </div>

        {{-- 2. Specifications --}}
        <div class="rounded-md border border-border bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('sellers.products.edit.section_specifications') }}</h2>
            </div>
            <form wire:submit="saveSpecifications" class="p-5">
                @if ($category->parameters->isEmpty())
                    <p class="text-sm text-text-secondary">{{ __('sellers.products.no_parameters') }}</p>
                @else
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach ($category->parameters as $parameter)
                            <div wire:key="edit-param-{{ $parameter->id }}" class="{{ $parameter->type === 'select_multiple' ? 'sm:col-span-2' : '' }}">
                                @include('livewire.sellers.products.partials.parameter-field', ['parameter' => $parameter])
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex justify-end">
                        <x-button type="submit" variant="secondary" size="sm" wire:loading.attr="disabled" wire:target="saveSpecifications">
                            {{ __('sellers.products.edit.save_specifications') }}
                        </x-button>
                    </div>
                @endif
            </form>
        </div>

        {{-- 3. Pricing --}}
        <div class="rounded-md border border-border bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('sellers.products.edit.section_pricing') }}</h2>
            </div>

            <div class="p-5">
                <p class="mb-4 text-sm text-text-secondary">
                    {{ __('sellers.products.edit.pricing.buyers_see') }}
                    @if ($this->vitrinPrice)
                        <span class="font-semibold text-text-primary">
                            {{ number_format((float) $this->vitrinPrice->price) }} UZS / {{ __('sellers.products.unit.'.$this->vitrinPrice->unit) }}
                        </span>
                    @else
                        <span class="font-semibold text-text-primary">—</span>
                    @endif
                </p>

                <div class="space-y-3">
                    @foreach (['pcs', 'pack', 'box'] as $unit)
                        @php($row = $this->pricesByUnit[$unit] ?? null)

                        @if ($row)
                            <div wire:key="price-row-{{ $unit }}-enabled" class="rounded-md border border-border bg-surface-raised p-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-lg font-semibold text-text-primary">{{ __('sellers.products.unit.'.$unit) }}</span>
                                    @if ($row->is_vitrin)
                                        <x-badge variant="info" dot>{{ __('sellers.products.edit.pricing.vitrin_badge') }}</x-badge>
                                    @endif
                                </div>

                                <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                                    @if ($unit === 'pcs')
                                        <p class="pt-2 text-sm text-text-secondary">{{ __('sellers.products.edit.pricing.pcs_fixed') }}</p>
                                    @else
                                        <div>
                                            <div class="flex items-center gap-2 text-sm text-text-secondary">
                                                <span>{{ __('sellers.products.edit.pricing.qty_prefix', ['unit' => __('sellers.products.unit.'.$unit)]) }}</span>
                                                <x-input type="number" min="1" step="1" wire:model.blur="priceForm.{{ $unit }}.qty_in_pcs" class="h-8 w-20 text-right" :error="$errors->has('priceForm.'.$unit.'.qty_in_pcs')" />
                                                <span>{{ __('sellers.products.edit.pricing.pcs_suffix') }}</span>
                                            </div>
                                            @error('priceForm.'.$unit.'.qty_in_pcs')
                                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @endif

                                    <div>
                                        <div class="flex items-center gap-2">
                                            <label class="text-sm text-text-secondary">{{ __('sellers.products.edit.pricing.price_label') }}</label>
                                            <x-input type="number" min="0" step="0.01" wire:model.blur="priceForm.{{ $unit }}.price" class="h-9 w-36 text-right text-base" :error="$errors->has('priceForm.'.$unit.'.price')" />
                                            <span class="text-sm text-text-secondary">UZS</span>
                                        </div>
                                        @error('priceForm.'.$unit.'.price')
                                            <p class="mt-1 text-right text-xs text-danger-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                @if ($unit === 'pcs' && (float) $row->price <= 0)
                                    <p class="mt-2 text-xs text-warning-700">{{ __('sellers.products.edit.pricing.set_price_warning') }}</p>
                                @endif

                                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                    @if (! $row->is_vitrin)
                                        @if ((float) $row->price > 0)
                                            <button type="button" wire:click="setVitrin('{{ $unit }}')" class="text-accent-700 hover:underline">{{ __('sellers.products.edit.pricing.set_vitrin') }}</button>
                                        @else
                                            <span class="cursor-not-allowed text-text-disabled" title="{{ __('sellers.products.edit.pricing.set_vitrin_disabled_caption') }}">{{ __('sellers.products.edit.pricing.set_vitrin') }}</span>
                                        @endif
                                    @endif

                                    @if (! $row->is_vitrin && $unit !== 'pcs')
                                        <span class="text-border-strong">·</span>
                                    @endif

                                    @if ($unit !== 'pcs')
                                        @if ($row->is_vitrin)
                                            <span class="cursor-not-allowed text-text-disabled" title="{{ __('sellers.products.edit.pricing.remove_disabled_caption') }}">{{ __('sellers.products.edit.pricing.remove') }}</span>
                                        @else
                                            <button type="button" wire:click="confirmRemove('{{ $unit }}')" class="text-danger-600 hover:underline">{{ __('sellers.products.edit.pricing.remove') }}</button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @elseif ($addingUnit[$unit] ?? false)
                            <div wire:key="price-row-{{ $unit }}-adding" class="rounded-md border border-border bg-surface-raised p-4">
                                <span class="text-lg font-semibold text-text-primary">{{ __('sellers.products.unit.'.$unit) }}</span>

                                <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2 text-sm text-text-secondary">
                                            <span>{{ __('sellers.products.edit.pricing.qty_prefix', ['unit' => __('sellers.products.unit.'.$unit)]) }}</span>
                                            <x-input type="number" min="1" step="1" wire:model="newRowForm.{{ $unit }}.qty_in_pcs" class="h-8 w-20 text-right" :error="$errors->has('newRowForm.'.$unit.'.qty_in_pcs')" />
                                            <span>{{ __('sellers.products.edit.pricing.pcs_suffix') }}</span>
                                        </div>
                                        @error('newRowForm.'.$unit.'.qty_in_pcs')
                                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <div class="flex items-center gap-2">
                                            <label class="text-sm text-text-secondary">{{ __('sellers.products.edit.pricing.price_label') }}</label>
                                            <x-input type="number" min="0" step="0.01" wire:model="newRowForm.{{ $unit }}.price" class="h-9 w-36 text-right text-base" :error="$errors->has('newRowForm.'.$unit.'.price')" />
                                            <span class="text-sm text-text-secondary">UZS</span>
                                        </div>
                                        @error('newRowForm.'.$unit.'.price')
                                            <p class="mt-1 text-right text-xs text-danger-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center justify-end gap-2">
                                    <x-button type="button" variant="ghost" size="sm" wire:click="cancelEnable('{{ $unit }}')">{{ __('sellers.products.edit.pricing.cancel') }}</x-button>
                                    <x-button type="button" variant="primary" size="sm" wire:click="saveEnable('{{ $unit }}')">
                                        {{ __('sellers.products.edit.pricing.save') }}
                                    </x-button>
                                </div>
                            </div>
                        @else
                            <button
                                type="button"
                                wire:key="price-row-{{ $unit }}-ghost"
                                wire:click="startEnable('{{ $unit }}')"
                                class="flex min-h-[3.25rem] w-full items-center justify-center rounded-md border border-dashed border-border-strong text-sm text-text-secondary transition-colors hover:border-accent-300 hover:text-text-primary"
                            >
                                + {{ __('sellers.products.edit.pricing.enable_unit', ['unit' => __('sellers.products.unit.'.$unit)]) }}
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- 4. Images --}}
        <div class="rounded-md border border-border bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('sellers.products.images.section_title') }}</h2>
            </div>

            <div class="p-5">
                <p class="mb-3 text-sm text-text-secondary">{{ __('sellers.products.images.caption') }}</p>

                <div class="flex flex-wrap gap-3">
                    @foreach ($this->images as $index => $image)
                        <div wire:key="product-image-{{ $image->id }}" class="w-24">
                            <div class="relative h-24 w-24 overflow-hidden rounded-md border border-border">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="" class="h-full w-full object-cover">
                                @if ($index === 0)
                                    <span class="absolute top-1 left-1 rounded-sm bg-accent-600 px-1.5 py-0.5 text-[10px] font-medium text-white">{{ __('sellers.products.images.primary_badge') }}</span>
                                @endif
                                <button
                                    type="button"
                                    wire:click="removeImage({{ $image->id }})"
                                    wire:confirm="{{ __('sellers.products.images.remove_confirm') }}"
                                    class="absolute top-1 right-1 flex h-5 w-5 items-center justify-center rounded-full bg-slate-900/60 text-xs text-white hover:bg-slate-900/80"
                                    aria-label="{{ __('sellers.products.images.remove') }}"
                                >
                                    ✕
                                </button>
                            </div>
                            <div class="mt-1 flex items-center justify-center gap-2 text-xs text-text-secondary">
                                <button type="button" wire:click="moveImageUp({{ $image->id }})" @if ($index === 0) disabled @endif class="rounded-sm px-1.5 py-0.5 hover:bg-surface-hover disabled:cursor-not-allowed disabled:opacity-30" aria-label="{{ __('sellers.products.images.move_up') }}">
                                    ↑
                                </button>
                                <button type="button" wire:click="moveImageDown({{ $image->id }})" @if ($index === $this->images->count() - 1) disabled @endif class="rounded-sm px-1.5 py-0.5 hover:bg-surface-hover disabled:cursor-not-allowed disabled:opacity-30" aria-label="{{ __('sellers.products.images.move_down') }}">
                                    ↓
                                </button>
                            </div>
                        </div>
                    @endforeach

                    @if ($this->images->count() < 4)
                        <label wire:key="product-image-upload-slot" class="flex h-24 w-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed border-border-strong text-xs text-text-secondary hover:border-accent-300 hover:text-text-primary">
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

                <p class="mt-2 text-xs text-text-muted">{{ __('sellers.products.images.count_caption', ['count' => $this->images->count()]) }}</p>
            </div>
        </div>
    </div>

    {{-- Remove confirmation modal --}}
    @if ($showRemoveConfirm)
        <div class="fixed inset-0 z-modal-backdrop bg-slate-900/40" wire:click="cancelRemove"></div>
        <div class="fixed inset-0 z-modal flex items-center justify-center p-4">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface-overlay p-5 shadow-lg">
                <h2 class="text-lg font-semibold text-text-primary">
                    {{ __('sellers.products.edit.pricing.remove_confirm_title', ['unit' => __('sellers.products.unit.'.($removingUnit ?? 'pack'))]) }}
                </h2>
                <p class="mt-2 text-sm text-text-secondary">{{ __('sellers.products.edit.pricing.remove_confirm_body') }}</p>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <x-button variant="ghost" wire:click="cancelRemove">{{ __('sellers.products.edit.pricing.cancel') }}</x-button>
                    <x-button variant="danger-solid" wire:click="removeUnit" wire:loading.attr="disabled" wire:target="removeUnit">
                        {{ __('sellers.products.edit.pricing.remove') }}
                    </x-button>
                </div>
            </div>
        </div>
    @endif
</div>
