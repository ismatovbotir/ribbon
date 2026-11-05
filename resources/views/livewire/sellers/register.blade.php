<div class="px-6 py-6 sm:px-8 sm:py-8">
    @if ($step < 3)
        {{-- Steps 1–2: wizard --}}
        <div class="mb-6">
            <p class="text-xs font-medium tracking-wide text-text-muted uppercase">{{ __('sellers.step_of', ['current' => $step, 'total' => 2]) }}</p>
            <h1 class="mt-1 text-xl font-semibold text-text-primary">
                {{ $step === 1 ? __('sellers.step1_title') : __('sellers.step2_title') }}
            </h1>
            <p class="mt-1 text-sm text-text-secondary">
                {{ $step === 1
                    ? __('sellers.step1_subtitle')
                    : __('sellers.step2_subtitle') }}
            </p>

            <div class="mt-4 flex items-center gap-2" role="presentation">
                <span class="h-1.5 flex-1 rounded-pill {{ $step >= 1 ? 'bg-accent-600' : 'bg-border' }}"></span>
                <span class="h-1.5 flex-1 rounded-pill {{ $step >= 2 ? 'bg-accent-600' : 'bg-border' }}"></span>
            </div>
        </div>

        @if ($step === 1)
            <form wire:key="step-1-account" wire:submit="continueToCompanyDetails" class="space-y-4">
                <div>
                    <label for="field-ownerName" class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.full_name_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-input id="field-ownerName" type="text" wire:model.blur="ownerName" :error="$errors->has('ownerName')" placeholder="{{ __('sellers.full_name_placeholder') }}" autofocus />
                    @error('ownerName')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="field-ownerEmail" class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.email_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-input id="field-ownerEmail" type="email" wire:model.blur="ownerEmail" :error="$errors->has('ownerEmail')" placeholder="{{ __('sellers.email_placeholder') }}" />
                    @error('ownerEmail')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="field-ownerPassword" class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.password_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-input id="field-ownerPassword" type="password" wire:model.blur="ownerPassword" :error="$errors->has('ownerPassword')" placeholder="{{ __('sellers.password_placeholder') }}" />
                    @error('ownerPassword')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="field-ownerPasswordConfirmation" class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.confirm_password_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-input id="field-ownerPasswordConfirmation" type="password" wire:model.blur="ownerPasswordConfirmation" :error="$errors->has('ownerPasswordConfirmation')" />
                    @error('ownerPasswordConfirmation')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="field-locale" class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.language_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-language-select id="field-locale" wire:model="locale" :error="$errors->has('locale')" />
                    @error('locale')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end pt-2">
                    <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="continueToCompanyDetails">
                        {{ __('sellers.continue_button') }}
                    </x-button>
                </div>
            </form>
        @else
            <form wire:key="step-2-company" wire:submit="register" class="space-y-4">
                <div>
                    <label for="field-companyName" class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.company_name_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-input id="field-companyName" type="text" wire:model.blur="companyName" :error="$errors->has('companyName')" placeholder="{{ __('sellers.company_name_placeholder') }}" autofocus />
                    @error('companyName')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="field-companyLogo" class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.company_logo_label') }}
                    </label>

                    @if ($companyLogo)
                        <img src="{{ $companyLogo->temporaryUrl() }}" alt="{{ __('sellers.company_logo_label') }}" class="mb-2 h-16 w-16 rounded-sm border border-border object-cover">
                    @endif

                    <input id="field-companyLogo" type="file" wire:model="companyLogo" accept="image/jpeg,image/png" class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

                    <div wire:loading wire:target="companyLogo" class="mt-1 text-xs text-text-muted">{{ __('sellers.uploading') }}</div>

                    @error('companyLogo')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">{{ __('sellers.company_logo_caption') }}</p>

                    @if ($companyLogo)
                        <button type="button" wire:click="removeCompanyLogo" class="mt-1 text-xs text-danger-600 hover:underline">{{ __('sellers.remove_logo') }}</button>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="field-countryId" class="mb-1 block text-sm font-medium text-text-primary">
                            {{ __('sellers.country_label') }} <span class="text-danger-600">*</span>
                        </label>
                        <x-select id="field-countryId" wire:model.live="countryId" :error="$errors->has('countryId')">
                            <option value="">{{ __('sellers.country_placeholder') }}</option>
                            @foreach ($this->countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name[app()->getLocale()] ?? $country->name[config('ribbon.locales')[0]] }}</option>
                            @endforeach
                        </x-select>
                        @error('countryId')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="field-regionId" class="mb-1 block text-sm font-medium text-text-primary">
                            {{ __('sellers.region_label') }} <span class="text-danger-600">*</span>
                        </label>
                        <x-select id="field-regionId" wire:model.live="regionId" :error="$errors->has('regionId')" :disabled="! $countryId">
                            <option value="">{{ $countryId ? __('sellers.region_placeholder') : __('sellers.region_placeholder_disabled') }}</option>
                            @foreach ($this->regions as $region)
                                <option value="{{ $region->id }}">{{ $region->name[app()->getLocale()] ?? $region->name[config('ribbon.locales')[0]] }}</option>
                            @endforeach
                        </x-select>
                        @error('regionId')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="field-cityId" class="mb-1 block text-sm font-medium text-text-primary">
                            {{ __('sellers.city_label') }} <span class="text-danger-600">*</span>
                        </label>
                        <x-select id="field-cityId" wire:model="cityId" :error="$errors->has('cityId')" :disabled="! $regionId">
                            <option value="">{{ $regionId ? __('sellers.city_placeholder') : __('sellers.city_placeholder_disabled') }}</option>
                            @foreach ($this->cities as $city)
                                <option value="{{ $city->id }}">{{ $city->name[app()->getLocale()] ?? $city->name[config('ribbon.locales')[0]] }}</option>
                            @endforeach
                        </x-select>
                        @error('cityId')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="field-companyAddress" class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.address_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <textarea id="field-companyAddress"
                        wire:model.blur="companyAddress"
                        rows="2"
                        class="block w-full rounded-sm border bg-surface px-3 py-2 text-base text-text-primary placeholder:text-text-muted focus:border-accent-500 focus:outline-none {{ $errors->has('companyAddress') ? 'border-danger-600' : 'border-border' }}"
                        placeholder="{{ __('sellers.address_placeholder') }}"
                    ></textarea>
                    @error('companyAddress')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="field-companyVatNumber" class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.vat_number_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-input id="field-companyVatNumber" type="text" wire:model.blur="companyVatNumber" :error="$errors->has('companyVatNumber')" />
                    @error('companyVatNumber')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="field-companyPhone" class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.phone_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <x-input id="field-companyPhone" type="tel" autocomplete="tel" wire:model.blur="companyPhone" :error="$errors->has('companyPhone')" placeholder="{{ __('sellers.phone_placeholder') }}" />
                    @error('companyPhone')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="field-companyCertificate" class="mb-1 block text-sm font-medium text-text-primary">
                        {{ __('sellers.certificate_label') }} <span class="text-danger-600">*</span>
                    </label>
                    <input id="field-companyCertificate" type="file" wire:model="companyCertificate" accept="application/pdf,image/jpeg,image/png" class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">
                    <div wire:loading wire:target="companyCertificate" class="mt-1 text-xs text-text-muted">{{ __('sellers.uploading') }}</div>
                    @error('companyCertificate')
                        <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-text-muted">{{ __('sellers.certificate_caption') }}</p>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <x-button type="button" variant="ghost" wire:click="back">{{ __('sellers.back_button') }}</x-button>
                    <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="register, companyLogo, companyCertificate">
                        {{ __('sellers.submit_button') }}
                    </x-button>
                </div>
            </form>
        @endif
    @else
        {{-- Step 3: static confirmation — no redirect, no login performed --}}
        <div class="px-2 py-4 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-pill bg-success-50 text-success-600">
                <x-icon name="check" class="size-6" />
            </div>
            <h1 class="text-xl font-semibold text-text-primary">{{ __('sellers.confirmation_title') }}</h1>
            <p class="mx-auto mt-2 max-w-sm text-sm text-text-secondary">
                {{ __('sellers.confirmation_body', ['company' => $companyName]) }}
            </p>
            <x-button tag="a" href="{{ url('/') }}" variant="secondary" class="mt-6">{{ __('sellers.return_home_button') }}</x-button>
        </div>
    @endif
</div>
