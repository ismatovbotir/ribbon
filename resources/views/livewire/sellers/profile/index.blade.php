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
                <label class="mb-1 block text-sm font-medium text-text-primary">{{ __('sellers.profile.logo_label') }}</label>

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
                            <input type="file" wire:model="logoUpload" accept="image/jpeg,image/png" class="block w-full max-w-xs text-sm text-text-secondary file:mr-3 file:rounded-sm file:border file:border-border-strong file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-text-primary hover:file:bg-surface-hover">

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
</div>
