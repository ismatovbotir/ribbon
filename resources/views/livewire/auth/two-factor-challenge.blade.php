<div class="rounded-md border border-border bg-surface-raised p-5 md:p-6">
    <h1 class="text-xl font-semibold text-text-primary">{{ __('auth.two_factor.challenge_title') }}</h1>
    <p class="mt-2 text-sm text-text-secondary">{{ __('auth.two_factor.challenge_body') }}</p>

    <form wire:submit="verify" class="mt-5 space-y-4">
        <x-field :label="__('auth.two_factor.code_or_recovery_label')" for="two-factor-code" :required="true" :error="$errors->first('code')">
            <x-input id="two-factor-code" type="text" autocomplete="one-time-code" maxlength="20" autofocus class="font-mono" wire:model="code" :error="$errors->has('code')" />
        </x-field>
        <x-button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="verify">{{ __('auth.two_factor.verify') }}</x-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
        @csrf
        <button type="submit" class="min-h-11 text-sm text-text-secondary hover:underline">{{ __('admin.ui.log_out') }}</button>
    </form>
</div>
