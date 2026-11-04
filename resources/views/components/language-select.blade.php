{{--
    Shared "preferred language" select used by both seller registration
    (Step 1 — Account) and seller employee management (Add Employee) — see
    config('ribbon.user_locales') for the single source of truth on the
    option list/order (deliberately uz/en/ru, not app-wide uz/ru/en).
    Forwards wire:model and any other attributes straight to the <select>,
    same pattern as <x-select>.
--}}
@props(['error' => false])

<select {{ $attributes->merge([
    'class' => 'block h-field w-full appearance-none rounded-sm border bg-surface bg-[length:1rem] bg-[position:right_0.75rem_center] bg-no-repeat py-0 pr-9 pl-3 text-field text-text-primary field-chevron focus-visible:border-accent-700 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus-ring disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-text-muted '
        .($error ? 'border-danger-600' : 'border-border-input'),
]) }}>
    @foreach (config('ribbon.user_locales') as $localeCode)
        <option value="{{ $localeCode }}">{{ __('sellers.language_option.'.$localeCode) }}</option>
    @endforeach
</select>
