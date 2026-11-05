<div>
    <x-page-header :title="__('sellers.nav.profile')" :subtitle="__('sellers.profile.subtitle')" />

    @if (session('status'))
        <div class="mb-6 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-md border border-border bg-surface-raised">
        <div class="border-b border-border bg-surface-subtle px-5 py-3">
            <h2 class="text-lg font-semibold text-text-primary">{{ __('sellers.profile.section_company') }}</h2>
        </div>

        <div class="p-5">
            <div>
                <p class="text-sm font-medium text-text-primary">{{ $seller->name }}</p>
            </div>

            <div class="mt-5">
                <label @if ($isOwner) for="logoUpload" @endif class="mb-1 block text-sm font-medium text-text-primary">{{ __('sellers.profile.logo_label') }}</label>

                <div class="flex items-start gap-4">
                    @if ($logoUpload)
                        <img src="{{ $logoUpload->temporaryUrl() }}" alt="{{ __('sellers.profile.logo_label') }}" class="h-20 w-20 rounded-sm border border-border object-cover">
                    @elseif ($existingLogoPath)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingLogoPath) }}" alt="{{ $seller->name }}" class="h-20 w-20 rounded-sm border border-border object-cover">
                    @else
                        <span class="flex h-20 w-20 items-center justify-center rounded-sm border border-dashed border-border-strong text-xs text-text-muted">{{ __('sellers.profile.no_logo') }}</span>
                    @endif

                    <div class="flex-1">
                        @if ($isOwner)
                            <x-file-input id="logoUpload" wire:model="logoUpload" accept="image/jpeg,image/png" :label="__('sellers.profile.logo_label')" />

                            <div wire:loading wire:target="logoUpload" class="mt-1 text-xs text-text-muted">{{ __('sellers.uploading') }}</div>

                            @error('logoUpload')
                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-text-muted">{{ __('sellers.company_logo_caption') }}</p>

                            <div class="mt-2 flex items-center gap-3">
                                @if ($logoUpload)
                                    <x-button type="button" variant="primary" size="sm" wire:click="saveLogo" wire:loading.attr="disabled" wire:target="saveLogo, logoUpload">
                                        {{ __('sellers.profile.save_logo') }}
                                    </x-button>
                                @endif
                                @if ($existingLogoPath && ! $logoUpload)
                                    <button type="button" wire:click="removeLogo" class="text-xs text-danger-600 hover:underline">{{ __('sellers.remove_logo') }}</button>
                                @endif
                            </div>
                        @else
                            {{-- Employees can see the logo but not change it — the
                                 control stays visible (disabled + captioned), it
                                 isn't hidden, so an Employee still knows the
                                 capability exists and who to ask. --}}
                            <input type="file" disabled class="block w-full max-w-xs cursor-not-allowed text-sm text-text-disabled file:mr-3 file:cursor-not-allowed file:rounded-sm file:border file:border-border file:bg-surface-subtle file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-disabled">
                            <p class="mt-1 text-xs text-text-muted">{{ __('sellers.profile.owner_only_caption') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-md border border-border bg-surface-raised">
        <div class="border-b border-border bg-surface-subtle px-5 py-3">
            <h2 class="text-lg font-semibold text-text-primary">{{ __('sellers.profile.section_business') }}</h2>
        </div>
        <form wire:submit="saveBusinessInfo" class="space-y-5 p-5">
            <p class="text-xs text-text-muted">{{ __('sellers.profile.business_caption') }}</p>
            <fieldset @disabled(! $isOwner) class="space-y-5">
                <x-field :label="__('sellers.profile.vat_payer_label')" for="vat-payer" :error="$errors->first('vatPayer')">
                    <x-select id="vat-payer" wire:model="vatPayer" class="max-w-xs" :error="$errors->has('vatPayer')">
                        <option value="">{{ __('sellers.profile.vat_payer_unset') }}</option>
                        <option value="yes">{{ __('sellers.profile.vat_payer_yes') }}</option>
                        <option value="no">{{ __('sellers.profile.vat_payer_no') }}</option>
                    </x-select>
                </x-field>

                <fieldset>
                    <legend class="mb-1.5 text-sm font-medium text-text-primary">{{ __('sellers.profile.payment_methods_label') }}</legend>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        @foreach (\App\Models\Seller::PAYMENT_METHODS as $method)
                            <label class="inline-flex min-h-11 items-center gap-2 text-sm text-text-primary">
                                <input type="checkbox" wire:model="paymentMethods" value="{{ $method }}" class="size-4 accent-accent-700">
                                {{ __('storefront.seller.payment_method.'.$method) }}
                            </label>
                        @endforeach
                    </div>
                    @error('paymentMethods.*')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </fieldset>

                <x-field :label="__('sellers.profile.warranty_label')" for="warranty-info" :hint="__('sellers.profile.warranty_caption')" :error="$errors->first('warrantyInfo')">
                    <x-textarea id="warranty-info" rows="3" wire:model="warrantyInfo" :error="$errors->has('warrantyInfo')" />
                </x-field>
            </fieldset>

            @if ($isOwner)
                <div class="flex justify-end">
                    <x-button type="submit" variant="primary" size="sm" wire:loading.attr="disabled" wire:target="saveBusinessInfo">{{ __('sellers.profile.save_business') }}</x-button>
                </div>
            @else
                <p class="text-xs text-text-muted">{{ __('sellers.profile.business_owner_only') }}</p>
            @endif
        </form>
    </div>

    @if ($isOwner)
        <div class="mt-6 rounded-md border border-border bg-surface-raised">
            <div class="border-b border-border bg-surface-subtle px-5 py-3">
                <h2 class="text-lg font-semibold text-text-primary">{{ __('sellers.certificate_label') }}</h2>
            </div>
            <div class="p-5">
                <p class="text-sm text-text-primary">{{ $seller->hasCertificate() ? __('sellers.profile.certificate_on_file') : __('sellers.profile.certificate_missing') }}</p>
                <div class="mt-3">
                    <label for="certificate-upload" class="mb-1 block text-sm font-medium text-text-primary">{{ __('sellers.profile.certificate_replace') }}</label>
                    <input id="certificate-upload" type="file" wire:model="certificateUpload" accept="application/pdf,image/jpeg,image/png" class="block w-full max-w-md text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">
                    <div wire:loading wire:target="certificateUpload" class="mt-1 text-xs text-text-muted">{{ __('sellers.uploading') }}</div>
                    @error('certificateUpload')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">{{ __('sellers.certificate_caption') }}</p>
                </div>
                @if ($certificateUpload)
                    <x-button type="button" variant="primary" size="sm" class="mt-3" wire:click="saveCertificate" wire:loading.attr="disabled" wire:target="saveCertificate, certificateUpload">{{ __('sellers.profile.save_certificate') }}</x-button>
                @endif
            </div>
        </div>
    @endif
</div>
