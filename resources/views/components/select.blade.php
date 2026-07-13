@props(['error' => false])

<select {{ $attributes->merge([
    'class' => 'block h-9 w-full rounded-sm border bg-surface px-3 text-base text-text-primary focus:border-accent-500 focus:ring-2 focus:ring-accent-100 focus:outline-none disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-text-disabled '
        .($error ? 'border-danger-600' : 'border-border'),
]) }}>
    {{ $slot }}
</select>
