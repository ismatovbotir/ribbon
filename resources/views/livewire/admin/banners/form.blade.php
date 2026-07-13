{{--
    Single form used for both Create and Edit ($banner is null on create).
    Every field keeps the exact same wire:model binding regardless of mode —
    there is no @if/@else swap between differently-bound blocks anywhere in
    this template, so the stale-Alpine-binding wire:key bug that hit the
    seller-registration wizard doesn't apply here. See Form::class docblock.
--}}
<div class="max-w-4xl">
    <x-page-header :title="$banner ? 'Edit Banner' : 'New Banner'" subtitle="The buyer storefront doesn't render banners yet — this only manages what will show once it does.">
        <x-slot:actions>
            <x-button tag="a" href="{{ route('admin.banners.index') }}" wire:navigate variant="ghost">
                ← All banners
            </x-button>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save, imageUpload, mobileImageUpload">
                Save Banner
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
                <h2 class="text-lg font-semibold text-text-primary">Content</h2>
            </div>
            <div class="px-5 py-5">
                <x-locale-tabs :incomplete="$this->incompleteLocales">
                    @foreach (config('ribbon.locales') as $locale)
                        <div x-show="locale === '{{ $locale }}'" x-cloak>
                            <label class="mb-1 block text-sm font-medium text-text-primary">
                                Title <span class="text-danger-600">*</span>
                            </label>
                            <x-input type="text" wire:model.blur="title.{{ $locale }}" :error="$errors->has('title.'.$locale)" placeholder="e.g. Summer ribbon sale" />
                            @error('title.'.$locale)
                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </x-locale-tabs>

                <div class="mt-5 border-t border-border pt-4">
                    <label class="mb-1 block text-sm font-medium text-text-primary">Link URL</label>
                    <x-input type="text" wire:model.blur="linkUrl" :error="$errors->has('linkUrl')" placeholder="https://…" />
                    @error('linkUrl')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">Where the banner links to. Not validated against internal routes — there's no storefront yet.</p>
                </div>
            </div>
        </div>

        {{-- Images --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">Images</h2>
            </div>
            <div class="grid grid-cols-1 gap-6 px-5 py-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">
                        Image <span class="text-danger-600">*</span>
                    </label>

                    @if ($imageUpload)
                        <img src="{{ $imageUpload->temporaryUrl() }}" alt="New banner image preview" class="mb-2 h-32 w-full rounded-sm border border-border object-cover">
                    @elseif ($existingImagePath)
                        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($existingImagePath) }}" alt="Current banner image" class="mb-2 h-32 w-full rounded-sm border border-border object-cover">
                    @endif

                    <input type="file" wire:model="imageUpload" accept="image/*" class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

                    <div wire:loading wire:target="imageUpload" class="mt-1 text-xs text-text-muted">Uploading…</div>

                    @error('imageUpload')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">Required. Max 4MB.</p>

                    @if ($existingImagePath || $imageUpload)
                        <button type="button" wire:click="removeImage" class="mt-1 text-xs text-danger-600 hover:underline">Remove image</button>
                    @endif
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-text-primary">Mobile image</label>

                    @if ($mobileImageUpload)
                        <img src="{{ $mobileImageUpload->temporaryUrl() }}" alt="New mobile banner image preview" class="mb-2 h-32 w-full rounded-sm border border-border object-cover">
                    @elseif ($existingMobileImagePath)
                        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($existingMobileImagePath) }}" alt="Current mobile banner image" class="mb-2 h-32 w-full rounded-sm border border-border object-cover">
                    @endif

                    <input type="file" wire:model="mobileImageUpload" accept="image/*" class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

                    <div wire:loading wire:target="mobileImageUpload" class="mt-1 text-xs text-text-muted">Uploading…</div>

                    @error('mobileImageUpload')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">Optional. Max 4MB. Falls back to the main image when unset.</p>

                    @if ($existingMobileImagePath || $mobileImageUpload)
                        <button type="button" wire:click="removeMobileImage" class="mt-1 text-xs text-danger-600 hover:underline">Remove mobile image</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Scheduling & placement --}}
        <div class="rounded-md border border-border-strong bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">Scheduling &amp; placement</h2>
            </div>
            <div class="px-5 py-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-primary">
                            Placement <span class="text-danger-600">*</span>
                        </label>
                        <x-select wire:model.blur="placement" :error="$errors->has('placement')">
                            @foreach (\App\Livewire\Admin\Banners\Form::PLACEMENTS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-select>
                        @error('placement')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-text-muted">Provisional slots — the real storefront layout isn't built yet.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-primary">Category</label>
                        <x-select wire:model="categoryId" :error="$errors->has('categoryId')">
                            <option value="">No specific category (generic)</option>
                            @foreach ($this->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name[app()->getLocale()] ?? $category->name[config('ribbon.locales')[0]] }}</option>
                            @endforeach
                        </x-select>
                        @error('categoryId')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-text-muted">Only meaningful for Category — Top banners; leave unset for a sitewide default.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-primary">
                            Sort order <span class="text-danger-600">*</span>
                        </label>
                        <x-input type="number" min="0" wire:model.blur="sortOrder" :error="$errors->has('sortOrder')" />
                        @error('sortOrder')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-primary">Starts at</label>
                        <x-input type="datetime-local" wire:model.blur="startsAt" :error="$errors->has('startsAt')" />
                        @error('startsAt')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-text-muted">Leave blank to make it eligible immediately.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-text-primary">Ends at</label>
                        <x-input type="datetime-local" wire:model.blur="endsAt" :error="$errors->has('endsAt')" />
                        @error('endsAt')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-text-muted">Leave blank to run indefinitely.</p>
                    </div>
                </div>

                <div class="mt-5 border-t border-border pt-4">
                    <x-toggle wire:model="isActive" label="Active" caption="Inactive banners are never shown, regardless of the scheduling window." />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <x-button tag="a" href="{{ route('admin.banners.index') }}" wire:navigate variant="ghost">Cancel</x-button>
            <x-button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save, imageUpload, mobileImageUpload">
                Save Banner
            </x-button>
        </div>
    </div>
</div>
