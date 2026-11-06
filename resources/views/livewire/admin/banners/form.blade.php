{{--
    Single form used for both Create and Edit ($banner is null on create).
    Every field keeps the exact same wire:model binding regardless of mode —
    there is no @if/@else swap between differently-bound blocks anywhere in
    this template, so the stale-Alpine-binding wire:key bug that hit the
    seller-registration wizard doesn't apply here. See Form::class docblock.
--}}
<div class="mx-auto w-full max-w-4xl">
    <x-page-header :title="$banner ? __('admin.ui.edit_banner') : __('admin.ui.new_banner')" :subtitle="__('admin.ui.shown_on_the_buyer_storefront_while_active_and_within_its_sc')">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.banners.index') }}" wire:navigate variant="ghost">
                {{ __('admin.ui.all_banners') }}
            </x-button>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save, imageUpload, mobileImageUpload">
                {{ __('admin.ui.save_banner') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col gap-6">
        {{-- Content --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.content') }}</h2>
            </div>
            <div class="px-5 py-5">
                <x-locale-tabs :incomplete="$this->incompleteLocales">
                    @foreach (config('ribbon.locales') as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak>
                            <label for="field-title-{{ $locale }}" class="mb-1 block text-sm font-medium text-text-primary">
                                {{ __('admin.ui.title') }} <span class="text-danger-600">*</span>
                            </label>
                            <x-input id="field-title-{{ $locale }}" type="text" wire:model.blur="title.{{ $locale }}" :error="$errors->has('title.'.$locale)" :placeholder="__('admin.ui.e_g_summer_ribbon_sale')" />
                            @error('title.'.$locale)
                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </x-locale-tabs>

                <div class="mt-5 border-t border-border pt-4">
                    <label for="field-linkUrl" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.link_url') }}</label>
                    <x-input id="field-linkUrl" type="text" wire:model.blur="linkUrl" :error="$errors->has('linkUrl')" placeholder="https://…" />
                    @error('linkUrl')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.where_the_banner_links_to_a_full_https_address_or_a_path_on') }}</p>
                </div>
            </div>
        </div>

        {{-- Images --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.images') }}</h2>
            </div>
            <div class="grid grid-cols-1 gap-6 px-5 py-5 sm:grid-cols-2">
                <div>
                    <label for="field-imageUpload" class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('admin.ui.image') }} <span class="text-danger-600">*</span>
                    </label>

                    @if ($imageUpload)
                        <img src="{{ $imageUpload->temporaryUrl() }}" alt="{{ __('admin.ui.new_banner_image_preview') }}" class="mb-2 h-32 w-full rounded-sm border border-border object-cover">
                    @elseif ($existingImagePath)
                        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($existingImagePath) }}" alt="{{ __('admin.ui.current_banner_image') }}" class="mb-2 h-32 w-full rounded-sm border border-border object-cover">
                    @endif

                    <input id="field-imageUpload" type="file" wire:model="imageUpload" accept="image/*" class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

                    <div wire:loading wire:target="imageUpload" class="mt-1 text-xs text-text-muted">{{ __('admin.ui.uploading') }}</div>

                    @error('imageUpload')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.required_max_4mb') }}</p>

                    @if ($existingImagePath || $imageUpload)
                        <button type="button" wire:click="removeImage" class="mt-1 text-xs text-danger-600 hover:underline">{{ __('admin.ui.remove_image') }}</button>
                    @endif
                </div>

                <div>
                    <label for="field-mobileImageUpload" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.mobile_image') }}</label>

                    @if ($mobileImageUpload)
                        <img src="{{ $mobileImageUpload->temporaryUrl() }}" alt="{{ __('admin.ui.new_mobile_banner_image_preview') }}" class="mb-2 h-32 w-full rounded-sm border border-border object-cover">
                    @elseif ($existingMobileImagePath)
                        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($existingMobileImagePath) }}" alt="{{ __('admin.ui.current_mobile_banner_image') }}" class="mb-2 h-32 w-full rounded-sm border border-border object-cover">
                    @endif

                    <input id="field-mobileImageUpload" type="file" wire:model="mobileImageUpload" accept="image/*" class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

                    <div wire:loading wire:target="mobileImageUpload" class="mt-1 text-xs text-text-muted">{{ __('admin.ui.uploading') }}</div>

                    @error('mobileImageUpload')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.optional_max_4mb_falls_back_to_the_main_image_when_unset') }}</p>

                    @if ($existingMobileImagePath || $mobileImageUpload)
                        <button type="button" wire:click="removeMobileImage" class="mt-1 text-xs text-danger-600 hover:underline">{{ __('admin.ui.remove_mobile_image') }}</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Scheduling & placement --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('admin.ui.scheduling_placement') }}</h2>
            </div>
            <div class="px-5 py-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="field-placement" class="mb-1 block text-sm font-medium text-text-primary">
                            {{ __('admin.ui.placement') }} <span class="text-danger-600">*</span>
                        </label>
                        <x-select id="field-placement" wire:model.blur="placement" :error="$errors->has('placement')">
                            @foreach (array_keys(\App\Livewire\Admin\Banners\Form::PLACEMENTS) as $value)
                                <option value="{{ $value }}">{{ __('admin.ui.placement_'.$value) }}</option>
                            @endforeach
                        </x-select>
                        @error('placement')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.where_on_the_storefront_this_banner_appears') }}</p>
                    </div>

                    <div>
                        <label for="field-categoryId" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.category') }}</label>
                        <x-select id="field-categoryId" wire:model="categoryId" :error="$errors->has('categoryId')">
                            <option value="">{{ __('admin.ui.no_specific_category_generic') }}</option>
                            @foreach ($this->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name[app()->getLocale()] ?? $category->name[config('ribbon.locales')[0]] }}</option>
                            @endforeach
                        </x-select>
                        @error('categoryId')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.only_meaningful_for_category_top_banners_leave_unset_for_a_s') }}</p>
                    </div>

                    <div>
                        <label for="field-sortOrder" class="mb-1 block text-sm font-medium text-text-primary">
                            {{ __('admin.ui.sort_order') }} <span class="text-danger-600">*</span>
                        </label>
                        <x-input id="field-sortOrder" type="number" min="0" wire:model.blur="sortOrder" :error="$errors->has('sortOrder')" />
                        @error('sortOrder')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="field-startsAt" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.starts_at') }}</label>
                        <x-input id="field-startsAt" type="datetime-local" wire:model.blur="startsAt" :error="$errors->has('startsAt')" />
                        @error('startsAt')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.leave_blank_to_make_it_eligible_immediately') }}</p>
                    </div>

                    <div>
                        <label for="field-endsAt" class="mb-1 block text-sm font-medium text-text-primary">{{ __('admin.ui.ends_at') }}</label>
                        <x-input id="field-endsAt" type="datetime-local" wire:model.blur="endsAt" :error="$errors->has('endsAt')" />
                        @error('endsAt')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-text-muted">{{ __('admin.ui.leave_blank_to_run_indefinitely') }}</p>
                    </div>
                </div>

                <div class="mt-5 border-t border-border pt-4">
                    <x-toggle wire:model="isActive" :label="__('admin.ui.active')" :caption="__('admin.ui.inactive_banners_are_never_shown_regardless_of_the_schedulin')" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <x-button tag="a" href="{{ route('admin.banners.index') }}" wire:navigate variant="ghost">{{ __('admin.ui.cancel') }}</x-button>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save, imageUpload, mobileImageUpload">
                {{ __('admin.ui.save_banner') }}
            </x-button>
        </div>
    </div>
</div>
