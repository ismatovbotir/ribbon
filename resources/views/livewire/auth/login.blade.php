<div class="px-6 py-6 sm:px-8 sm:py-8">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-text-primary">{{ __('auth.login.title') }}</h1>
        <p class="mt-1 text-sm text-text-secondary">{{ __('auth.login.subtitle') }}</p>
    </div>

    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="mb-1 block text-sm font-medium text-text-primary">
                {{ __('auth.email_label') }} <span class="text-danger-600">*</span>
            </label>
            <x-input type="email" wire:model.blur="email" :error="$errors->has('email')" placeholder="{{ __('auth.email_placeholder') }}" autofocus />
            @error('email')
                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-text-primary">
                {{ __('auth.password_label') }} <span class="text-danger-600">*</span>
            </label>
            <x-input type="password" wire:model.blur="password" :error="$errors->has('password')" />
            @error('password')
                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-end pt-2">
            <x-button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="login">
                {{ __('auth.login.submit_button') }}
            </x-button>
        </div>
    </form>

    <p class="mt-6 text-center text-xs text-text-secondary">
        {{ __('auth.login.register_prompt') }}
        <a href="{{ route('sellers.register') }}" class="font-medium text-accent-600 hover:underline">{{ __('auth.login.register_link') }}</a>
    </p>
</div>
