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
    'class' => 'block h-9 w-full rounded-sm border bg-surface px-3 text-base text-text-primary focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-text-disabled '
        .($error ? 'border-danger-600' : 'border-border'),
]) }}>
    @foreach (config('ribbon.user_locales') as $localeCode)
        <option value="{{ $localeCode }}">{{ __('sellers.language_option.'.$localeCode) }}</option>
    @endforeach
</select>
