@props(['error' => false])

<select {{ $attributes->merge([
    'class' => 'block h-field w-full appearance-none rounded-sm border bg-surface bg-[length:1rem] bg-[position:right_0.75rem_center] bg-no-repeat py-0 pr-9 pl-3 text-field text-text-primary field-chevron focus-visible:border-accent-700 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus-ring disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-text-muted '
        .($error ? 'border-danger-600' : 'border-border-input'),
]) }}>
    {{ $slot }}
</select>
