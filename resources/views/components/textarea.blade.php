@props(['error' => false])

<textarea {{ $attributes->merge([
    'class' => 'block min-h-24 w-full rounded-sm border bg-surface px-3 py-2 text-field text-text-primary placeholder:text-text-muted focus-visible:border-accent-700 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-focus-ring disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-text-muted '
        .($error ? 'border-danger-600' : 'border-border-input'),
]) }}>{{ $slot }}</textarea>
